<?php
include_once 'include/db_object.class.php';
class roster_role_assignment extends db_object
{
	// NB This class only exists for the following SQL
	// It has no ID
	function getInitSql($table_name = NULL)
	{
		return 'create table roster_role_assignment (
					assignment_date		date not null,
					roster_role_id		int(11) not null,
					personid			int(11) not null,
					pending_personid	int(11) not null,
					`rank`				int unsigned not null default 0,
					assigner			int(11) not null,
					assignedon			timestamp NOT NULL default CURRENT_TIMESTAMP,
					primary key (roster_role_id, assignment_date, personid),
					constraint `rra_assiger` foreign key (assigner) references _person(id),
					constraint `rra_personid` foreign key (personid) references _person(id) ON DELETE CASCADE,
					constraint `rra_pending_personid` foreign key (pending_personid) references _person(id) ON DELETE SET NULL,
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
		$sql = 'SELECT rra.assignment_date, COALESCE(c.name, "") as cong, rr.title, rr.id, c.meeting_time, rra.assignedon
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

    /**
     * Record a swap request, to be later accepted by 'swapToCurrentUser'
     *
     * Checks that the current user is rostered on the given role and date
     * and then records a request for a new person to take it over
     *
     * @param str $roleid    The role to be swapped
     * @param str $date      The date to swap for
     * @param str $personid  The requested person to be rostered instead
     */
	static function recordSwapRequest($roleid, $date, $personid)
	{
		$currentUserId = $GLOBALS['user_system']->getCurrentPerson('id');
		$where = 'WHERE rra.`roster_role_id` = '.(int)$roleid.' AND rra.`assignment_date` = '.$GLOBALS['db']->quote($date).' AND rra.`personid` = '.$currentUserId;
		$SQL = 'SELECT 1 FROM roster_role_assignment rra '.$where;
		if ($GLOBALS['db']->queryRow($SQL)) {
			$SQL = 'UPDATE roster_role_assignment rra
					SET rra.`pending_personid` = '.(int)$personid.' '.$where;
			$GLOBALS['db']->query($SQL);
			return true;
		}
	}

    /**
     * Accept the swap requested by 'recordSwapRequest'.
     *
     * Checks that the swap was requsted for the given role and date
     * to the current user by the current rostered person
     * and then updates the assigned person and clears the request
     *
     * @param str $roleid              The role to be swapped
     * @param str $date                The date to swap for
     * @param str $originalAssigneeID  The current person rostered on
     */
	static function swapToCurrentUser($roleid, $date, $originalAssigneeID) {
		$currentUserId = $GLOBALS['user_system']->getCurrentPerson('id');
		$where = 'WHERE rra.`roster_role_id` = '.(int)$roleid.' AND rra.`assignment_date` = '.$GLOBALS['db']->quote($date).' AND rra.`personid` = '.(int)$originalAssigneeID.' AND rra.`pending_personid` = '.$currentUserId;
		$SQL = 'SELECT 1 FROM roster_role_assignment rra '.$where;
		if ($GLOBALS['db']->queryRow($SQL)) {
			$SQL = 'UPDATE roster_role_assignment rra
					SET rra.`personid` = '.$currentUserId.',
						rra.`pending_personid` = NULL '.$where;
			$GLOBALS['db']->query($SQL);
			return true;
		}
	}

}