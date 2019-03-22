<?php
include_once 'include/db_object.class.php';
class roster_role_assignment extends db_object
{
	// NB This class only exists for the following SQL
	// It has no ID
	function getInitSql($table_name = NULL)
	{
		return 'create table roster_role_assignment (
					assignment_date	date not null,
					roster_role_id	int(11) not null,
					personid		int(11) not null,
					rank            int unsigned not null default 0,
					assigner		int(11) not null,
					assignedon		timestamp,
					primary key (roster_role_id, assignment_date, personid),
					constraint `rra_assiger` foreign key (assigner) references _person(id),
					constraint `rra_personid` foreign key (personid) references _person(id) ON DELETE CASCADE,
					constraint `rra_roster_role_id` foreign key (roster_role_id) references roster_role(id)
				) ENGINE=InnoDB ;';
	}

	static function getAssignmentsForDateAndCong($date, $congid)
	{
		$SQL = 'SELECT personid, GROUP_CONCAT(rr.title SEPARATOR ", ") as role
				FROM roster_role_assignment rra
					JOIN roster_role rr ON rr.id = rra.roster_role_id
				WHERE assignment_date = '.$GLOBALS['db']->quote($date).'
					AND ((rr.congregationid = '.(int)$congid.') OR (rr.congregationid IS NULL))
				GROUP BY personid';
		return $GLOBALS['db']->queryAll($SQL, NULL, NULL, TRUE);
	}

	static function getUpcomingAssignments($personid, $timeframe='4 weeks')
	{
		$end_date = date('Y-m-d', strtotime('+'.$timeframe));
		$sql = 'SELECT rra.assignment_date, COALESCE(c.name, "") as cong, rr.title, rr.id, c.meeting_time
			FROM roster_role_assignment rra
				JOIN roster_role rr ON rra.roster_role_id = rr.id
				LEFT OUTER JOIN congregation c ON rr.congregationid = c.id
			WHERE rra.personid = '.$GLOBALS['db']->quote($personid);
		if (!empty($timeframe)) {
			$sql .= '
			AND rra.assignment_date BETWEEN  DATE(NOW()) AND '.$GLOBALS['db']->quote($end_date);
		} else {
			$sql .= '
			AND rra.assignment_date >= DATE(NOW())';
		}

		$sql .= '
			ORDER BY rra.assignment_date ASC, c.meeting_time';
		$res = $GLOBALS['db']->queryAll($sql, NULL, NULL, true, false, true);
		return $res;
	}

	static function hasAssignments($personid)
	{
		$SQL = 'SELECT count(*) FROM roster_role_assignment
				WHERE personid = '.(int)$personid;
		$res = $GLOBALS['db']->queryOne($SQL);
	}

}
?>
