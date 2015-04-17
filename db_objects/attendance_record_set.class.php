<?php

// This is not a standard DB object but performs similarly
class Attendance_Record_Set
{

	var $date = NULL;
	var $congregationid = NULL;
	var $groupid = NULL;
	var $age_bracket = NULL;
	var $_attendance_records = Array();
	
	const LIST_ORDER_DEFAULT = 'status ASC, family_name ASC, familyid, age_bracket ASC, gender DESC';

//--        CREATING, LOADING AND SAVING        --//

	function Attendance_Record_Set($date=NULL, $age_bracket=NULL, $status=NULL, $congregationid=NULL, $groupid=NULL)
	{
		if ($date && ($congregationid || $groupid)) {
			$this->load($date, $age_bracket, $status, $congregationid, $groupid);
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
			) ENGINE=InnoDB ;
		";
	}
	
	public function getForeignKeys()
	{
		return Array();
	}

	function load($date, $age_bracket, $status, $congregationid=0, $groupid=0)
	{
		$this->date = $date;
		$this->congregationid = $congregationid;
		$this->groupid = $groupid;
		$this->age_bracket = $age_bracket;
		$this->status = $status;
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
		if ($this->status) {
			$sql .= '
				AND p.status = '.$db->quote($this->status);
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
		if (strlen($this->age_bracket) || ($this->status !== NULL)) {
			$sql .= '
					AND (personid IN (SELECT id FROM person WHERE 1=1 ';
			if (strlen($this->age_bracket)) {
				' AND age_bracket = '.$db->quote($this->age_bracket).' ';
			}
			if (!is_null($this->status)) {
				' AND status = '.$db->quote($this->status);
			}
			$sql .= ' )) ';
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
		$order = defined('ATTENDANCE_LIST_ORDER') ? constant('ATTENDANCE_LIST_ORDER') : self::LIST_ORDER_DEFAULT;
		if ($this->congregationid) {
			$conds = Array('congregationid' => $this->congregationid, '!status' => 'archived');
			if (strlen($this->age_bracket)) {
				$conds['age_bracket'] = $this->age_bracket;
			}
			$members = $GLOBALS['system']->getDBObjectData('person', $conds, 'AND', $order);
		} else {
			$group =& $GLOBALS['system']->getDBObject('person_group', $this->groupid);
			$members =& $group->getMembers(FALSE, $order);
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
				<td><?php echo ents($details['last_name']); ?></td>
				<td><?php echo ents($details['first_name']); ?></td>
			<?php 
			if (!SizeDetector::isNarrow()) {
				?>
				<td>
					<?php
					if ($this->groupid) {
						echo ents($details['membership_status']);
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
		return count($members);
	}

	function processForm($prefix)
	{
		$this->_attendance_records = Array();
		if (isset($_POST['attendances']) && isset($_POST['attendances'][$prefix])) {
			foreach ($_POST['attendances'][$prefix] as $personid => $present) {
				if (!empty($present) && ($present != 'unknown')) {
					$this->_attendance_records[$personid] = (int)($present == 'present');
				}
			}
		}
	}

	public function getStats()
	{
		$db = $GLOBALS['db'];
		$groupingField = ((int)$this->congregationid) ? 'p.status' : 'pgm.membership_status';
		$SQL = 'SELECT present, '.$groupingField.' AS status, count(p.id) AS total
				FROM attendance_record ar
				JOIN person p ON ar.personid = p.id
				LEFT JOIN person_group pg ON pg.id = ar.groupid
				LEFT JOIN person_group_membership pgm ON pgm.personid = p.id AND pgm.groupid = pg.id
				LEFT JOIN person_group_membership_status pgms ON pgms.id = pgm.membership_status
				WHERE date = '.$db->quote($this->date).'
				AND ar.groupid = '.$db->quote($this->groupid).'
				';
		if ($this->congregationid) {
			$SQL .= '
				AND p.congregationid = '.$db->quote($this->congregationid);
		}
		$SQL .= '
				GROUP BY present, '.$groupingField.'
				ORDER BY present, '.$groupingField;
		$res = $db->queryAll($SQL);
		check_db_result($res);
		$totals = Array(0 => 0, 1 => 0);
		$breakdowns = Array(0 => Array(), 1 => Array());
		$dummy = new Person();
		foreach ($res as $r) {
			if ($this->congregationid) $r['status'] = $dummy->getFormattedValue('status', $r['status']);
			$totals[$r['present']] += $r['total'];
			$breakdowns[$r['present']][] = $r;
		}
		return Array($totals, $breakdowns);
	}

	public function printStats()
	{
		list($totals, $breakdowns) = $this->getStats();

		?>
		<table class="table valign-middle attendance-stats table-bordered" style="width: 40ex">
			<tr class="headcount">
				<th>Total Headcount</th>
				<td colspan="3">
					<b>
					<?php
					if ((int)$this->congregationid) {
						$headcount = Congregation_Headcount::fetch($this->date, $this->congregationid);
					} else {
						$headcount = Person_Group_Headcount::fetch($this->date, $this->groupid);
					}
					echo $headcount;
					?>
					</b>
				</td>
			</tr>
		<?php
		foreach (Array(1 => 'Present', 0 => 'Absent') as $present => $label) {
			?>
			<tr class="<?php echo strtolower($label); ?>">
				<th rowspan="<?php echo count($breakdowns[$present]); ?>">Marked <?php echo $label; ?></th>
				<td rowspan="<?php echo count($breakdowns[$present]); ?>">
					<b><?php echo $totals[$present]; ?></b>
				</td>
			<?php
			if (!empty($breakdowns[$present])) {
				?>
				<td><?php echo $breakdowns[$present][0]['status']; ?></td>
				<td><?php echo $breakdowns[$present][0]['total']; ?></td>
				<?php
			} else {
				?>
				<td>&nbsp;</td>
				<td>&nbsp;</td>
				<?php
			}
			?>
			</tr>
			<?php
			for ($i = 1; $i < count($breakdowns[$present]); $i++) {
				?>
				<tr class="<?php echo strtolower($label); ?>">
					<td><?php echo $breakdowns[$present][$i]['status']; ?></td>
					<td><?php echo $breakdowns[$present][$i]['total']; ?></td>
				</tr>
				<?php
			}
		}
		?>
			<tr class="extras">
				<th>Extras</th>
				<td colspan="3"><b><?php echo ($headcount - $totals[1]); ?></b></td>
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
		if ($age_bracket !== '') {
			$SQL .= '
				AND p.age_bracket = '.$GLOBALS['db']->quote($age_bracket);
		}
		if ($congregationids) {
			 $SQL .= '
				 AND p.congregationid IN ('.implode(', ', array_map(Array($GLOBALS['db'], 'quote'), $congregationids)).') ';
		}
		$order = defined('ATTENDANCE_LIST_ORDER') ? constant('ATTENDANCE_LIST_ORDER') : self::LIST_ORDER_DEFAULT;
		$order = preg_replace("/(^|[^.])status($| |,)/", '\\1p.status\\2', $order);
		$SQL .= '
				ORDER BY '.$order;
		$dates = Array();
		$attendances = Array();
		$totals = Array();
		$res = $GLOBALS['db']->query($SQL);
		check_db_result($res);
		while ($row = $res->fetchRow()) {
			$dates[$row['date']] = 1;
			foreach (Array('last_name', 'first_name', 'membership_status', 'status') as $f) {
				if (array_key_exists($f, $row)) $attendances[$row['id']][$f] = $row[$f];
			}
			$attendances[$row['id']][$row['date']] = $row['present'];
			if (!isset($totals[$row['date']]) || !isset($totals[$row['date']][$row['present']])) {
				$totals[$row['date']][$row['present']] = 0;
			}
			$totals[$row['date']][$row['present']]++;
		}
		$dates = array_keys($dates);
		sort($dates);
		return Array($dates, $attendances, $totals);
	}





		


}//end class
