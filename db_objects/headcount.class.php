<?php
class Headcount
{
	public static function getInitSQL()
	{
		return Array(
			'CREATE TABLE congregation_headcount (
					`date` DATE NOT NULL,
					`congregationid` INT(11) NOT NULL,
					`number` INT(11) NOT NULL,
					PRIMARY KEY (`date`, `congregationid`),
					CONSTRAINT FOREIGN KEY (`congregationid`) REFERENCES `congregation` (`id`)
				) Engine=InnoDB;',
			 'CREATE TABLE person_group_headcount (
					`date` DATE NOT NULL,
					`person_groupid` INT(11) NOT NULL,
					`number` INT(11) NOT NULL,
					PRIMARY KEY (`date`, `person_groupid`),
					CONSTRAINT FOREIGN KEY (`person_groupid`) REFERENCES `_person_group` (`id`)
				) Engine=InnoDB;'
		);
	}

	private function checkEntityType($entityType)
	{
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
		check_db_result($res);
		return TRUE;
	}

	public static function fetch($entitytype, $date, $entityid)
	{
		self::checkEntityType($entitytype);
		$db = $GLOBALS['db'];
		$SQL = 'SELECT number FROM '.$entitytype.'_headcount
				WHERE `date` = '.$db->quote($date).' AND '.$entitytype.'id = '.$db->quote($entityid);
		$res = $db->queryOne($SQL);
		check_db_result($res);
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
		check_db_result($res);
		return $res;
	}


}