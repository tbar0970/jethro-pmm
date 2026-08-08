<?php

/**
 * SMS script — Jethro wrapper around the jethro-sms package CLI.
 *
 * The CLI core (actions, argument parsing, output) lives in the jethro-sms
 * package (vendor/jethro/jethro-sms/src/cli.php); this wrapper boots Jethro and adds what only a
 * Jethro host can provide:
 *
 *   - person-ID recipients (--to=42 looks up the person's mobile; sends are
 *     then DB-logged through the full bridge chain with token expansion)
 *   - DB-backed cancel (updates sms/smsdelivery rows as well as the gateway)
 *   - Configured/Usable lines in `info`
 *   - export-smslog (reads the sms/smsdelivery tables)
 *
 * Usage:
 *   HTTP_HOST=jethro.example.org ./scripts/sms.php <action> [options] [message]
 *
 * Run with no arguments for the full action list, or see the package README
 * (vendor/jethro/jethro-sms/README.md) for the standalone equivalent,
 * bin/jethro-sms (also installed as vendor/bin/jethro-sms).
 */

use Jethro\Sms\JethroSmsRecipient;
use Sms\PhoneNumber;

// ===========================================================================
// Bootstrap Jethro
// ===========================================================================

if (!defined('JETHRO_ROOT')) {
	define('JETHRO_ROOT', dirname(__DIR__));
}

// --conf=<path> override: load an alternate config file, then strip the flag
$confFile = null;
foreach ($_SERVER['argv'] as $i => $arg) {
    if (str_starts_with($arg, '--conf=')) {
        $confFile = substr($arg, 7);
        unset($_SERVER['argv'][$i]);
        break;
    }
}
$_SERVER['argv'] = array_values($_SERVER['argv']);

if ($confFile !== null) {
    if (!is_readable($confFile)) throw new RuntimeException("Config file not readable: $confFile");
    require_once $confFile;
} else {
    if (!is_readable(JETHRO_ROOT.'/conf.php')) {
        throw new RuntimeException('Jethro configuration file not found. You need to copy conf.php.sample to conf.php and edit it before Jethro can run');
    }
    require_once JETHRO_ROOT.'/conf.php';
}
define('DB_MODE', 'private');
require_once JETHRO_ROOT.'/include/init.php';
require_once JETHRO_ROOT.'/include/user_system.class.php';
$GLOBALS['user_system'] = new User_System();
$GLOBALS['user_system']->setCLIScript();
require_once JETHRO_ROOT.'/include/system_controller.class.php';
$GLOBALS['system'] = System_Controller::get();

// Load the SMS pipeline (bridge; pulls in the jethro-sms package)
require_once JETHRO_ROOT.'/include/jethro_sms.php';

// ===========================================================================
// Jethro-specific CLI environment
// ===========================================================================

/**
 * Export all sent SMSes as JSON arrays matching the legacy sms.log format.
 *
 * Each line is a JSON array: [timestamp, username, recipient_count, null, truncated_body]
 * - timestamp: ISO 8601 in UTC
 * - username: staff_member username of sender
 * - recipient_count: number of recipients (as string)
 * - null: legacy placeholder
 * - truncated_body: first 27 chars of message + "..." if > 30 chars
 */
function handleExportSmslog(): void
{
    $db = $GLOBALS['db'];

    $rows = $db->queryAll(
        'SELECT s.body,
                COALESCE(sm.username, \'system\') AS username,
                s.created,
                COUNT(d.id) AS recipient_count
         FROM sms s
         LEFT JOIN staff_member sm ON sm.id = s.sender
         LEFT JOIN _smsdelivery d ON d.sms_id = s.id
         GROUP BY s.id
         ORDER BY s.created'
    );

    foreach ($rows as $row) {
        $body  = (string) $row['body'];
        $count = (int) $row['recipient_count'];

        // Truncate body at 27 chars + "..." if > 30 chars total.
        if (mb_strlen($body) > 30) {
            $body = mb_substr($body, 0, 27) . '...';
        }

        echo json_encode([
            gmdate('Y-m-d\\TH:i:s+00:00', strtotime((string) $row['created'])),
            $row['username'],
            (string) $count,
            null,
            $body,
        ]) . "\n";
    }
}


/**
 * Compare upstream provider deliveries with local database records.
 *
 * Usage: list-diff [--since=<unix_timestamp>], e.g. --since=$(date -d 2016-01-01 +%s)
 */
function handleListDiff(array $args): void
{
	$opts = $pos = [];
	\Sms\Cli\parseArgs(array_slice($args, 1), $opts, $pos);
	$sinceRaw = $opts['since'][0] ?? null;
	$since = $sinceRaw !== null ? (int) $sinceRaw : null;

	echo \Jethro\Sms\checkSynchronized($since);
}

/**
 * Import upstream deliveries missing from the local database.
 *
 * Usage: sync-history [--since=<unix_timestamp>], e.g. --since=$(date -d 2016-01-01 +%s)
 */
function handleSyncHistory(array $args): void
{
	$opts = $pos = [];
	\Sms\Cli\parseArgs(array_slice($args, 1), $opts, $pos);
	$sinceRaw = $opts['since'][0] ?? null;
	$since = $sinceRaw !== null ? (int) $sinceRaw : null;

	try {
		$result = \Jethro\Sms\synchronizeHistory($since);
	} catch (\RuntimeException $e) {
		throw new \Sms\Cli\CliError($e->getMessage());
	}
	echo "Imported {$result['imported']} deliveries in {$result['batches']} new batches ({$result['skipped']} batches already existed).\n";
}


$env = new \Sms\Cli\CliEnvironment(
	providerFactory: fn (bool $logToDb): \Result => \Jethro\Sms\getSmsProvider(logToDb: $logToDb),
	// Person IDs (1-6 digits) → DB lookup, otherwise raw mobile.
	recipientResolver: function (string $toArg): ?\Sms\SmsRecipient {
		if (ctype_digit($toArg) && strlen($toArg) <= 6) {
			$person = $GLOBALS['system']->getDBObject('person', (int) $toArg);
			if (!$person || !$person->id) {
				fwrite(STDERR, "Warning: Person ID $toArg not found, skipping.\n");
				return null;
			}
			$mobile = (string) $person->getValue('mobile_tel');
			if ($mobile === '') {
				fwrite(STDERR, "Warning: Person ID $toArg has no mobile number, skipping.\n");
				return null;
			}
			return new JethroSmsRecipient(
				personId: (int) $toArg,
				number: new PhoneNumber($mobile),
			);
		}
		return new PhoneNumber($toArg);
	},
	// DB-aware cancel: updates sms/smsdelivery rows as well as the gateway.
	cancel: fn (\Sms\SmsDeliveryBatch $batch): \Result => \Jethro\Sms\cancelSms($batch),
	extraActions: [
		'export-smslog' => fn (array $args, \Sms\Cli\Presenter $ui) => handleExportSmslog(),
		'list-diff'     => fn (array $args, \Sms\Cli\Presenter $ui) => handleListDiff($args),
		'sync-history'  => fn (array $args, \Sms\Cli\Presenter $ui) => handleSyncHistory($args),
	],
	extraActionHelp: [
		'export-smslog' => "Print database send history in the legacy sms.log JSON-lines format",
		'list-diff'     => "Compare provider deliveries with the local database (read-only)\n"
			. "Optional: --since=<unix_timestamp>  (default: last 24 hours,\n"
			. "    e.g. --since=\$(date -d 2016-01-01 +%s))",
		'sync-history'  => "Import provider deliveries missing from the local database\n"
			. "Optional: --since=<unix_timestamp>  (default: last 24 hours;\n"
			. "    for everything the provider holds: --since=\$(date -d 2016-01-01 +%s))",
	],
	extraInfoLines: fn (): array => [
		'Configured' => \Jethro\Sms\isConfigured() ? 'Yes' : 'No',
		'Usable' => \Jethro\Sms\isUsable() ? 'Yes' : 'No',
	],
	usageProgram: './scripts/sms.php',
	usageEnvPrefix: 'HTTP_HOST=jethro.mychurch.org ',
);

\Sms\Cli\main($_SERVER['argv'], $env);
