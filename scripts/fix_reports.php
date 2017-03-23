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
if (!defined('DSN')) define('DSN', constant('PRIVATE_DSN'));
require_once JETHRO_ROOT.'/include/init.php';
$db = $GLOBALS['db'];

$SQL = 'CREATE TABLE _disused_person_query_backup SELECT * from person_query';
$res = $db->exec($SQL);
check_db_result($res);

$SQL = 'SELECT id, params FROM _disused_person_query_backup';
$queries = $db->queryAll($SQL);
check_db_result($queries);
foreach ($queries as $row) {
	$params = unserialize($row['params']);
	if (!empty($params['rules']['p.age_bracket'])) {
		$params['rules']['p.age_bracketid'] = (int)$params['rules']['p.age_bracket']+1;
		unset($params['rules']['p.age_bracket']);
		$SQL = 'UPDATE person_query SET params = '.$db->quote(serialize($params)).' WHERE id = '.(int)$row['id'];
		$res = $db->exec($SQL);
		check_db_result($res);
	}
}
echo "done \n";