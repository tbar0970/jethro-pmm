<?php

// This is not a standard DB object but performs similarly
class Attendance_Record_Set
{

	var $date = NULL;
	var $congregationid = NULL;
	var $groupid = NULL;
	var $age_bracket = NULL;
	var $_attendance_records = Array();

//--        CREATING, LOADING AND SAVING        --//

	function Attendance_Record_Set($date=NULL, $age_bracket=NULL, $congregationid=NULL, $groupid=NULL)
	{
		if ($date && ($congregationid || $groupid)) {
			$this->load($date, $age_bracket, $congregationid, $groupid);
		}
	}

	function create()
	{
	}


	function getInitSQL()
	{
		return "
			CREATE TABLE `attendance_record` (
			  `date` date NOT NULL default '0000-00-00',
			  `personid` int(11) NOT NULL default '0',
			  `groupid` int(11) NOT NULL default '0',
			  `present` tinyint(1) unsigned NOT NULL default '0',
			  PRIMARY KEY  (`date`,`personid`,`groupid`)
			) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;
		";
	}

	function load($date, $age_bracket, $congregationid=0, $groupid=0)
	{
		$this->date = $date;
		$this->congregationid = $congregationid;
		$this->groupid = $groupid;
		$this->age_bracket = $age_bracket;
		$db =& $GLOBALS['db'];
		$sql = 'SELECT personid, present 
					FROM attendance_record ar
					JOIN person p ON ar.personid = p.id
				WHERE date = '.$db->quote($date).' 
					AND groupid = '.(int)$this->groupid;
		if ($this->congregationid) {
			$sql .= '
				AND p.congregationid = '.$db->quote($this->congregationid);
		}
		if (strlen($this->age_bracket)) {
			$sql .= '
				AND p.age_bracket = '.$db->quote($this->age_bracket);
		}
		$this->_attendance_records = $db->queryAll($sql, null, null, true);
		check_db_result($this->_attendance_records);
	}


	function save()
	{
		if (empty($this->date)) {
			trigger_error('Cannot save attendance record set with no date', E_USER_WARNING);
			return;
		}
		$db =& $GLOBALS['db'];
		$GLOBALS['system']->doTransaction('begin');
			$this->delete();
			$stmt = $db->prepare('INSERT INTO attendance_record (date, groupid, personid, present) VALUES ('.$db->quote($this->date).', '.(int)$this->groupid.', ?, ?)', Array('integer', 'integer', 'integer'), MDB2_PREPARE_MANIP);
			check_db_result($stmt);
			foreach ($this->_attendance_records as $personid => $present) {
				$res = $stmt->execute(Array($personid, $present));
				check_db_result($res);
			}
		$GLOBALS['system']->doTransaction('commit');
	}


	function delete()
	{
		$db =& $GLOBALS['db'];
		$sql = 'DELETE FROM attendance_record
				WHERE date = '.$db->quote($this->date).'
					AND (groupid = '.$db->quote((int)$this->groupid).')';
		if ($this->congregationid) {
			$sql .= '
					AND (personid IN (SELECT id FROM person WHERE congregationid = '.$db->quote($this->congregationid).') ';
			if (!empty($this->_attendance_records)) {
				$our_personids = array_map(Array($GLOBALS['db'], 'quote'), array_keys($this->_attendance_records));
				$sql .= ' OR personid IN ('.implode(', ', $our_personids).')';
			}
			$sql .= ')';
		}
		if (strlen($this->age_bracket)) {
			$sql .= '
					AND (personid IN (SELECT id FROM person WHERE age_bracket = '.$db->quote($this->age_bracket).')) ';
		}
		$res = $db->query($sql);
		check_db_result($res);
	}



//--        INTERFACE PAINTING AND PROCESSING        --//


	function printSummary()
	{
	}

	function printForm($prefix=0)
	{
		require_once 'include/size_detector.class.php';
		if ($this->congregationid) {
			$order = defined('ATTENDANCE_LIST_ORDER') ? constant('ATTENDANCE_LIST_ORDER') : 'status ASC, last_name ASC, familyid, age_bracket ASC, gender DESC';
			$conds = Array('congregationid' => $this->congregationid, '!status' => 'archived');
			if (strlen($this->age_bracket)) {
				$conds['age_bracket'] = $this->age_bracket;
			}
			$members = $GLOBALS['system']->getDBObjectData('person', $conds, 'AND', $order);
		} else {
			$group =& $GLOBALS['system']->getDBObject('person_group', $this->groupid);
			$members =& $group->getMembers();
			if (strlen($this->age_bracket)) {
				// Not the most efficient but it's a problem when it's a problem
				foreach ($members as $i => $person) {
					if ($person['age_bracket'] != $this->age_bracket) unset($members[$i]);
				}
			}
		}
		$GLOBALS['system']->includeDBClass('person');
		$dummy = new Person();
		?>
		<table class="table table-auto-width table-condensed valign-middle">
		<?php
		$is_first = TRUE;
		foreach ($members as $personid => $details) {
			$v = isset($this->_attendance_records[$personid]) 
					? ($this->_attendance_records[$personid] ? 'present' : 'absent') 
					: (empty($this->_attendance_records) ? '' : 'unknown');
			$dummy->populate($personid, $details);
			?>
			<tr>
			<?php 
			if (!SizeDetector::isNarrow()) {
				?>
				<td><?php echo $personid; ?></td>
				<?php
			}
			?>
				<td><?php echo htmlentities($details['last_name']); ?></td>
				<td><?php echo htmlentities($details['first_name']); ?></td>
			<?php 
			if (!SizeDetector::isNarrow()) {
				?>
				<td>
					<?php
					if ($this->groupid) {
						echo htmlentities($details['membership_status']);
					} else {
						$dummy->printFieldValue('status'); 
					}
					?>
				</td>
				<?php
			}
			?>
				<td>
					<?php print_widget(
							'attendances['.$prefix.']['.$personid.']',
							Array(
								'options' => Array('unknown' => '?', 'present' => 'Present', 'absent' => 'Absent'),
								'type' => 'select',
								'style' => 'colour-buttons',
								'class' => $is_first ? 'autofocus' : '',
							),
							$v
					); ?>
				</td>
				<td class="action-cell narrow">
					<a class="med-popup" tabindex="-1" href="?view=persons&personid=<?php echo $personid; ?>"><i class="icon-user"></i>View</a> &nbsp;
					<a class="med-popup" tabindex="-1" href="?view=_edit_person&personid=<?php echo $personid; ?>"><i class="icon-wrench"></i>Edit</a> &nbsp;
					<a class="med-popup" tabindex="-1" href="?view=_add_note_to_person&personid=<?php echo $personid; ?>"><i class="icon-pencil"></i>Add Note</a>
				</td>
			</tr>
			<?php
			$is_first = FALSE;
		}
		?>
		</table>
		<?php
	}

	function processForm($prefix)
	{
		$this->_attendance_records = Array();
		if (isset($_POST['attendances']) && isset($_POST['attendances'][$prefix])) {
			foreach ($_POST['attendances'][$prefix] as $personid => $present) {
				if ($present != 'unknown') {
					$this->_attendance_records[$personid] = (int)($present == 'present');
				}
			}
		}
	}

	function printStats()
	{
		$freqs = array_count_values($this->_attendance_records);
		$db =& $GLOBALS['db'];
		$sql = 'SELECT status, COUNT(id)
				FROM person
				WHERE id IN
					(SELECT personid 
					FROM attendance_record 
					WHERE date = '.$db->quote($this->date).' 
						AND present = __PRESENT__
						AND groupid = '.$db->quote($this->groupid).'
					)';
		if ($this->congregationid) {
			$sql .= '
				AND congregationid = '.$db->quote($this->congregationid);
		}
		$sql .= '
				GROUP BY status';

		$present_sql = str_replace('__PRESENT__', '1', $sql);
		$present_breakdown = $db->queryAll($present_sql, null, null, true);
		check_db_result($present_breakdown);

		$absent_sql = str_replace('__PRESENT__', '0', $sql);
		$absent_breakdown = $db->queryAll($absent_sql, null, null, true);
		check_db_result($absent_breakdown);

		$GLOBALS['system']->includeDBClass('person');
		$dummy = new Person();

		?>
		<table class="table table-bordered table-auto-width">
			<tr>
				<th>Present</th>
				<td>
					<?php echo array_get($freqs, 1, 0); ?> persons
					<table class="table table-striped table-bordered" style="margin: 3px">
					<?php
					foreach ($present_breakdown as $status => $number) {
						$dummy->setValue('status', $status);
						?>
						<tr>
							<th><?php $dummy->printFieldValue('status'); ?></th>
							<td><?php echo $number; ?></td>
						</tr>
						<?php
					}
					?>
					</table>
				</td>
			</tr>
			<tr>
				<th>Absent</th>
				<td>
					<?php echo array_get($freqs, 0, 0); ?> persons
					<table class="table table-striped table-bordered" style="margin: 3px">
					<?php
					foreach ($absent_breakdown as $status => $number) {
						$dummy->setValue('status', $status);
						?>
						<tr>
							<th><?php $dummy->printFieldValue('status'); ?></th>
							<td><?php echo $number; ?></td>
						</tr>
						<?php
					}
					?>
					</table>
				</td>
			</tr>
		</table>
		<?php
	}

	function getCongregationalAttendanceStats($start_date, $end_date, $congregations=Array())
	{
		$db =& $GLOBALS['db'];

		$sql = '
				SELECT status, AVG(percent_present) as avg_attendance FROM
				(
					SELECT ar.personid, p.status as status, CONCAT(ROUND(SUM(ar.present) * 100 / COUNT(ar.date)), '.$db->quote('%').') as percent_present
					FROM 
						person p 
						JOIN attendance_record ar ON p.id = ar.personid
					WHERE 
						ar.date BETWEEN '.$db->quote($start_date).' AND '.$db->quote($end_date).'
						AND ar.groupid = 0
				';
		if (!empty($congregations)) {
			$int_congs = Array();
			foreach ($congregations as $congid) {
				$int_congs[] = (int)$congid;
			}
			$sql .= '	AND p.congregationid IN ('.implode(',', $int_congs).')';
		}
		$sql .=	'
					GROUP BY ar.personid, p.status
				) indiv
				GROUP BY status';
		$res = $db->queryAll($sql);
		check_db_result($res);

		$stats = Array();
		foreach ($res as $row) {
			$stats[$row['status']] = round($row['avg_attendance']);
		}
		return $stats;
	}

	function getPersonsByAttendance($target_percent, $cutoff_ts, $congregation=0, $operator='<', $groupid=0)
	{
		if ($operator <> '<') $operator = '>';
		$db =& $GLOBALS['db'];
		$sql = 'SELECT ar.personid, p.*, CONCAT(ROUND(SUM(ar.present) * 100 / COUNT(ar.date)), '.$db->quote('%').') as percent_present
				FROM 
					person p 
					JOIN attendance_record ar ON p.id = ar.personid
					JOIN family f ON p.familyid = f.id
				WHERE UNIX_TIMESTAMP(ar.date) >= '.$db->quote((int)$cutoff_ts).'
				AND ar.groupid = '.(int)$groupid.'
				AND p.status <> '.$db->quote('archived');
		if ($congregation) {
			$sql .= '
				AND p.congregationid = '.$db->quote((int)$congregation);
		}
		$order = defined('ATTENDANCE_LIST_ORDER') ? constant('ATTENDANCE_LIST_ORDER') : 'status ASC, last_name ASC, age_bracket ASC, gender DESC';
		$sql .= '
				GROUP BY p.id, p.first_name, p.last_name, p.congregationid
				HAVING percent_present '.$operator.' '.$db->quote((int)$target_percent).'
				ORDER BY '.$order;
		$persons_res = $db->queryAll($sql, null, null, true);
		check_db_result($persons_res);

		if ($GLOBALS['user_system']->havePerm(PERM_VIEWNOTE)) {

			$notes_res = Array();
			if (!empty($persons_res)) {
				$sql = 'SELECT pn.personid, GROUP_CONCAT(an.subject SEPARATOR '.$db->quote(', ').') 
						FROM abstract_note an JOIN person_note pn ON an.id = pn.id
						WHERE an.status = '.$db->quote('pending').'
							AND an.action_date <= NOW()
							AND pn.personid IN ('.implode(',', array_map(Array($db, 'quote'), array_keys($persons_res))).')
						GROUP BY pn.personid';
				$notes_res = $db->queryAll($sql, null, null, true);
				check_db_result($notes_res);
			}

			foreach ($persons_res as $personid => $result) {
				$persons_res[$personid]['outstanding_notes'] = array_get($notes_res, $personid, '');
			}
		}

		return $persons_res;
	}

	function getAttendances($congregationids, $groupid, $age_bracket, $start_date, $end_date)
	{
		$SQL = 'SELECT p.id, p.last_name, p.first_name, '.($groupid ? 'pgms.label AS membership_status' : 'p.status').', ar.date, ar.present
				FROM person p
				JOIN family f ON p.familyid = f.id
				JOIN attendance_record ar ON ar.personid = p.id ';
		if ($congregationids) {
			$SQL .= 'AND ar.groupid = 0';
		}
		if ($groupid) {
			$SQL .= 'AND ar.groupid = '.(int)$groupid;
			$SQL .= '
				LEFT JOIN person_group_membership pgm ON pgm.personid = p.id AND pgm.groupid = ar.groupid
				LEFT JOIN person_group_membership_status pgms ON pgms.id = pgm.membership_status';
		}
		$SQL .= '
				WHERE ar.date BETWEEN '.$GLOBALS['db']->quote($start_date).' AND '.$GLOBALS['db']->quote($end_date);
		if ($age_bracket) {
			$SQL .= '
				AND p.age_bracket = '.$GLOBALS['db']->quote($age_bracket);
		}
		if ($congregationids) {
			 $SQL .= 'AND p.congregationid IN ('.implode(', ', array_map(Array($GLOBALS['db'], 'quote'), $congregationids)).') ';
		}
		$order = defined('ATTENDANCE_LIST_ORDER') ? constant('ATTENDANCE_LIST_ORDER') : 'status ASC, last_name ASC, age_bracket ASC, gender DESC';
		$SQL .= '
				ORDER BY '.$order;
		$dates = Array();
		$attendances = Array();
		$res = $GLOBALS['db']->query($SQL);
		check_db_result($res);
		while ($row = $res->fetchRow()) {
			$dates[$row['date']] = 1;
			foreach (Array('last_name', 'first_name', 'membership_status', 'status') as $f) {
				if (isset($row[$f])) $attendances[$row['id']][$f] = $row[$f];
			}
			$attendances[$row['id']][$row['date']] = $row['present'];
		}
		$dates = array_keys($dates);
		sort($dates);
		return Array($dates, $attendances);
	}





		


}//end class
