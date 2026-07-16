#!/usr/bin/env php
<?php
/**
 * Database charset and collation health check.
 *
 * Reports whether all tables use the standard utf8mb4/utf8mb4_unicode_ci charset
 * and collation, whether the database default is correct, and (with --detail)
 * per-column collation information.
 *
 * Usage:
 *   php scripts/database_health_check.php          # health report
 *   php scripts/database_health_check.php --fix    # health report + repair
 *   php scripts/database_health_check.php --detail # health report + column detail
 *
 * Idempotent — safe to run repeatedly.
 */

if ((php_sapi_name() !== 'cli') && !defined('STDIN')) {
	echo "This script must be run from the command line\n";
	exit(1);
}

ini_set('display_errors', 1);
define('JETHRO_ROOT', dirname(__DIR__));
set_include_path(get_include_path().PATH_SEPARATOR.JETHRO_ROOT);

if (!is_readable(JETHRO_ROOT.'/conf.php')) {
	echo "Jethro configuration file not found. Copy conf.php.sample to conf.php and edit it.\n";
	exit(1);
}
require_once JETHRO_ROOT.'/conf.php';
define('DB_MODE', 'private');
require_once JETHRO_ROOT.'/include/init.php';
require_once JETHRO_ROOT.'/upgrades/upgradefixes/2.40.0_fix_db_charset/dbcharsetutils.php';

$fix = in_array('--fix', $argv);
$detail = in_array('--detail', $argv);

// ── Health check ──────────────────────────────────────────────

$health = DB_Charset_Utils::checkHealth();

$dbname = $GLOBALS['db']->queryOne('SELECT DATABASE()') ?: 'unknown';
$target = DB_Charset_Utils::TARGET_CHARSET.' / '.DB_Charset_Utils::TARGET_COLLATION;
printf("%-13s %s\n", 'Database:', $dbname);
printf("%-13s %s\n", 'Target:', $target);
if ($health['database_default']) {
	$def = $health['database_default']['charset'].' / '.$health['database_default']['collation'];
	printf("%-13s %s\n", 'DB default:', $def);
}
echo "\n";

if ($health['healthy']) {
	echo "HEALTHY — all tables use the standard charset and collation.\n";
} else {
	foreach ($health['problems'] as $problem) {
		echo "  * ".$problem."\n";
	}
	if ($health['problem_tables']) {
		echo "\nProblem tables:\n";
		foreach ($health['problem_tables'] as $t) {
			$note = $t['needs_dynamic'] ? ' (row format: '.$t['row_format'].' → DYNAMIC)' : '';
			echo sprintf("  %-50s %s%s\n", $t['name'], $t['collation'], $note);
		}
	}
	echo "\n";
}

// ── Detail ────────────────────────────────────────────────────

if ($detail) {
	$coll = DB_Charset_Utils::getCollationDetail();
	echo "Column collation detail (database default: ".$coll['default_collation']."):\n";
	echo "\n";
	echo str_repeat('-', 90)."\n";
	printf("%-40s %-30s %-30s\n", 'Table', 'Column', 'Collation');
	echo str_repeat('-', 90)."\n";
	foreach ($coll['columns'] as $c) {
		$marker = ($c['column_collation'] !== DB_Charset_Utils::TARGET_COLLATION) ? ' !' : '  ';
		printf("%-40s %-30s %-30s%s\n", $c['tbl'], $c['col'], $c['column_collation'], $marker);
	}
	echo str_repeat('-', 90)."\n";
	echo "\nSummary:\n";
	foreach ($coll['collation_counts'] as $row) {
		$marker = ($row['collation'] !== DB_Charset_Utils::TARGET_COLLATION) ? ' !' : '';
		printf("  %-30s %d columns%s\n", $row['collation'], $row['cnt'], $marker);
	}
	echo "\n";
}
// ── Latin1 / UTF-8 mismatch check ─────────────────────────────

$latin1check = DB_Charset_Utils::detectLatin1UTF8Bytes();
if ($latin1check['risky']) {
	echo "╔══════════════════════════════════════════════════════════════╗\n";
	echo "║  Note: Latin1 tables with UTF-8 bytes detected              ║\n";
	echo "╚══════════════════════════════════════════════════════════════╝\n";
	echo "\n";
	echo count($latin1check['risky_columns'])." column(s) in latin1 tables contain raw UTF-8 bytes\n";
	echo "(PHP < 5.3.6 PDO bug). --fix will convert these safely via a\n";
	echo "BLOB detour: CONVERT TO binary, then CONVERT TO utf8mb4.\n";
	echo "\n";
	echo "Affected columns:\n";
	foreach ($latin1check['risky_columns'] as $rc) {
		printf("  %-40s %s\n", $rc['tbl'].'.'.$rc['col'], $rc['utf8_seq_type']);
	}
	echo "\n";
}

// ── Fix ───────────────────────────────────────────────────────

if ($fix) {
	echo "Running fix...\n";
	$result = DB_Charset_Utils::fix();

	if ($result['converted']) {
		printf("%-13s %d table(s): %s\n", 'Converted:', count($result['converted']), implode(', ', $result['converted']));
	}
	if ($result['database_default_aligned']) {
		printf("%-13s %s / %s\n", 'DB default:', DB_Charset_Utils::TARGET_CHARSET, DB_Charset_Utils::TARGET_COLLATION);
	}
	if ($result['errors']) {
		echo "\nErrors:\n";
		foreach ($result['errors'] as $error) {
			echo "  * ".$error."\n";
		}
		exit(1);
	}
	if (empty($result['converted']) && empty($result['errors'])) {
		echo "Nothing needed fixing.\n";
	}
	echo "\n";
}
exit($health['healthy'] ? 0 : 1);
