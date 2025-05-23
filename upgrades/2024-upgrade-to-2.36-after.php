#!/usr/bin/env php
<?php
/**
 * Run this script when upgrading from pre-2.36 to 2.36 or above, after applying upgrades/2024-upgrade-to-2.36.sql.
 * It is idempotent i.e. safe to run more than once in Jethro 2.36 and above.
 *
 * Jethro 2.36 introduced the person_status table (https://github.com/tbar0970/jethro-pmm/issues/1035).
 * This script edits reports (person_queries) and action plans (action_plan) stored in the database, to reference IDs in person_status.
 */

 if ((php_sapi_name() !== 'cli') && !defined('STDIN')) {
	echo "This script must be run from the command line";
	exit;
}

ini_set('display_errors', 1);
define('JETHRO_ROOT', dirname(dirname(__FILE__)));
set_include_path(get_include_path().PATH_SEPARATOR.JETHRO_ROOT);

is_readable(JETHRO_ROOT.'/conf.php') || fail('Jethro configuration file not found.  You need to copy conf.php.sample to conf.php and edit it before Jethro can run', E_USER_ERROR);
require_once JETHRO_ROOT.'/conf.php';
define('DB_MODE', 'private');
require_once JETHRO_ROOT.'/include/init.php';
/** @var JethroDB $db */
$db = $GLOBALS['db'];

include 'upgradelibs/Status_Upgrader.class.php';
Status_Upgrader::run();
echo "done \n";