<?php
include_once 'include/db_object.class.php';
class Person_Unavailable extends db_object
{

	protected static function _getFields()
	{
		$fields = Array(
			'from_date' => Array(
									'type'			=> 'date',
									'allow_empty'	=> FALSE,
									'label' => 'First date unavailable',
								   ),
			'to_date' => Array(
									'type'			=> 'date',
									'allow_empty'	=> FALSE,
									'label' => 'Last dDate unavailable from',
								   ),
		);
		return $fields;
	}

	public function isNotAvailable($personid, $date, $groupid=NULL)
	{
		//
		// The person is unavailable on this date if :-
		// - they are marked as unavailable
		// - or they are marked as 'absent' from the group on that date
		// - unless they are marked as present on that date

		$present = -1;  // 1=present 0=absent
		if (! is_null($groupid)) {
			$sql = 'SELECT present '.
  				   ' FROM attendance_record '.
				   ' WHERE date = '.$GLOBALS['db']->quote($date).
				   ' AND groupid = '.(int)$groupid.
				   ' AND personid = '.(int)$personid;
			$res = $GLOBALS['db']->query($sql);
			while ($row = $res->fetch()) {
				$present = $row['present'];
			}
		}

		$unavailable = false;
		if ($present <> 1) {
			// Have not been marked as explicitly present - could they be unavailable?

			if ($present == 0) {
				// Explicitly marked as absent so no need to check further
				$unavailable = true;
			} else {
  			     $sql = 'SELECT personid '.
  				        ' FROM person_unavailable '.
 				        ' WHERE personid = '.(int)$personid.
				        ' AND from_date <= '.$GLOBALS['db']->quote($date).
				        ' AND to_date >= '.$GLOBALS['db']->quote($date);
			     $unavailable = (boolean)$GLOBALS['db']->queryOne($sql);
			}
		}

		return $unavailable;
	}

	public function isAvailable($personid, $date, $groupid=NULL)
	{
		return (! $this->isNotAvailable($personid, $date, $groupid));
	}

	static function getDates($personid, $earliestdate=NULL)
	{
		if (is_null($earliestdate)) {
			$from_date = date('Y-m-d');
		} else {
			$from_date = $earliestdate;
		}

		$db =& $GLOBALS['db'];
		$sql = 'SELECT id, personid, from_date, to_date '.
			   ' FROM person_unavailable '.
			   ' WHERE personid = '.(int)$personid.
			   ' AND to_date >= '.$GLOBALS['db']->quote($from_date).
			   ' ORDER BY from_date';
		$res = $db->queryAll($sql, null, null, true, true);
		return $res;
	}

	static function addDates($personid, $from, $to)
	{
		$db =& $GLOBALS['db'];

		if (!$GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
			trigger_error("You do not have permission to add dates");
			return FALSE;
		}

		$sql = 'INSERT '.
			   ' INTO person_unavailable (personid, from_date, to_date) '.
			   ' VALUES ('.$db->quote((int)$personid).', '.$db->quote($from).', '.$db->quote($to).')'.
			   ' ON DUPLICATE KEY UPDATE to_date = '.$db->quote($to);
		$res = $db->query($sql);
		return TRUE;
	}

	static function removeDates($personid, $from)
	{
		if (!$GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
			trigger_error("You do not have permission to modify members");
			return FALSE;
		}
		$db =& $GLOBALS['db'];
		$sql = 'DELETE FROM person_unavailable '.
		      ' WHERE personid = '.(int)$personid.
		      ' AND from_date = '.$db->quote($from);
		$res = $db->query($sql);
		return TRUE;
	}

}
