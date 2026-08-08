<?php
/**
 * SMS low balance reminder script.
 *
 * Checks the current SMS provider balance against SMS_BALANCE_LOW_THRESHOLD.
 * If the balance is below the threshold, sends an email notification to
 * the address configured in SMS_BALANCE_LOW_NOTIFICANT_EMAIL.
 *
 * To avoid repeated alerts, only sends a notification if an SMS has been
 * sent in the last 24 hours.
 *
 * Notifications are sent by email only — sending an SMS warning about
 * a low balance would consume further credits from the depleted account.
 *
 * Intended to be run once daily via cron:
 *
 *   php ./scripts/sms_low_reminder.php --sender=admin@mychurch.org
 *
 * Optional flags:
 *   --sender=<email>   Sender email address (required when not in --debug mode).
 *   --reply-to=<email>      Reply-To address for the notification email.
 *   --reply-to-name=<name>  Display name for the Reply-To address.
 *   --debug            Print the email content to stdout instead of sending it.
 *   --verbose          Print extra diagnostic information.
 *
 * Required settings (configured via the system config page or conf.php):
 *   SMS_BALANCE_LOW_THRESHOLD   — balance level that triggers a notification (0 = disabled)
 *   SMS_BALANCE_LOW_NOTIFICANT_EMAIL  — email address that receives the notification
 *                                       (need not belong to a Jethro person)
 */

if ((php_sapi_name() !== 'cli') && !defined('STDIN')) {
	echo "This script must be run from the command line\n";
	exit(1);
}

define('JETHRO_ROOT', dirname(__DIR__));
set_include_path(get_include_path() . PATH_SEPARATOR . JETHRO_ROOT);

if (!is_readable(JETHRO_ROOT . '/conf.php')) {
	throw new RuntimeException('Jethro configuration file not found. Copy conf.php.sample to conf.php and edit it before Jethro can run');
}

require_once JETHRO_ROOT . '/conf.php';
define('DB_MODE', 'private');
require_once JETHRO_ROOT . '/include/init.php';
require_once JETHRO_ROOT . '/include/user_system.class.php';
$GLOBALS['user_system'] = new User_System();
$GLOBALS['user_system']->setCLIScript();
require_once JETHRO_ROOT . '/include/system_controller.class.php';
$GLOBALS['system'] = System_Controller::get();

require_once JETHRO_ROOT . '/include/jethro_sms.php';
require_once JETHRO_ROOT . '/include/emailer.class.php';

// ---------------------------------------------------------------------------
// Parse flags
// ---------------------------------------------------------------------------

$debug = in_array('--debug', $argv ?? [], true);
$verbose = $debug || in_array('--verbose', $argv ?? [], true);
$sender = null;
$replyTo = null;
$replyToName = null;
foreach ($argv ?? [] as $arg) {
	if (str_starts_with($arg, '--sender=')) {
		$sender = substr($arg, strlen('--sender='));
	} elseif (str_starts_with($arg, '--reply-to=')) {
		$replyTo = substr($arg, strlen('--reply-to='));
	} elseif (str_starts_with($arg, '--reply-to-name=')) {
		$replyToName = substr($arg, strlen('--reply-to-name='));
	}
}

// ---------------------------------------------------------------------------
// Check configuration
// ---------------------------------------------------------------------------

$threshold = (int) ifdef('SMS_BALANCE_LOW_THRESHOLD', 0);
$notificantEmail = trim((string) ifdef('SMS_BALANCE_LOW_NOTIFICANT_EMAIL', ''));

if ($threshold <= 0) {
	echo "SMS_BALANCE_LOW_THRESHOLD is not set or zero — nothing to do.\n";
	exit(0);
}

if ($notificantEmail === '') {
	echo "SMS_BALANCE_LOW_NOTIFICANT_EMAIL is not set — cannot send notification.\n";
	exit(1);
}

// ---------------------------------------------------------------------------
// Get current balance
// ---------------------------------------------------------------------------

$balance = \Jethro\Sms\getSmsBalance();

if ($balance === null) {
	echo "Could not retrieve SMS account balance.\n";
	if ($verbose) {
		$pr = \Jethro\Sms\getSmsProvider();
		if ($pr->isFailure()) {
			echo "Provider error: " . $pr->getError() . "\n";
		}
	}
	echo "Skipping notification.\n";
	exit(1);
}

echo "Current SMS balance: $balance (threshold: $threshold)\n";

if ($balance > $threshold) {
	echo "Balance is above threshold — no notification needed.\n";
	exit(0);
}

// ---------------------------------------------------------------------------
// Check for recent SMS activity — only notify if someone is actively sending
// ---------------------------------------------------------------------------

$db = $GLOBALS['db'];
$latestSent = $db->queryOne(
	'SELECT MAX(s.created) FROM sms s JOIN _smsdelivery d ON d.sms_id = s.id WHERE d.status IN (\'sent\', \'delivered\')'
);

if ($latestSent === null || $latestSent === '') {
	echo "No delivered SMS broadcasts found — skipping notification (no recent activity).\n";
	exit(0);
}

$cutoff = date('Y-m-d H:i:s', strtotime('-24 hours'));
if ($latestSent < $cutoff) {
	echo "Most recent delivered SMS was at $latestSent (more than 24 hours ago) — skipping notification.\n";
	exit(0);
}

echo "Most recent delivered SMS was at $latestSent (within 24 hours) — sending notification.\n";

// ---------------------------------------------------------------------------
// Send notification
// ---------------------------------------------------------------------------

$subject = 'SMS account balance is low: ' . $balance . ' SMSes remaining';
$body = "The SMS account balance for " . ifdef('SYSTEM_NAME', 'Jethro') . " is critically low.\n\n"
	. "Current balance: $balance SMSes remaining\n"
	. "Low-balance threshold: $threshold\n"
	. "Most recent delivered SMS: $latestSent\n\n"
	. "Please arrange a top-up to avoid service interruption.\n";

if ($debug) {
	echo "\nDEBUG MODE — email not sent. Content:\n";
	echo "----------------------------------------\n";
	echo "From: $sender\n";
	echo "To: $notificantEmail\n";
	echo "Subject: $subject\n\n";
	echo $body;
	echo "----------------------------------------\n";
	exit(0);
}

if ($sender === null || $sender === '') {
	echo "Error: --sender=<email> is required (e.g. --sender=admin@mychurch.org).\n";
	exit(1);
}

$message = Emailer::newMessage()
	->setFrom($sender)
	->setSubject($subject)
	->text($body)
	->addTo($notificantEmail);
if ($replyTo) {
	$message->setReplyTo($replyTo, $replyToName);
}

$result = Emailer::send($message);

if ($result) {
	echo "Notification sent to $notificantEmail.\n";
	exit(0);
} else {
	echo "Failed to send notification to $notificantEmail.\n";
	exit(1);
}
