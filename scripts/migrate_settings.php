<?php
/**
 * This script can be used to trigger the migration of settings from conf.php
 * to the database (https://github.com/tbar0970/jethro-pmm/issues/15)
 *
 * It is not NECESSARY to run this script - you can instead just load Jethro
 * in the browser to trigger the migration.  But sometimes it's conveient to be
 * able to do it (in bulk, pehaps) from the command line.
 */

ini_set('display_errors', 1);
define('JETHRO_ROOT', dirname(dirname(__FILE__)));
set_include_path(get_include_path().PATH_SEPARATOR.JETHRO_ROOT);
if (!is_readable(JETHRO_ROOT.'/conf.php')) {
	trigger_error('Jethro configuration file not found.  You need to copy conf.php.sample to conf.php and edit it before Jethro can run', E_USER_ERROR);
	exit();
}
require_once JETHRO_ROOT.'/conf.php';
if (defined('PRIVATE_DSN')) {
        preg_match('|([a-z]+)://([^:]*)(:(.*))?@([A-Za-z0-9\.-]*)(/([0-9a-zA-Z_/\.]*))|',
     PRIVATE_DSN,$matches);
         if (!defined('DB_TYPE')) define('DB_TYPE', $matches[1]);
         if (!defined('DB_HOST')) define('DB_HOST', $matches[5]);
         if (!defined('DB_DATABASE')) define('DB_DATABASE', $matches[7]);
         if (!defined('DB_PRIVATE_USERNAME')) define('DB_PRIVATE_USERNAME', $matches[2]);
         if (!defined('DB_PRIVATE_PASSWORD')) define('DB_PRIVATE_PASSWORD', $matches[4]);
}
if (!defined('DSN')) {
        define('DSN', DB_TYPE . ':host=' . DB_HOST . (!empty(DB_PORT)? (';port=' . DB_PORT):'') . ';dbname=' . DB_DATABASE . ';charset=utf8');
}
if (!defined('DB_USERNAME')) define('DB_USERNAME', DB_PRIVATE_USERNAME);
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', DB_PRIVATE_PASSWORD);

require_once JETHRO_ROOT.'/include/init.php';
dump_messages();
