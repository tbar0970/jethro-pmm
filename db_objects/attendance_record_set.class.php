<?php

// This is not a standard DB object but performs similarly
class Attendance_Record_Set
{

	public $date = NULL;
	public $congregationid = NULL;
	public $groupid = 0;
	public $age_bracket = NULL;
	public $show_photos = FALSE;
	private $_persons = NULL;
	private $_attendance_records = Array();
	private $_cohort_object = NULL;
	
	const LIST_ORDER_DEFAULT = 'status ASC, family_name ASC, familyid, age_bracket ASC, gender DESC';

//--        CREATING, LOADING AND SAVING        --//

	function Attendance_Record_Set($date=NULL, $cohort=NULL, $age_bracket=NULL, $status=NULL)
	{
		if ($date && $cohort) {
			$this->load($date, $cohort, $age_bracket, $status);
		}
	}
	
	private function _getCohortObject()
	{
		if (!$this->_cohort_object) {
			if ($this->groupid) {
				$this->_cohort_object = $GLOBALS['system']->getDBObject('person_group', $this->groupid);
			} else if ($this->congregationid) {
				$this->_cohort_object = $GLOBALS['system']->getDBObject('congregation', $this->congregationid);
			}			
		}
		return $this->_cohort_object;
	}
	
	public function acquireLock()
	{
		$obj = $this->_getCohortObject();
		if (!$obj) {
			trigger_error("Could not get cohort object for lock");
			return FALSE;
		}
		return $obj->acquireLock('attendance-'.$this->date);
	}
	
	public function haveLock()
	{
		$obj = $this->_getCohortObject();
		if (!$obj) {
			trigger_error("Could not get cohort object for lock");
			return FALSE;
		}
		return $obj->haveLock('attendance-'.$this->date);	
	}
	
	public function releaseLock()
	{
		$obj = $this->_getCohortObject();
		if (!$obj) {
			trigger_error("Could not get cohort object for lock");
			return FALSE;
		}
		return $obj->releaseLock('attendance-'.$this->date);	
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

	function load($date, $cohort, $age_bracket, $status)
	{
		$this->date = $date;
		list($cohortType, $cohortID) = explode('-', $cohort);
		switch ($cohortType) {
			case 'c': $this->congregationid = $cohortID; break;
			case 'g': $this->groupid = $cohortID; break;
			default: trigger_error("Invalid cohort $cohort"); return;
		}
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

		$order = defined('ATTENDANCE_LIST_ORDER') ? constant('ATTENDANCE_LIST_ORDER') : self::LIST_ORDER_DEFAULT;
		if ($this->congregationid) {
			$conds = Array('congregationid' => $this->congregationid, '!status' => 'archived');
			if (strlen($this->age_bracket)) {
				$conds['age_bracket'] = $this->age_bracket;
			}
			$this->_persons = $GLOBALS['system']->getDBObjectData('person', $conds, 'AND', $order);
		} else {
			$group =& $GLOBALS['system']->getDBObject('person_group', $this->groupid);
			$this->_persons = $group->getMembers(FALSE, $order);
			if (strlen($this->age_bracket)) {
				// Not the most efficient but it's a problem when it's a problem
				foreach ($this->_persons as $i => $person) {
					if ($person['age_bracket'] != $this->age_bracket) unset($this->_persons[$i]);
				}
			}
		}

	}


	function save()
	{
		if (empty($this->date)) {
			trigger_error('Cannot save attendance record set with no date', E_USER_WARNING);
			return;
		}
		if (!$this->haveLock()) {
			trigger_error("Cannot save attendance record set - we do not have the lock");
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
		$sql = 'DELETE ar 
				FROM attendance_record ar
				JOIN person p ON ar.personid = p.id
				WHERE date = '.$db->quote($this->date).'
					AND (ar.groupid = '.$db->quote((int)$this->groupid).')';
		if ($this->congregationid) {
			$sql .= '
					AND ((congregationid = '.$db->quote($this->congregationid).') ';
			if (!empty($this->_attendance_records)) {
				$our_personids = array_map(Array($GLOBALS['db'], 'quote'), array_keys($this->_attendance_records));
				$sql .= ' OR personid IN ('.implode(', ', $our_personids).')';
			}
			$sql .= ')';
		}
		if (strlen($this->age_bracket)) {
			$sql .= ' AND age_bracket = '.$db->quote($this->age_bracket).' ';
		}
		if ($this->status !== NULL) {
			$sql .= ' AND status = '.$db->quote($this->status);
		}

		$res = $db->query($sql);
		check_db_result($res);
	}



//--        INTERFACE PAINTING AND PROCESSING        --//


	function printSummary()
	{
	}


	public function printWidget($prefix, $personid)
	{
		static $is_first = TRUE;
		if (isset($this->_persons[$personid])) {
			$v = isset($this->_attendance_records[$personid])
					? ($this->_attendance_records[$personid] ? 'present' : 'absent')
					: (empty($this->_attendance_records) ? '' : 'unknown');
			print_widget(
				'attendances['.$prefix.']['.$personid.']',
				Array(
					'options' => Array('unknown' => '?', 'present' => 'Present', 'absent' => 'Absent'),
					'type' => 'select',
					'style' => 'colour-buttons',
					'class' => $is_first ? 'autofocus' : '',
				),
				$v
			);
			$is_first = FALSE;
			return 1;
		}
		return 0;
	}

	function &getPersons()
	{
		return $this->_persons;
	}
	
	public function getCohortName()
	{
		$obj = $this->_getCohortObject();
		if ($obj) return $obj->getValue('name');
	}
	
	public function checkAllowedDate()
	{
		$obj = $this->_getCohortObject();
		if (!$obj) return FALSE;
		return $obj->canRecordAttendanceOn($this->date);
	}
	
	public static function getPersonIDsForCohorts($cohortids)
	{
		$db = $GLOBALS['db'];
		$groupids = $congids = Array();
		foreach ($cohortids as $cid) {
			list($type, $id) = explode('-', $cid);
			if ($type == 'c') $congids[] = $id;
			if ($type == 'g') $groupids[] = $id;
		}
		$SQL = 'SELECT p.id, p.first_name, p.last_name, p.status, c.id as congregationid, c.name as congregation, '
				.($groupids ? 'group_concat(pgm.groupid) as groupids' : '"" AS groupids').'
				FROM person p
				JOIN family f on p.familyid = f.id
				LEFT JOIN congregation c ON p.congregationid = c.id
				';
		if ($groupids) {
			$SQL .= '
				LEFT JOIN person_group_membership pgm 
					ON pgm.personid = p.id
					AND pgm.groupid in ('.implode(', ', array_map(Array($db, 'quote'), $groupids)).')
				';
		}
		$SQL .= '
				WHERE
				';
		$wheres = Array();
		if ($congids) $wheres[] = '(p.congregationid IN ('.implode(', ', array_map(Array($db, 'quote'), $congids)).'))';
		if ($groupids) $wheres[] = '(pgm.groupid IS NOT NULL)';
		$SQL .= implode(" OR ", $wheres);
		$order = defined('ATTENDANCE_LIST_ORDER') ? constant('ATTENDANCE_LIST_ORDER') : self::LIST_ORDER_DEFAULT;
		$order = preg_replace("/(^|[^.])status($| |,)/", '\\1p.status\\2', $order);
		$SQL .=  "GROUP BY p.id \n";
		$SQL .= ' ORDER BY '.$order."\n";
		$res= $db->queryAll($SQL, null, null, true);
		check_db_result($res);
		return $res;
		
		
	}


	function printForm($prefix=0)
	{
		if (empty($this->_persons)) return 0;
		
		$GLOBALS['system']->includeDBClass('person');
		$dummy = new Person();
		?>
		<table class="table table-condensed table-auto-width valign-middle">
		<?php
		$is_first = TRUE;
		foreach ($this->_persons as $personid => $details) {
			$dummy->populate($personid, $details);
			?>
			<tr>
			<?php 
			if (!SizeDetector::isNarrow()) {
				?>
				<td><?php echo $personid; ?></td>
				<?php
			}
			if ($this->show_photos) {
				?>
				<td>
					<a class="med-popup" tabindex="-1" href="?view=persons&personid=<?php echo $personid; ?>">
						<img style="width: 50px; max-width: 50px" src="?call=person_photo&personid=<?php echo (int)$personid; ?>" />
					</a>
				</td>
				<?php
			}
			?>
				<td>
					<?php echo ents($details['first_name'].' '.$details['last_name']); ?>
				</td>
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
				<td class="narrow">
					<?php $this->printWidget($prefix, $personid); ?>
				</td>
			<?php
			if (!SizeDetector::isNarrow()) {
				?>
				<td class="action-cell narrow">
					<a class="med-popup" tabindex="-1" href="?view=persons&personid=<?php echo $personid; ?>"><i class="icon-user"></i>View</a> &nbsp;
					<a class="med-popup" tabindex="-1" href="?view=_edit_person&personid=<?php echo $personid; ?>"><i class="icon-wrench"></i>Edit</a> &nbsp;
					<a class="med-popup" tabindex="-1" href="?view=_add_note_to_person&personid=<?php echo $personid; ?>"><i class="icon-pencil"></i>Add Note</a>
				</td>
				<?php
			}
			?>
			</tr>
			<?php
			$is_first = FALSE;
		}
		?>
		</table>
		<?php
		return count($this->_persons);
	}

	function printHeadcountField()
	{
		if ((int)$this->congregationid) {
			$headcountFieldName = 'headcount[congregation]['.$this->congregationid.']';
			$headcountValue = Headcount::fetch('congregation', $this->date, $this->congregationid);
		} else {
			$headcountFieldName = 'headcount[group]['.$this->groupid.']';
			$headcountValue = Headcount::fetch('person_group', $this->date, $this->groupid);
		}
		?>
		<input type="text" class="int-box" name="<?php echo $headcountFieldName; ?>" value="<?php echo $headcountValue; ?>" size="5" />
		<input type="button" class="btn" onclick="var x = $(this).siblings('input').get(0); x.value = x.value == '' ? 1 : parseInt(x.value, 10)+1" value="+" />
		<?php
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
		return count($this->_attendance_records);
	}

	public function getStats()
	{
		$db = $GLOBALS['db'];
		$groupingField = ((int)$this->congregationid) ? 'p.status' : 'pgms.label';
		$SQL = 'SELECT present, '.$groupingField.' AS status, count(p.id) AS total
				FROM attendance_record ar
				JOIN person p ON ar.personid = p.id
				LEFT JOIN person_group pg ON pg.id = ar.groupid
				LEFT JOIN person_group_membership pgm ON pgm.personid = p.id AND pgm.groupid = pg.id
				LEFT JOIN person_group_membership_status pgms ON pgms.id = pgm.membership_status
				WHERE date = '.$db->quote($this->date).'
				AND ar.groupid = '.$db->quote((int)$this->groupid).'
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
		if ((int)$this->congregationid) {
			$headcount = Headcount::fetch('congregation', $this->date, $this->congregationid);
		} else {
			$headcount = Headcount::fetch('person_group', $this->date, $this->groupid);
		}

		?>
		<table class="table valign-middle attendance-stats table-bordered" style="width: 40ex">
		<?php
		if ($headcount) {
			?>
			<tr class="headcount">
				<th>Total Headcount</th>
				<td colspan="3">
					<b>
					<?php
					echo $headcount;
					?>
					</b>
				</td>
			</tr>
			<?php
		}
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
		if ($headcount) {
			?>
			<tr class="extras">
				<th>Extras</th>
				<td colspan="3"><b><?php echo ($headcount - $totals[1]); ?></b></td>
			</tr>
			<?php
		}
		?>
		</table>
		<?php

	}

	function getStatsForPeriod($start_date, $end_date, $cohortid)
	{
		$db =& $GLOBALS['db'];
		
		list($type, $id) = explode('-', $cohortid);
		$groupid = ($type == 'g') ? $id : 0;
		$status_col = ($type == 'g') ? 'pgms.id' : 'p.status';
		$cohort_where = 'ar.groupid = '.(int)$groupid;
		if ($type == 'c') {
			$cohort_where .= ' AND p.congregationid = '.(int)$id;
		}		
		$sql = '
				SELECT status, rank, AVG(percent_present) as avg_attendance FROM
				(
					SELECT ar.personid, '.$status_col.' AS status, pgms.rank, CONCAT(ROUND(SUM(ar.present) * 100 / COUNT(ar.date)), '.$db->quote('%').') as percent_present
					FROM 
						person p 
						JOIN attendance_record ar ON p.id = ar.personid
						LEFT JOIN person_group_membership pgm ON pgm.personid = p.id AND pgm.groupid = ar.groupid
						LEFT JOIN person_group_membership_status pgms ON pgms.id = pgm.membership_status
					WHERE 
						ar.date BETWEEN '.$db->quote($start_date).' AND '.$db->quote($end_date).'
						AND '.$cohort_where.'
				';

		$sql .=	'
					GROUP BY ar.personid, '.$status_col.'
				) indiv
				GROUP BY rank, status WITH ROLLUP';
		$res = $db->queryAll($sql);
		check_db_result($res);
		
		$stats = Array();
		$stats[NULL]['rate'] = $stats[NULL]['avg_present'] = $stats[NULL]['avg_absent'] = 0;
		
		foreach ($res as $row) {
			$stats[$row['status']]['rate'] = round($row['avg_attendance']);
			$stats[$row['status']]['avg_present'] = 0;
			$stats[$row['status']]['avg_absent'] = 0;	
		}
		
		$sql = '
				SELECT status, present, rank, AVG(count) as count FROM
				(
					SELECT ar.date, '.$status_col.' AS status, pgms.rank, present, count(*) as count
					FROM 
						person p 
						JOIN attendance_record ar ON p.id = ar.personid
						LEFT JOIN person_group_membership pgm ON pgm.personid = p.id AND pgm.groupid = ar.groupid
						LEFT JOIN person_group_membership_status pgms ON pgms.id = pgm.membership_status
					WHERE 
						ar.date BETWEEN '.$db->quote($start_date).' AND '.$db->quote($end_date).'
						AND '.$cohort_where.'
					GROUP BY ar.date, status, present
				) perdate GROUP BY status, present';
		$res = $db->queryAll($sql);
		check_db_result($res);
		foreach ($res as $row) {
			$key = $row['present'] ? 'avg_present' : 'avg_absent';
			$stats[$row['status']][$key] = $row['count'];
			$stats[NULL][$key] += (float)$row['count'];
		}		
		return $stats;
	}

	function getAttendances($congregationids, $groupid, $age_bracket, $start_date, $end_date)
	{
		$SQL = 'SELECT p.id, p.last_name, p.first_name, '.($groupid ? 'pgms.label AS membership_status, ' : '').' p.status, ar.date, ar.present
				FROM person p
				JOIN family f ON p.familyid = f.id
				';
		if ($groupid) {
			$SQL .= '
				JOIN person_group_membership pgm ON pgm.personid = p.id AND pgm.groupid = '.(int)$groupid;
		}
		$SQL .= '
				LEFT JOIN attendance_record ar ON ar.personid = p.id
					AND ar.date BETWEEN '.$GLOBALS['db']->quote($start_date).' AND '.$GLOBALS['db']->quote($end_date);
		if ($congregationids) {
			$SQL .= ' AND ar.groupid = 0';
		}
		if ($groupid) {
			$SQL .= ' AND ar.groupid = '.(int)$groupid;
			$SQL .= '
				LEFT JOIN person_group_membership_status pgms ON pgms.id = pgm.membership_status';
		}
		$SQL .= '
				WHERE ((p.status <> "archived") OR (ar.present IS NOT NULL)) ';
		if ($congregationids) {
			 $SQL .= '
				 AND p.congregationid IN ('.implode(', ', array_map(Array($GLOBALS['db'], 'quote'), $congregationids)).') ';
		}
		if ($age_bracket !== '') {
			$SQL .= '
				AND p.age_bracket = '.$GLOBALS['db']->quote($age_bracket);
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
			if (!empty($row['date'])) $dates[$row['date']] = 1;
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

	static public function printCohortChooserRow($selectedValue)
	{
		static $groups = NULL;
		static $congregations = NULL;
		if ($groups === NULL) {
			$congregations = $GLOBALS['system']->getDBObjectData('congregation', Array('!attendance_recording_days' => 0), 'OR', 'meeting_time');
			$groups = $GLOBALS['system']->getDBObjectData('person_group', Array('!attendance_recording_days' => 0, 'is_archived' => 0), 'AND', 'category, name');
			// need to preserve category too
			uasort($groups, create_function('$x,$y', '$r = strnatcmp($x["category"], $y["category"]); if ($r == 0) $r = strnatcmp($x["name"], $y["name"]); return $r;')); // to ensure natural sorting
		}
		$lastCategory = -1;
		?>
		<tr>
			<td>
				<select name="cohortids[]">
					<option value="">-- Choose --</option>
					<optgroup label="Congregations">
					<?php
					foreach ($congregations as $congid => $cong) {
						$s = ($selectedValue == 'c-'.$congid) ? 'selected="selected"' : '';
						?>
						<option value="c-<?php echo $congid; ?>" <?php echo $s; ?>><?php echo ents($cong['name']); ?></option>
						<?php
					}
					?>
					</optgroup>
					<optgroup label="Groups">
					<?php
					foreach ($groups as $groupid => $group) {
						if ($lastCategory != $group['category']) {
							?>
							<option disabled="disabled">-- <?php echo ents($group['category'] ? $group['category'] : 'Uncategorised'); ?>--</option>
							<?php
							$lastCategory = $group['category'];
						}
						$s = ($selectedValue == 'g-'.$groupid) ? 'selected="selected"' : '';
						?>
						<option value="g-<?php echo $groupid; ?>" <?php echo $s; ?>><?php echo ents($group['name']); ?></option>
						<?php
					}
					?>
					</optgroup>
					</optgroup>
				</select>
			</td>
		</tr>
		<?php
	}





		


}//end class
