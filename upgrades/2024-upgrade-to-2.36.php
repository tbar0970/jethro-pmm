#!/usr/bin/env php
<?php
/**
 * Run this script when upgrading from pre-2.36 to 2.36 or above, after applying upgrades/2024-upgrade-to-2.36.sql.
 * It is idempotent i.e. safe to run more than once in Jethro 2.36 and above.
 *
 * Jethro 2.36 introduced the person_status table (https://github.com/tbar0970/jethro-pmm/issues/1035).
 * This script edits reports (person_queries) and action plans (action_plan) stored in the database, to reference IDs in person_status.
 */

ini_set('display_errors', 1);
define('JETHRO_ROOT', dirname(dirname(__FILE__)));
set_include_path(get_include_path().PATH_SEPARATOR.JETHRO_ROOT);

function fail($msg) {
	trigger_error($msg, E_USER_ERROR);
}

is_readable(JETHRO_ROOT.'/conf.php') || fail('Jethro configuration file not found.  You need to copy conf.php.sample to conf.php and edit it before Jethro can run', E_USER_ERROR);
require_once JETHRO_ROOT.'/conf.php';
define('DB_MODE', 'private');
require_once JETHRO_ROOT.'/include/init.php';
/** @var JethroDB $db */
$db = $GLOBALS['db'];

$person_status = $db->queryAll('SELECT id, label FROM person_status', NULL, NULL, true);
if ($person_status === null) fail('No person_status table in the database. If this is a pre-2.36 Jethro instance, please first run 2024-upgrade-to-2.36.sql');
$archived_id = array_search("Archived", $person_status);
if ($archived_id===false) fail("Missing 'Archived' person_status");
$contact_id = array_search("Contact", $person_status);
if ($contact_id===false) fail("Missing 'Contact' person_status");

/**
 * Convert from old status string ("1") to new status id (2). If $oldstatus is already an int it is considered already upgraded, and is returned unmodified.
 * @param $oldstatus string ("1") or int (2)
 * @return int|string int (2))
 */
function upgradeStatus($oldstatus)  {
	global $archived_id, $person_status;
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
	}
	// Post-2.36. Return unaltered int.
	return $oldstatus;
}

/**
 * Fix person_reports if needed.
 */
function fixPersonReports(): void
{
	global $db, $person_status;
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
                    'upgradeStatus'
				, $oldstatuses);
			if ($oldstatuses != $newstatuses) {
				echo "Updating report $row[id]\n";
				$params['rules']['p.status'] = $newstatuses;
				$SQL = 'UPDATE person_query SET params = '.$db->quote(serialize($params)).' WHERE id = '.(int)$row['id'];
				$res = $db->exec($SQL);
			}
		}
	}
	$SQL = "DROP TABLE _disused_person_query_backup;";
	$db->queryAll($SQL, NULL, NULL, true);
}


/**
 * Fix action_plans if needed.
 */
function fixActionPlans()
{
	global $db, $person_status;
	$SQL = "DROP TABLE IF EXISTS _disused_action_plan_backup; 
CREATE TABLE IF NOT EXISTS _disused_action_plan_backup SELECT * from action_plan;";
	$res = $db->exec($SQL);

	$SQL = 'SELECT id, actions FROM _disused_action_plan_backup';
	$queries = $db->queryAll($SQL);
	foreach ($queries as $row) {
		$actions = unserialize($row['actions']);
		if (isset($actions['fields']['status'])) {
			$oldstatus = $actions['fields']['status']['value'];
			$newstatus = upgradeStatus($oldstatus);
			if ($oldstatus != $newstatus) {
				echo "Updating action plan $row[id]\n";
				$actions['fields']['status']['value'] = $newstatus;
				$SQL = 'UPDATE action_plan SET actions = '.$db->quote(serialize($actions)).' WHERE id = '.(int)$row['id'];
				$res = $db->exec($SQL);
			}
		}
	}
	$SQL = "DROP TABLE _disused_action_plan_backup;";
	$db->queryAll($SQL, NULL, NULL, true);
}

fixPersonReports();
fixActionPlans();

echo "done \n";
