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
            'CREATE TABLE congregation_category_headcount (
					`date` DATE NOT NULL,
					`congregationid` INT(11) NOT NULL,
                    `category` varchar(30) NOT NULL,
					`number` INT(11) NOT NULL,
					PRIMARY KEY (`date`, `congregationid`)
				) Engine=InnoDB;',
			 'CREATE TABLE person_group_headcount (
					`date` DATE NOT NULL,
					`person_groupid` INT(11) NOT NULL,
					`number` INT(11) NOT NULL,
					PRIMARY KEY (`date`, `person_groupid`)
				) Engine=InnoDB;',
          'CREATE TABLE person_group_category_headcount (
					`date` DATE NOT NULL,
					`person_groupid` INT(11) NOT NULL,
                    `category` varchar(30) NOT NULL,
					`number` INT(11) NOT NULL,
					PRIMARY KEY (`date`, `person_groupid`)
				) Engine=InnoDB;'
		);
	}
	
	public function getForeignKeys()
	{
		return Array(
			'congregation_headcount.congregationid' => 'congregation(id) ON DELETE CASCADE',
            'congregation_category_headcount.congregationid' => 'congregation(id) ON DELETE CASCADE',
			'person_group_category_headcount.person_groupid' => '_person_group(id) ON DELETE CASCADE',
		);
	}

	private function checkEntityType(&$entityType)
	{
		if ($entityType == 'c') $entityType = 'congregation';
		if ($entityType == 'g') $entityType = 'person_group';
		if (!in_array($entityType, Array('congregation', 'person_group'))) {
			trigger_error('Unknown entity type '.$entityType, E_USER_ERROR);
		}
	}
	public static function save($entitytype, $date, $entityid, $number, $category='')
	{
		self::checkEntityType($entitytype);
		$db = $GLOBALS['db'];
		if ((int)$number > 0) {
            if ($category == '') {
              $SQL = 'REPLACE INTO '.$entitytype.'_headcount
                      (`date`, `'.$entitytype.'id`, `number`)
					  VALUES ('.$db->quote($date).', '.$db->quote($entityid).', '.$db->quote($number).')';
            } else {
              $SQL = 'REPLACE INTO '.$entitytype.'_headcount
                      (`date`, `'.$entitytype.'id`, `category`, `number`)
					  VALUES ('.$db->quote($date).', '.$db->quote($entityid).', '.$db->quote($category) . ', '.$db->quote($number).')';
            }
		} else {
          if ($category == '') {
			$SQL = 'DELETE FROM '.$entitytype.'_headcount
					WHERE `date` = '.$db->quote($date).' AND '.$entitytype.'id = '.$db->quote($entityid);
          } else {
            $SQL = 'DELETE FROM '.$entitytype.'_headcount
					WHERE `date` = '.$db->quote($date).' AND '.$entitytype.'id = '.$db->quote($entityid) . ' AND category = ' . $db->quote($category);
          }
		}
		$res = $db->exec($SQL);
		return TRUE;
	}
	public static function fetch($entitytype, $date, $entityid, $category='')
	{
		self::checkEntityType($entitytype);
		$db = $GLOBALS['db'];
        if ($category == '') {
          $SQL = 'SELECT number FROM '.$entitytype.'_headcount
		  		  WHERE `date` = '.$db->quote($date).' AND '.$entitytype.'id = '.$db->quote($entityid);  
        } else {
          $SQL = 'SELECT number FROM '.$entitytype.'_headcount
		  		  WHERE `date` = '.$db->quote($date).' AND '.$entitytype.'id = '.$db->quote($entityid) . ' AND category = ' . $db->quote($category);
        }		
		$res = $db->queryOne($SQL);
		return $res;
	}

	public static function fetchRange($entitytype, $entityid, $fromDate, $toDate, $category='')
	{
		self::checkEntityType($entitytype);
		$db = $GLOBALS['db'];
        if ($category=='') {
		  $SQL = 'SELECT `date`, number FROM '.$entitytype.'_headcount
		  		  WHERE (`date` BETWEEN '.$db->quote($fromDate).' AND '.$db->quote($toDate).')
				  AND '.$entitytype.'id = '.$db->quote($entityid);
        } else {
          $SQL = 'SELECT `date`, number FROM '.$entitytype.'_headcount
		  		  WHERE (`date` BETWEEN '.$db->quote($fromDate).' AND '.$db->quote($toDate).')
				  AND '.$entitytype.'id = '.$db->quote($entityid) . '
                  AND category = '.$db->quote($category);
        }
		$res = $db->queryAll($SQL, null, null, true);
		return $res;
	}
	
	public static function fetchAverage($entitytype, $entityid, $fromDate, $toDate, $category='')
	{
		self::checkEntityType($entitytype);
		$db = $GLOBALS['db'];
        if ($category=='') {
		  $SQL = 'SELECT AVG(number) FROM '.$entitytype.'_headcount
				  WHERE (`date` BETWEEN '.$db->quote($fromDate).' AND '.$db->quote($toDate).')
				  AND '.$entitytype.'id = '.$db->quote($entityid);
        } else {
          $SQL = 'SELECT AVG(number) FROM '.$entitytype.'_headcount
				  WHERE (`date` BETWEEN '.$db->quote($fromDate).' AND '.$db->quote($toDate).')
				  AND '.$entitytype.'id = '.$db->quote($entityid) . '
                  AND category = '.$db->quote($category);
        }
		$res = $db->queryOne($SQL);
		return $res;
		
	}


}