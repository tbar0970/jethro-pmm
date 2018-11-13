<?php

// This is not a standard DB object but performs similarly
class Attendance_Record_Set
{

	public $date = NULL;
	public $congregationid = NULL;
	public $groupid = 0;
	public $age_brackets = NULL;
	public $statuses = NULL;
	public $show_photos = FALSE;
	private $_persons = NULL;
	private $_attendance_records = Array();
	private $_cohort_object = NULL;

	const LIST_ORDER_DEFAULT = 'status ASC, family_name ASC, familyid, ab.rank ASC, gender DESC';
//--        CREATING, LOADING AND SAVING        --//

	function __construct($date=NULL, $cohort=NULL, $age_brackets=NULL, $statuses=NULL)
	{
		if ($date && $cohort) {
			$this->load($date, $cohort, $age_brackets, $statuses);
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
		return $obj->acquireLock('att-'.$this->date);
	}

	public function haveLock()
	{
		$obj = $this->_getCohortObject();
		if (!$obj) {
			trigger_error("Could not get cohort object for lock");
			return FALSE;
		}
		return $obj->haveLock('att-'.$this->date);
	}

	public function releaseLock()
	{
		$obj = $this->_getCohortObject();
		if (!$obj) {
			trigger_error("Could not get cohort object for lock");
			return FALSE;
		}
		return $obj->releaseLock('att-'.$this->date);
	}

	function create()
	{
	}

	function load($date, $cohort, $age_brackets, $statuses)
	{
		$this->date = $date;
		list($cohortType, $cohortID) = explode('-', $cohort);
		switch ($cohortType) {
			case 'c': $this->congregationid = $cohortID; break;
			case 'g': $this->groupid = $cohortID; break;
			default: trigger_error("Invalid cohort $cohort"); return;
		}
		$this->age_brackets = $age_brackets;
		$this->statuses = $statuses;
		if ($this->statuses) {
			foreach ($this->statuses as $status) {
				list($statusType, $statusID) = explode('-', $status);
				if (($statusType == 'g') && ($cohortType != 'g')) {
					trigger_error("Cannot restrict congregational attendance by a group membership status");
					return;
				}
			}
		}

		// FETCH ANY EXISTING ATTENDANCE RECORDS
		$db =& $GLOBALS['db'];
		$sql = 'SELECT ar.personid, ar.present
					FROM attendance_record ar
					JOIN person p ON ar.personid = p.id
					LEFT JOIN person_group_membership pgm
						ON pgm.groupid = ar.groupid AND pgm.personid = p.id
				WHERE ar.date = '.$db->quote($date).'
					AND ar.groupid = '.(int)$this->groupid;
		if ($this->congregationid) {
			$sql .= '
				AND p.congregationid = '.$db->quote($this->congregationid);
		}
		$statusType = $statusID = NULL;
		if ($this->statuses) {
			$statusClauses = Array();
			foreach ($this->statuses as $status) {
				list($statusType, $statusID) = explode('-', $status);
				switch ($statusType) {
					case 'p':
						$statusClauses[] = 'p.status = '.(int)$statusID;
						break;
					case 'g':
						$statusClauses[] ='pgm.membership_status = '.(int)$statusID;
						break;
					default:
						trigger_error("invalid status filter $status"); return;
				}
			}
			$sql .= '
				AND (('.implode(') OR (', $statusClauses).'))';
		}
		if ($this->age_brackets) {
			$sql .= '
				AND p.age_bracketid IN ('.implode(',', array_map(Array($db, 'quote'), $this->age_brackets)).')';
		}
		$this->_attendance_records = $db->queryAll($sql, null, null, true);

		// NOW FETCH THE APPLICABLE PERSON RECORDS
		$order = defined('ATTENDANCE_LIST_ORDER') ? constant('ATTENDANCE_LIST_ORDER') : self::LIST_ORDER_DEFAULT;
		$order = str_replace('age_bracket', 'ab.rank', $order);

		$conds = Array();
		if ($this->age_brackets) {
			$conds['(age_bracketid'] = $this->age_brackets;
		}
		if ($this->statuses) {
			foreach ($this->statuses as $status) {
				list($statusType, $statusID) = explode('-', $status);
				$field = $statusType == 'g' ? '(membership_status' : '(status';
				$conds[$field][] = $statusID;
			}
		}
		if (!isset($conds['(status'])) $conds['!status'] = 'archived';
		if ($this->congregationid) {
			$conds['congregationid'] = $this->congregationid;
			$this->_persons = $GLOBALS['system']->getDBObjectData('person', $conds, 'AND', $order);
		} else {
			$group = $GLOBALS['system']->getDBObject('person_group', $this->groupid);
			$this->_persons = $group->getMembers($conds, $order);
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
			$stmt = $db->prepare('REPLACE INTO attendance_record (date, groupid, personid, present) VALUES ('.$db->quote($this->date).', '.(int)$this->groupid.', ?, ?)', Array('integer', 'integer', 'integer'));
			foreach ($this->_attendance_records as $personid => $present) {
                $res = $stmt->execute(Array($personid, $present));
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
					AND (congregationid = '.$db->quote($this->congregationid).')
					';
		}
		$sql .= '  AND personid IN ('.implode(',', array_map(Array($db, 'quote'), array_keys($this->_persons))).')';

		$res = $db->query($sql);
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

	public function printForm($prefix=0)
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
						<img style="width: 50px; max-width: 50px" src="?call=photo&personid=<?php echo (int)$personid; ?>" />
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
				<?php
				if ($GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
					?>
					<a class="med-popup" tabindex="-1" href="?view=_add_note_to_person&personid=<?php echo $personid; ?>"><i class="icon-pencil"></i>Add Note</a>
					<?php
				}
				?>
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
		<input type="number" inputmode="numeric" pattern="[0-9]*" name="<?php echo $headcountFieldName; ?>" value="<?php echo $headcountValue; ?>" style="width: 60px" />
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
				<th><?php echo _('Total Headcount'); ?></th>
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

	public static function getStatsForPeriod($start_date, $end_date, $cohortid)
	{
		$db =& $GLOBALS['db'];

		list($type, $id) = explode('-', $cohortid);
		$groupid = ($type == 'g') ? $id : 0;
		$status_col = ($type == 'g') ? 'pgms.id' : 'p.status';
		$cohort_where = 'ar.groupid = '.(int)$groupid;
		$cohort_joins = '';
		if ($type == 'c') {
			$cohort_where .= ' AND p.congregationid = '.(int)$id;
		}
		if ($type == 'g') {
			$cohort_joins = '
							LEFT JOIN person_group_membership pgm ON pgm.personid = p.id AND pgm.groupid = ar.groupid
							LEFT JOIN person_group_membership_status pgms ON pgms.id = pgm.membership_status
							';

		}
		$stats = Array();
		$stats[NULL]['rate'] = $stats[NULL]['avg_present'] = $stats[NULL]['avg_absent'] = 0;

		foreach (Array('status', 'age_bracketid') as $groupingField) {
			$rank = ($groupingField == 'status' && $type == 'g') ? 'rank, ' : '';
			$selectCol = ($groupingField == 'status') ? $status_col : $groupingField;

			// SELECT THE RATES

			$sql = '
					SELECT '.$groupingField.', '.$rank.' AVG(percent_present) as avg_attendance FROM
					(
						SELECT ar.personid, '.$selectCol.' AS '.$groupingField.', '.$rank.' CONCAT(ROUND(SUM(ar.present) * 100 / COUNT(ar.date)), '.$db->quote('%').') as percent_present
						FROM
							person p
							JOIN attendance_record ar ON p.id = ar.personid
							'.$cohort_joins.'
						WHERE
							ar.date BETWEEN '.$db->quote($start_date).' AND '.$db->quote($end_date).'
							AND '.$cohort_where.'
					';

			$sql .=	'
						GROUP BY ar.personid, '.$selectCol.'
					) indiv
					GROUP BY '.$rank.' '.$groupingField.' WITH ROLLUP';
			$res = $db->queryAll($sql);

			foreach ($res as $row) {
				if (NULL !== $row[$groupingField]) {
					$stats[$groupingField][$row[$groupingField]]['rate'] = round($row['avg_attendance']);
					$stats[$groupingField][$row[$groupingField]]['avg_present'] = 0;
					$stats[$groupingField][$row[$groupingField]]['avg_absent'] = 0;
				} else {
					// the rollup row
					$stats[$row[$groupingField]]['rate'] = round($row['avg_attendance']);
					$stats[$row[$groupingField]]['avg_present'] = 0;
					$stats[$row[$groupingField]]['avg_absent'] = 0;
				}
			}

			// SELECT THE NUMBER
			$sql = '
					SELECT '.$groupingField.', AVG(TotalPresent) as avg_present, AVG(TotalAbsent) as avg_absent
					FROM (
						SELECT ar.date, '.$selectCol.' AS '.$groupingField.',  '.$rank.' SUM(present) as TotalPresent, COUNT(*)-SUM(present) as TotalAbsent
						FROM
							person p
							JOIN attendance_record ar ON p.id = ar.personid
							'.$cohort_joins.'
						WHERE
							ar.date BETWEEN '.$db->quote($start_date).' AND '.$db->quote($end_date).'
							AND '.$cohort_where.'
						GROUP BY ar.date, '.$groupingField.'
					) perdate GROUP BY '.$groupingField.'';
			$res = $db->queryAll($sql);
			foreach ($res as $row) {
				foreach (Array('avg_present', 'avg_absent') as $key) {
					$stats[$groupingField][$row[$groupingField]][$key] = $row[$key];
					$stats[NULL][$key] += (float)$row[$key];
				}
			}
		}
		//exit;
		return $stats;
	}

	/**
	 * Get person data for people in the specified cohorts and params
	 * @param array	$cohortids	Eg c-1, g-2
	 * @param array $params		Filters to apply to person, eg age bracket
	 * @return array
	 */
	public static function getPersonDataForCohorts($cohortids, $params)
	{
		$db = $GLOBALS['db'];
		$groupids = $congids = Array();
		foreach ($cohortids as $cid) {
			list($type, $id) = explode('-', $cid);
			if ($type == 'c') $congids[] = $id;
			if ($type == 'g') $groupids[] = $id;
		}
		$SQL = 'SELECT person.id, person.first_name, person.last_name, person.status, c.id as congregationid, c.name as congregation, '
				.($groupids ? 'group_concat(pgm.groupid) as groupids' : '"" AS groupids').'
				FROM person person
				JOIN age_bracket ab ON ab.id = person.age_bracketid
				JOIN family f on person.familyid = f.id
				LEFT JOIN congregation c ON person.congregationid = c.id
				';
		if ($groupids) {
			$SQL .= '
				LEFT JOIN person_group_membership pgm
					ON pgm.personid = person.id
					AND pgm.groupid in ('.implode(', ', array_map(Array($db, 'quote'), $groupids)).')
				LEFT JOIN person_group_membership_status pgms
					ON pgms.id = pgm.membership_status
				';
		}
		$SQL .= '
				WHERE
				';
		$wheres = Array();
		if ($congids) $wheres[] = '(person.congregationid IN ('.implode(', ', array_map(Array($db, 'quote'), $congids)).'))';
		if ($groupids) $wheres[] = '(pgm.groupid IS NOT NULL)';
		$SQL .= implode(" OR ", $wheres);


		if (!empty($params['(age_bracketid'])) {
			$SQL .= '
				AND person.age_bracketid IN ('.implode(',', array_map(Array($GLOBALS['db'], 'quote'), $params['(age_bracketid'])).')';
		}
		$statusClauses = Array();
		foreach (array_get($params, '(status', Array()) as $status) {
			if (strlen($status)) {
				list($statusType, $statusID) = explode('-', $status);
				if (($statusType == 'g') && empty($groupids)) {
					trigger_error("Cannot filter by group membership status for congregational attendance");
					return Array(Array(), Array(), Array());
				}
				switch ($statusType) {
					case 'g':
						$statusClauses[] = 'pgm.membership_status = '.$GLOBALS['db']->quote($statusID);
						break;
					case 'p':
						$statusClauses[] = 'person.status = '.$GLOBALS['db']->quote($statusID);
						break;
				}
			}
		}
		if ($statusClauses) {
			$SQL .= 'AND (('.implode(') OR (', $statusClauses).'))';
		}

		$order = defined('ATTENDANCE_LIST_ORDER') ? constant('ATTENDANCE_LIST_ORDER') : self::LIST_ORDER_DEFAULT;
		$order = str_replace('age_bracket', 'ab.rank', $order);
		// Since we are getting persons for multiple cohorts, "status" has to mean person status here.
		$order = preg_replace("/(^|[^.])status($| |,)/", '\\1person.status\\2', $order);
		$SQL .=  "GROUP BY person.id \n";
		$SQL .= ' ORDER BY '.$order."\n";
		$res= $db->queryAll($SQL, null, null, true);
		return $res;


	}

	public static function getMostRecentDate($cohort)
	{
		list($type, $cohortID) = explode('-', $cohort);
		switch ($type) {
			case 'c':
				$SQL = 'SELECT MAX(date)
						FROM attendance_record a
						JOIN _person p ON p.id = a.personid
						WHERE p.congregationid = '.(int)$cohortID.'
						AND a.groupid = 0';
				break;
			case 'g':
				$SQL = 'SELECT MAX(date)
						FROM attendance_record a
						WHERE groupid = '.(int)$cohortID;
				break;
		}
		$res = $GLOBALS['db']->queryOne($SQL);
		return $res;
	}

	/**
	 * Get Attendance data for the specified criteria
	 * @param array $congregationids
	 * @param int $groupid
	 * @param array $params		Parameters to restrict person records, eg age bracket and status
	 * @param string $start_date
	 * @param string $end_date
	 * @return array
	 */
	public static function getAttendances($congregationids, $groupid, $params, $start_date, $end_date)
	{
		$SQL = 'SELECT person.id, person.last_name, person.first_name, '.($groupid ? 'pgms.label AS membership_status, ' : '').' person.status, ar.date, ar.present
				FROM person person
				JOIN age_bracket ab ON ab.id = person.age_bracketid
				JOIN family f ON person.familyid = f.id
				';
		if ($groupid) {
			$SQL .= '
				JOIN person_group_membership pgm ON pgm.personid = person.id AND pgm.groupid = '.(int)$groupid;
		}
		// restricting the attendance dates within a subquery improves performance significantly.
		$SQL .= '
				LEFT JOIN (
					SELECT personid, date, present
					FROM attendance_record ar
					WHERE ar.date BETWEEN '.$GLOBALS['db']->quote($start_date).' AND '.$GLOBALS['db']->quote($end_date).'
					AND ar.groupid = '.(int)$groupid.'
				) ar ON ar.personid = person.id';
		if ($groupid) {
			$SQL .= '
				LEFT JOIN person_group_membership_status pgms ON pgms.id = pgm.membership_status';
		}
		$SQL .= '
				WHERE ((person.status <> "archived") OR (ar.present IS NOT NULL)) ';
		if ($congregationids) {
			 $SQL .= '
				 AND person.congregationid IN ('.implode(', ', array_map(Array($GLOBALS['db'], 'quote'), $congregationids)).') ';
		}
		if (!empty($params['(age_bracketid'])) {
			$SQL .= '
				AND person.age_bracketid IN ('.implode(',', array_map(Array($GLOBALS['db'], 'quote'), $params['(age_bracketid'])).')';
		}
		$statuses = array_get($params, '(status', Array());
		if (isset($params['status'])) {
			$statuses[] = $params['status'];
		}
		$statusClauses = Array();
		foreach ($statuses as $status) {
			if (strlen($status)) {
				list($statusType, $statusID) = explode('-', $status);
				if (($statusType == 'g') && empty($groupid)) {
					trigger_error("Cannot filter by group membership status for congregational attendance");
					return Array(Array(), Array(), Array());
				}
				switch ($statusType) {
					case 'g':
						$statusClauses[] = 'pgm.membership_status = '.$GLOBALS['db']->quote($statusID);
						break;
					case 'p':
						$statusClauses[] = 'person.status = '.$GLOBALS['db']->quote($statusID);
						break;
				}
			}
		}
		if ($statusClauses) $SQL .= 'AND (('.implode(') OR (', $statusClauses).'))';

		$order = defined('ATTENDANCE_LIST_ORDER') ? constant('ATTENDANCE_LIST_ORDER') : self::LIST_ORDER_DEFAULT;
		$order = str_replace('age_bracket', 'ab.rank', $order);
		if ($congregationids) {
			$order = preg_replace("/(^|[^.])status($| |,)/", '\\1person.status\\2', $order);
		} else {
			$order = preg_replace("/(^|[^.])status($| |,)/", '\\1pgms.rank\\2', $order);
		}
		$SQL .= '
				ORDER BY '.$order;
		$dates = Array();
		$attendances = Array();
		$totals = Array();
		$res = $GLOBALS['db']->query($SQL);
		while ($row = $res->fetch()) {
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
			uasort($groups, function($x,$y) {$r = strnatcmp($x["category"], $y["category"]); if ($r == 0) $r = strnatcmp($x["name"], $y["name"]); return $r;}); // to ensure natural sorting
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

	public static function printPersonFilters($age_brackets, $statuses)
	{
		?>
		<div class="row-fluid">
			<div class="span6" style="margin-top: 5px">
				<label class="checkbox nowrap" >
					<?php
					print_widget(
						'age_brackets_all',
						Array(
							'type' => 'checkbox',
							'attrs' => Array(
								'data-toggle' => "visible",
								'data-target' => "#agebrackets"
							)
						),
						empty($age_brackets)
					);
					?>
					All age brackets
				</label>
				<div id="agebrackets" style="<?php echo empty($age_brackets) ? 'display: none' : ''; ?>">
					<?php
					print_widget('age_brackets', Array(
							'type'			=> 'reference',
							'references'    => 'age_bracket',
							'default'		=> '',
							'allow_empty'	=> true,
							'allow_multiple' => true,
							'height' => count(Age_Bracket::getMap()),

					), $age_brackets);
					?>
				</div>
			</div>
			<div class="span6" style="margin-top: 5px">
				<label class="checkbox nowrap">
					<?php
					print_widget(
						'statuses_all',
						Array(
							'type' => 'checkbox',
							'attrs' => Array(
								'data-toggle' => "visible",
								'data-target' => "#statuses"
							)
						),
						empty($statuses)
					);
					?>
					All statuses
				</label>
				<div id="statuses" style="<?php echo empty($statuses) ? 'display: none' : ''; ?>">
					<?php
					$statusOptions = self::getStatusOptions();
					print_widget('statuses', Array(
							'type'			=> 'select',
							'options'		=> $statusOptions,
							'default'		=> '',
							'allow_empty'	=> true,
							'allow_multiple' => true,
							'height' => count($statusOptions),
					), $statuses);
					?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get the options for filtering people by (person or group-membership) status
	 * @return array
	 */
	public static function getStatusOptions()
	{
		$statusOptions = Array();
		foreach (Person::getStatusOptions() as $id => $val) {
			$statusOptions['p-'.$id] = $val;
		}
		list($gOptions, $default) = Person_Group::getMembershipStatusOptionsAndDefault();
		foreach ($gOptions as $id => $val) {
			$statusOptions['g-'.$id] = $val;
		}
		return $statusOptions;
	}






}//end class
