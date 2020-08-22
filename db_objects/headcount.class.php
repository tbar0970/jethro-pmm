<?php
class Headcount
{
	public function getInitSQL($table_name=NULL)
	{
		return Array(
			'CREATE TABLE congregation_headcount (
					`date` DATE NOT NULL,
					`congregationid` INT(11) NOT NULL,
					`number` INT(11) NOT NULL,
					PRIMARY KEY (`date`, `congregationid`)
				) Engine=InnoDB;',
			 'CREATE TABLE person_group_headcount (
					`date` DATE NOT NULL,
					`person_groupid` INT(11) NOT NULL,
					`number` INT(11) NOT NULL,
					PRIMARY KEY (`date`, `person_groupid`)
				) Engine=InnoDB;'
		);
	}

	/**
	 *
	 * @return The SQL to run to create any database views used by this class
	 */
	public function getViewSQL()
	{
		return NULL;
	}

	public function getForeignKeys()
	{
		return Array(
			'congregation_headcount.congregationid' => 'congregation(id) ON DELETE CASCADE',
			'person_group_headcount.person_groupid' => '_person_group(id) ON DELETE CASCADE',
		);
	}

	private static function checkEntityType(&$entityType)
	{
		if ($entityType == 'c') $entityType = 'congregation';
		if ($entityType == 'g') $entityType = 'person_group';
		if (!in_array($entityType, Array('congregation', 'person_group'))) {
			trigger_error('Unknown entity type '.$entityType, E_USER_ERROR);
		}
	}
	public static function save($entitytype, $date, $entityid, $number)
	{
		self::checkEntityType($entitytype);
		$db = $GLOBALS['db'];
		if ((int)$number > 0) {
			$SQL = 'REPLACE INTO '.$entitytype.'_headcount
					(`date`, `'.$entitytype.'id`, `number`)
					VALUES ('.$db->quote($date).', '.$db->quote($entityid).', '.$db->quote($number).')';
		} else {
			$SQL = 'DELETE FROM '.$entitytype.'_headcount
					WHERE `date` = '.$db->quote($date).' AND '.$entitytype.'id = '.$db->quote($entityid);
		}
		$res = $db->exec($SQL);
		return TRUE;
	}

	public static function fetch($entitytype, $date, $entityid)
	{
		self::checkEntityType($entitytype);
		$db = $GLOBALS['db'];
		$SQL = 'SELECT number FROM '.$entitytype.'_headcount
				WHERE `date` = '.$db->quote($date).' AND '.$entitytype.'id = '.$db->quote($entityid);
		$res = $db->queryOne($SQL);
		return $res;
	}

	public static function fetchRange($entitytype, $entityid, $fromDate, $toDate)
	{
		self::checkEntityType($entitytype);
		$db = $GLOBALS['db'];
		$SQL = 'SELECT `date`, number FROM '.$entitytype.'_headcount
				WHERE (`date` BETWEEN '.$db->quote($fromDate).' AND '.$db->quote($toDate).')
				AND '.$entitytype.'id = '.$db->quote($entityid);
		$res = $db->queryAll($SQL, null, null, true);
		return $res;
	}

	public static function fetchAverage($entitytype, $entityid, $fromDate, $toDate)
	{
		self::checkEntityType($entitytype);
		$db = $GLOBALS['db'];
		$SQL = 'SELECT AVG(number) FROM '.$entitytype.'_headcount
				WHERE (`date` BETWEEN '.$db->quote($fromDate).' AND '.$db->quote($toDate).')
				AND '.$entitytype.'id = '.$db->quote($entityid);
		$res = $db->queryOne($SQL);
		return $res;

	}


}