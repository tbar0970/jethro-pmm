#!/usr/bin/env php
<?php
/**
 * Run this script when upgrading from pre-2.36 to 2.36 or above, after applying upgrades/2024-upgrade-to-2.36.sql.
 * It is idempotent i.e. safe to run more than once in Jethro 2.36 and above.
 *
 * Jethro 2.36 introduced the person_status table (https://github.com/tbar0970/jethro-pmm/issues/1035).
 * This script edits reports (person_queries) stored in the database, to reference IDs in person_status.
 */

ini_set('display_errors', 1);
define('JETHRO_ROOT', dirname(dirname(__FILE__)));
set_include_path(get_include_path().PATH_SEPARATOR.JETHRO_ROOT);

function fail($msg) {
	trigger_error($msg, E_USER_ERROR);
	exit();
}
is_readable(JETHRO_ROOT.'/conf.php') || fail('Jethro configuration file not found.  You need to copy conf.php.sample to conf.php and edit it before Jethro can run', E_USER_ERROR);
require_once JETHRO_ROOT.'/conf.php';
define('DB_MODE', 'private');
require_once JETHRO_ROOT.'/include/init.php';
$db = $GLOBALS['db'];

$person_status = $db->queryAll('SELECT id, label FROM person_status', NULL, NULL, true);
$archived_id=array_search("Archived", $person_status) || fail("Missing 'Archived' person_status");
$contact_id = array_search("Contact", $person_status) || fail("Missing 'Contact' person_status");

$SQL = "DROP TABLE IF EXISTS _disused_person_query_backup; 
CREATE TABLE IF NOT EXISTS _disused_person_query_backup SELECT * from person_query;";
$res = $db->exec($SQL);

$SQL = 'SELECT id, params FROM _disused_person_query_backup';
$queries = $db->queryAll($SQL);
foreach ($queries as $row) {
	$params = unserialize($row['params']);
	if (isset($params['rules']['p.status'])) {
		$oldstatuses = $params['rules']['p.status'];
		$newstatuses = array_map(
			function ($oldstatus) use($person_status) {
				global $archived_id;
				global $contact_id;

                // Before 2.36 p.status was an array of strings, e.g. ["0"] for 'Regular' or ["1", "archived"] for "Irregular and archived"
                // In 2.36+ p.status is an int, a foreign key into person_status.
				if (is_string($oldstatus)) {
                    // Pre-2.36 - return an int equivalent of the old string.
					if ($oldstatus == "archived") {
						return $archived_id;
					} elseif ($oldstatus == "contact") {
						return $contact_id;
					} else {
						// $newval is oldval plus one, as set in upgrades/2024-upgrade-to-2.36.sql
						$newval = ((int)$oldstatus) + 1;
						if ($person_status[$newval]) {
							return $newval;
						} else {
							fail("No person_status with id $newval");
						}
					}
				} else {
                    // Post-2.36. Return unaltered int.
					return $oldstatus;
				}
			}
			, $oldstatuses);
		if ($oldstatuses != $newstatuses) {
			echo "Updating report $row[id]\n";
			$params['rules']['p.status'] = $newstatuses;
			$SQL = 'UPDATE person_query SET params = '.$db->quote(serialize($params)).' WHERE id = '.(int)$row['id'];
			$res = $db->exec($SQL);
		}
	}
}
$SQL="DROP TABLE _disused_person_query_backup;";
$db->queryAll($SQL, NULL, NULL, true);

echo "done \n";
