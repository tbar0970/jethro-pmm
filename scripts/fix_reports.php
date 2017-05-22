<?php
/**
 * This script can be used to fix problems in reports following an upgrade to 2.19.0
 * This script is ONLY needed if your system was upgraded to 2.19.0 exactly.
 * If you upgraded from something before 2.19.0 to 2.19.1 or later, it is not necessary.
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
$db = $GLOBALS['db'];

$SQL = 'CREATE TABLE IF NOT EXISTS _disused_person_query_backup SELECT * from person_query';
$res = $db->exec($SQL);
check_db_result($res);

$SQL = 'SELECT id, params FROM _disused_person_query_backup';
$queries = $db->queryAll($SQL);
check_db_result($queries);
foreach ($queries as $row) {
	$params = unserialize($row['params']);
	if (!empty($params['rules']['p.age_bracket'])) {
		$params['rules']['p.age_bracketid'] = Array();
		foreach ($params['rules']['p.age_bracket'] as $k => $v) {
			$params['rules']['p.age_bracketid'][$k] = (int)$v+1;
		}
		unset($params['rules']['p.age_bracket']);
		$SQL = 'UPDATE person_query SET params = '.$db->quote(serialize($params)).' WHERE id = '.(int)$row['id'];
		$res = $db->exec($SQL);
		check_db_result($res);
	}
}

echo "done \n";
