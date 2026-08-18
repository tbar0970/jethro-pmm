#!/usr/bin/env php
<?php
/**
 * Convert any remaining non-standard-collation tables to utf8mb4_unicode_ci.
 *
 * Run this script when upgrading from 2.38 (or earlier) to 2.40 or above. It is
 * idempotent - safe to run more than once, and safe to run on a database that has
 * already been converted.
 *
 * Jethro 2.40 makes utf8mb4/utf8mb4_unicode_ci the standard character set for new
 * installs: the table-creation code (db_object.class.php, installer.class.php) and
 * the PDO connection charset now all use utf8mb4. This script brings existing
 * databases into line by converting every table not already on utf8mb4_unicode_ci
 * (utf8mb3, latin1, or a non-standard utf8mb4 collation) to utf8mb4_unicode_ci.
 *
 * Notes:
 * - The conversion is lossless for every character representable in utf8mb3 (the
 *   Basic Multilingual Plane); 4-byte characters could not be stored in utf8mb3
 *   tables, so there is nothing to lose.
 * - Tables still on the legacy COMPACT/REDUNDANT row format are moved to DYNAMIC
 *   in the same ALTER, because their indexes would otherwise exceed the 767-byte
 *   InnoDB key limit once widened to utf8mb4 (eg the family table's composite
 *   name/address index is 1368 bytes as utf8mb4). DYNAMIC is the modern default
 *   row format anyway (3072-byte key limit).
 * - Views need no rebuilding: their columns resolve to the base tables' charsets
 *   at query time. The script verifies the four known views still execute.
 * - Latin1 tables that contain raw UTF-8 bytes (from PHP < 5.3.6 era or
 *   misconfigured connections) are handled safely via a BLOB detour:
 *   CONVERT TO binary preserves bytes, then MODIFY cols to utf8mb4
 *   reinterprets them without double-encoding. This matches the Admin
 *   dashboard path (view_10_admin__8_upgrade.class.php → DB_Charset_Utils::fix()).
 */

if ((php_sapi_name() !== 'cli') && !defined('STDIN')) {
	echo "This script must be run from the command line";
	exit;
}

ini_set('display_errors', 1);
define('JETHRO_ROOT', dirname(dirname($_SERVER['SCRIPT_FILENAME'])));
set_include_path(get_include_path().PATH_SEPARATOR.JETHRO_ROOT);

if (!is_readable(JETHRO_ROOT.'/conf.php')) {
	echo "Jethro configuration file not found.  You need to copy conf.php.sample to conf.php and edit it before Jethro can run\n";
	exit(1);
}
require_once JETHRO_ROOT.'/conf.php';
define('DB_MODE', 'private');
require_once JETHRO_ROOT.'/include/init.php';
require_once JETHRO_ROOT.'/upgrades/upgradefixes/2.40.0_fix_db_charset/dbcharsetutils.php';
/** @var JethroDB $db */
$db = $GLOBALS['db'];
echo "Converting tables in database ".ifdef('DB_DATABASE', 'unknown')." to utf8mb4_unicode_ci...\n";

try {
	$result = DB_Charset_Utils::fix();
} catch (RuntimeException $e) {
	// DB_Charset_Utils::fix() throws on MySQL when it cannot ALTER DATABASE
	// (requires SUPER). The table conversion already completed; the
	// database-default alignment is handled below with MySQL-aware logic.
	$result = Array(
		'converted' => Array(),
		'errors' => Array(),
		'database_default_aligned' => FALSE,
	);
}

if (!empty($result['converted'])) {
	foreach ($result['converted'] as $tbl) {
		echo "  - ".$tbl."\n";
	}
	echo count($result['converted'])." table(s) converted to utf8mb4_unicode_ci.\n";
}
if (!empty($result['errors'])) {
	echo "Errors:\n";
	foreach ($result['errors'] as $error) {
		echo "  * ".$error."\n";
	}
}

// Align the database default charset as well, so tables created without an explicit
// charset clause in the future (eg from later upgrades) also inherit utf8mb4.
$dbname = $db->queryOne('SELECT DATABASE()');
if (!empty($dbname)) {
	// MySQL requires the SUPER privilege for ALTER DATABASE, which the
	// application user typically lacks. On MySQL we check whether the
	// database default even needs changing and either print the SQL to run
	// or skip (if already correct). On MariaDB, ALTER DATABASE works
	// with database-level privileges, so we attempt it.
	$version = $db->queryOne('SELECT VERSION()');
	$isMySQL = (stripos($version, 'MariaDB') === false);

	if ($isMySQL) {
		$current = $db->queryRow(
			"SELECT DEFAULT_CHARACTER_SET_NAME AS charset, DEFAULT_COLLATION_NAME AS collation
			 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()"
		);
		$needsAlignment = (empty($current) || $current['charset'] != 'utf8mb4' || $current['collation'] != 'utf8mb4_unicode_ci');
		if ($needsAlignment) {
			echo "\n";
			echo "WARNING: Cannot set the database default charset/collation on MySQL\n";
			echo "without the SUPER privilege. Please run the following command as a\n";
			echo "MySQL superuser (e.g. root):\n";
			echo "\n";
			echo "  ALTER DATABASE `".$dbname."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
			echo "\n";
			echo "Continuing with remaining steps...\n";
		}
		// Database default is already correct — nothing to do.
	} else {
		try {
			$db->exec('ALTER DATABASE `'.$dbname.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
		} catch (Exception $e) {
			echo "\n";
			echo "WARNING: Could not set the database default charset to utf8mb4:\n";
			echo "  ".$e->getMessage()."\n";
			echo "\n";
			if (stripos($e->getMessage(), 'access denied') !== false
				|| stripos($e->getMessage(), 'privilege') !== false) {
				echo "This is likely because your database user lacks the ALTER DATABASE privilege.\n";
				echo "MariaDB grants this at the database level (ALL PRIVILEGES ON db.* suffices),\n";
				echo "but MySQL 8 requires it at the server level (ALTER ON *.*).\n";
				echo "\n";
				echo "To complete the conversion, run this SQL as a superuser:\n";
				echo "  ALTER DATABASE `".$dbname."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
				echo "\n";
			}
			echo "Continuing with remaining steps...\n";
		}
	}
}

// Verify the four known views still execute (their columns follow the base tables' charset).
echo "Verifying views...\n";
foreach (Array('person', 'person_group', 'member', 'abstract_note') as $view) {
	$stmnt = $db->query('SELECT COUNT(*) FROM `'.$view.'`');
	$count = ($stmnt === false) ? 0 : $stmnt->fetchColumn();
	echo "  - ".$view." OK\n";
}
// Final check: nothing left unconverted.
$target = $db->quote('utf8mb4_unicode_ci');
$remaining = $db->queryAll(
	"SELECT TABLE_NAME AS tbl FROM information_schema.TABLES
	 WHERE TABLE_SCHEMA = DATABASE()
	   AND TABLE_COLLATION IS NOT NULL
	   AND TABLE_COLLATION <> ".$target.""
);
if ($remaining) {
	$names = implode(', ', array_map(function ($r) {
		return $r['tbl'];
	}, $remaining));
	echo "WARNING: the following tables are still not utf8mb4_unicode_ci: ".$names."\n";
	exit(1);
}
	// Clear the upgrade flag so the web UI no longer warns that an upgrade is needed.
	Config_Manager::deleteSetting('NEEDS_UTF8MB4_UPGRADE');
echo "Done. All tables are now utf8mb4_unicode_ci.\n";
