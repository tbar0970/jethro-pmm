<?php
/**
 * Searches a Jethro database for Person Reports with invalid references
 * (deleted custom fields, groups, select options, statuses, congregations, etc.)
 * Safe to run at any time; makes no changes.
 * See https://github.com/tbar0970/jethro-pmm/issues/1413
 */
if ((php_sapi_name() !== 'cli') && !defined('STDIN')) {
	echo "This script must be run from the command line";
	exit;
}
ini_set('display_errors', 1);
define('JETHRO_ROOT', dirname(dirname(__FILE__)));
$_SERVER['HTTP_HOST'] = $_SERVER['ATL_FQDN'];
set_include_path(get_include_path().PATH_SEPARATOR.JETHRO_ROOT);
if (!is_readable(JETHRO_ROOT.'/conf.php')) {
	throw new \RuntimeException('Jethro configuration file not found.  You need to copy conf.php.sample to conf.php and edit it before Jethro can run');
	exit();
}
require_once JETHRO_ROOT.'/conf.php';
define('DB_MODE', 'private');
require_once JETHRO_ROOT.'/include/init.php';
require_once JETHRO_ROOT.'/include/user_system.class.php';
require_once JETHRO_ROOT.'/include/system_controller.class.php';
$GLOBALS['user_system'] = new User_System();
$GLOBALS['user_system']->setCLIScript();
$GLOBALS['system'] = System_Controller::get();
$db = $GLOBALS['db'];

$GLOBALS['system']->includeDBClass('person_query');

$queries = $db->queryAll('SELECT id, name FROM person_query ORDER BY id');
$found = 0;
foreach ($queries as $row) {
	$query = new Person_Query($row['id']);
	$badParams = $query->getValidationErrors();
	if (empty($badParams)) continue;
	$found++;
	echo 'Query '.$row['id'].': '.$row['name']."\n";
	foreach (Person_Query::formatValidationErrors($badParams) as $message) {
		echo '  - '.$message."\n";
	}
}
if ($found === 0) {
	echo "No invalid references found.\n";
}
