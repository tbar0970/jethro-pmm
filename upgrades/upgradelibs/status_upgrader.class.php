<?php

class Status_Upgrader 
{

	static $person_status;
	static $archived_id;
	static $contact_id;

	public static function runHTML()
	{
		echo '<pre class="alert alert-info">';
		echo "<b>UPGRADING REPORTS AND ACTION PLANS FOR JETHRO v.2.36:</b>\n";
		self::run();
		echo "UPGRADE COMPLETE \n";
		echo '</pre>';
	}

	public static function run()
	{
		global $db;
		if (!ifdef('NEEDS_1035_UPGRADE')) {
			echo "Upgrade already completed \n";
			return;
		}
		self::$person_status = $db->queryAll('SELECT id, label FROM person_status', NULL, NULL, true);
		if (self::$person_status === null) trigger_error('No person_status table in the database. If this is a pre-2.36 Jethro instance, please first run 2024-upgrade-to-2.36.sql', E_USER_ERROR);
		self::$archived_id = array_search("Archived", self::$person_status);
		if (self::$archived_id===false) trigger_error("Missing 'Archived' person_status", E_USER_ERROR);
		self::$contact_id = array_search("Contact", self::$person_status);
		if (self::$contact_id===false) trigger_error("Missing 'Contact' person_status", E_USER_ERROR);

		self::fixPersonReports();
		self::fixActionPlans();	
		Config_Manager::deleteSetting('NEEDS_1035_UPGRADE');

	}

	/**
	 * Convert from old status string ("1") to new status id (2). If $oldstatus is already an int it is considered already upgraded, and is returned unmodified.
	 * @param $oldstatus string ("1") or int (2)
	 * @return int|string int (2))
	 */
	static function upgradeStatus($oldstatus)  {
		// Before 2.36 p.status was an array of strings, e.g. ["0"] for 'Regular' or ["1", "archived"] for "Irregular and archived"
		// In 2.36+ p.status is an int, a foreign key into person_status.
		if (is_string($oldstatus)) {
			// Pre-2.36 - return an int equivalent of the old string.
			if ($oldstatus == "archived") {
				return self::$archived_id;
			} elseif ($oldstatus == "contact") {
				return self::$contact_id;
			} else {
				// $newval is oldval plus one, as set in upgrades/2024-upgrade-to-2.36.sql
				$newval = ((int)$oldstatus) + 1;
				if (self::$person_status[$newval]) {
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
	static function fixPersonReports(): void
	{
		global $db;
		$SQL = 'SELECT id, params FROM _disused_person_query_backup_1035';
		$queries = $db->queryAll($SQL);
		foreach ($queries as $row) {
			$params = unserialize($row['params']);
			if (isset($params['rules']['p.status'])) {
				$oldstatuses = $params['rules']['p.status'];
				$newstatuses = array_map(
					Array(__CLASS__, 'upgradeStatus'),
					$oldstatuses);
				if ($oldstatuses != $newstatuses) {
					echo "Updating report $row[id]\n";
					$params['rules']['p.status'] = $newstatuses;
					$SQL = 'UPDATE person_query SET params = '.$db->quote(serialize($params)).' WHERE id = '.(int)$row['id'];
					$res = $db->exec($SQL);
				}
			}
		}
	}


	/**
	 * Fix action_plans if needed.
	 */
	static function fixActionPlans()
	{
		global $db;
		$SQL = 'SELECT id, actions FROM _disused_action_plan_backup_1035';
		$queries = $db->queryAll($SQL);
		foreach ($queries as $row) {
			echo "Examining action plan $row[id] ...";
			$actions = unserialize($row['actions']);
			if (isset($actions['fields']['status'])) {
				$oldstatus = $actions['fields']['status']['value'];
				$newstatus = Status_Upgrader::upgradeStatus($oldstatus);
				if ($oldstatus != $newstatus) {
					echo "Updating \n";
					$actions['fields']['status']['value'] = $newstatus;
					$SQL = 'UPDATE action_plan SET actions = '.$db->quote(serialize($actions)).' WHERE id = '.(int)$row['id'];
					$res = $db->exec($SQL);
				} else {
					echo "No update needed \n";
				}
			} else {
				echo "No update needed \n";
			}
		}
	}


}