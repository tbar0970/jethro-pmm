<?php
/**
 * This script can be used to trigger the migration of settings from conf.php
 * to the database (https://github.com/tbar0970/jethro-pmm/issues/15)
 *
 * It is not NECESSARY to run this script - you can instead just load Jethro
 * in the browser to trigger the migration.  But sometimes it's conveient to be
 * able to do it (in bulk, pehaps) from the command line.
 */
if ((php_sapi_name() !== 'cli') && !defined('STDIN')) {
	echo "This script must be run from the command line";
	exit;
}
ini_set('display_errors', 1);
define('JETHRO_ROOT', dirname(dirname(__FILE__)));
set_include_path(get_include_path().PATH_SEPARATOR.JETHRO_ROOT);
if (!is_readable(JETHRO_ROOT.'/conf.php')) {
	trigger_error('Jethro configuration file not found.  You need to copy conf.php.sample to conf.php and edit it before Jethro can run', E_USER_ERROR);
	exit();
}
require_once JETHRO_ROOT.'/conf.php';

define('DB_MODE', 'PRIVATE');
require_once JETHRO_ROOT.'/include/init.php';
dump_messages();
