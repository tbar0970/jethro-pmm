<?php
class View_Attendance__Display extends View
{
	private $age_bracket = null;
	private $cohortids = Array();
	private $start_date = null;
	private $end_date = null;
	private $format = 'sequential';

	private $letters = Array(0 => 'A', 1 => 'P', '' => '?', '-1' => '');
	private $classes = Array(0 => 'absent', 1 => 'present', '' => 'unknown', '-1' => '');

	static function getMenuPermissionLevel()
	{
		return PERM_VIEWATTENDANCE;
	}

	function getTitle()
	{
		return 'Display attendance';
	}

	function processView()
	{
		if (empty($_REQUEST['params_submitted'])) {
			if (!empty($_SESSION['attendance'])) {
				$this->age_bracket = array_get($_SESSION['attendance'], 'age_bracket');
				$this->cohortids = array_get($_SESSION['attendance'], 'cohortids');
				$this->start_date = array_get($_SESSION['attendance'], 'start_date', date('Y-m-d', strtotime('-7 weeks')));
				$this->end_date = array_get($_SESSION['attendance'], 'end_date');
				$this->format = array_get($_SESSION['attendance'], 'format');
			} else {
				$this->start_date = date('Y-m-d', strtotime('-7 weeks'));
			}

		} else {
			$this->age_bracket = $_SESSION['attendance']['age_bracket'] = $_REQUEST['age_bracket'];
			if ($this->age_bracket != '') $this->age_bracket = (int)$this->age_bracket;
			if (!empty($_REQUEST['cohortids']) && is_array($_REQUEST['cohortids'])) {
				foreach ($_REQUEST['cohortids'] as $id) {
					$this->cohortids[] = $id;
				}
				$_SESSION['attendance']['cohortids'] = $this->cohortids;
			}
			$this->start_date = $_SESSION['attendance']['start_date'] = process_widget('start_date', Array('type' => 'date'));
			$this->end_date = $_SESSION['attendance']['end_date'] = process_widget('end_date', Array('type' => 'date'));
			$this->format = $_SESSION['attendance']['format'] = $_REQUEST['format'];
		}

		// Make sure there are no empty congregation IDs, except the first one
		for ($i = count($this->cohortids); $i >= 0 ; $i--) {
			if (empty($this->cohortids[$i])) unset($this->cohortids[$i]);
		}

	}
	
	public function printView()
	{
		$this->_printParams();

		if (!empty($_REQUEST['params_submitted'])) {
			if ($this->format == 'sequential') {
				$this->_printResultsSequential();
			} else {
				$this->_printResultsTabular();
			}
		}
	}

	private function _printParams()
	{
		?>
		<form method="get" class="well well-small clearfix">
			<input type="hidden" name="view" value="<?php echo $_REQUEST['view']; ?>" />
			<table class="attendance-config-table valign-middle">
				<tr>
					<th>For</th>
					<td colspan="2" class="fill-me">
						<?php
						print_widget('age_bracket', Array(
								'type'			=> 'select',
								'options'		=> Array('' => 'All age brackets') + explode(',', AGE_BRACKET_OPTIONS),
								'default'		=> '',
								'allow_empty'	=> false,
						), $this->age_bracket);
						?>
					</td>
				</tr>
				<tr>
					<th>In</th>
					<td class="valign-top">
						<table class="expandable">
							<?php
							if (empty($this->cohortids)) {
								Attendance_Record_Set::printCohortChooserRow(NULL);
							} else {
								foreach ($this->cohortids as $id) {
									Attendance_Record_Set::printCohortChooserRow($id);
								}
							}
							?>
						</table>
					</td>
				</tr>
				<tr>
					<th>Between</th>
					<td colspan="2">
						<?php print_widget('start_date', Array('type' => 'date'), $this->start_date); ?>
					</td>
				</tr>
				<tr>
					<th>And</th>
					<td colspan="2">
						<?php print_widget('end_date', Array('type' => 'date'), $this->end_date); ?>
					</td>
				</tr>
				<tr>
					<th>Format</th>
					<td>
						<?php
						print_widget(
							'format',
							Array(
								'type' => 'select',
								'options' => Array(
									'sequential' => 'Sequential',
									'tabular' => 'Tabular',
									'collapsed' => 'Date Totals',
							)),
							$this->format
						);
						?>
					</td>
				</tr>
			</table>
			<button type="submit" class="btn attendance-config-submit">Go <i class="icon-chevron-right"></i></button>
			<input type="hidden" name="params_submitted" value="1" />
		</form>
		<?php
	}

	private function _printResultsSequential()
	{
		$GLOBALS['system']->includeDBClass('attendance_record_set');
		$GLOBALS['system']->includeDBClass('person');

		if (!empty($this->cohortids)) {
			foreach ($this->cohortids as $cohortid) {
				if (empty($cohortid)) continue;
				$this->_printResultSet($cohortid);
			}
		}
	}

	private function _printResultSet($cohortid)
	{
		$congid = $groupid = NULL;
		list($type, $id) = explode('-', $cohortid);
		if ($type == 'c') $congid = $id;
		if ($type == 'g') $groupid = $id;
		
		echo '<h3>';
		if ($congid) {
			$cong = $GLOBALS['system']->getDBObject('congregation', $congid);
			$cong->printFieldValue('name');
			echo ' Congregation';
		} else {
			$group = $GLOBALS['system']->getDBObject('person_group', $groupid);
			$group->printFieldValue('name');
			echo ' Group';
		}
		echo '</h3>';

		list ($dates, $attendances, $totals) = Attendance_Record_Set::getAttendances((array)$congid, $groupid, $this->age_bracket, $this->start_date, $this->end_date);
		if (empty($attendances)) {
			?>
			<p><i>No attendance records found.  Try adjusting your criteria.</i></p>
			<?php
			return;
		}
		$headcounts = Headcount::fetchRange(($congid ? 'congregation' : 'person_group'), $congid ? $congid : $groupid, $this->start_date, $this->end_date);
		$dummy = new Person();
		?>
		<form method="post" action="" class="bulk-person-action">
		<table class="table table-hover table-auto-width nowrap table-bordered table-condensed">
			<thead>
				<tr>
					<th>Last Name</th>
					<th>First Name</th>
					<th><?php echo $groupid ? 'Membership Status' : 'Status'; ?></th>
				<?php
				foreach ($dates as $date) {
					?>
					<th><?php echo date('j M', strtotime($date)); ?></th>
					<?php
				}
				?>
					<th>Actions</th>
					<th class="narrow selector form-inline"><input type="checkbox" class="select-all" title="Select all" /></th>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ($attendances as $personid => $record) {
				?>
				<tr <?php if ($record['status'] == 'archived') echo 'class="archived"'; ?>>
					<td><?php echo ents($record['last_name']); ?></td>
					<td><?php echo ents($record['first_name']); ?></td>
					<td>
						<?php
						if ($groupid) {
							echo ents($record['membership_status']);
						} else {
							$dummy->printFieldValue('status', $record['status']);
						}
						?>
					</td>
				<?php
				foreach ($dates as $date) {
					$letter = $this->letters[array_get($record, $date, '')];
					$class = $this->classes[array_get($record, $date, '')];
					echo '<td class="'.$class.'">'.$letter.'</td>';
				}
				?>
					<td class="narrow action-cell">
						<a class="med-popup" href="?view=persons&personid=<?php echo $personid; ?>"><i class="icon-user"></i>View</a> &nbsp;
					<?php
					if ($GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
						?>
						<a class="med-popup" href="?view=_edit_person&personid=<?php echo $personid; ?>"><i class="icon-wrench"></i>Edit</a> &nbsp;
						<?php
					}
					if ($GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
						?>
						<a class="med-popup" href="?view=_add_note_to_person&personid=<?php echo $personid; ?>"><i class="icon-pencil"></i>Add Note</a>
						<?php
					}
					?>
					</td>
					<td class="selector"><input name="personid[]" type="checkbox" value="<?php echo $personid; ?>" /></td>
				</tr>
				<?php
			}
			?>
			</tbody>
			<tfoot class="attendance-stats">
				<tr class="headcount">
					<th colspan="3">Total Headcount</th>
				<?php
				foreach ($dates as $date) {
					?>
					<td>
						<?php echo array_get($headcounts, $date); ?>
					</td>
					<?php
				}
				?>
					<td colspan="2">&nbsp;</td>
				</tr>
				<tr class="present">
					<th colspan="3">Total Present</th>
				<?php
				foreach ($dates as $date) {
					?>
					<td>
						<?php echo array_get($totals[$date], 1, 0); ?>
					</td>
					<?php
				}
				?>
					<td colspan="2">&nbsp;</td>
				</tr>
				<tr class="absent">
					<th colspan="3">Total Absent</th>
				<?php
				foreach ($dates as $date) {
					?>
					<td>
						<?php echo array_get($totals[$date], 0, 0); ?>
					</td>
					<?php
				}
				?>
					<td colspan="2">&nbsp;</td>
				</tr>
				<tr class="extras">
					<th colspan="3">Extras</th>
				<?php
				foreach ($dates as $date) {
					?>
					<td>
						<?php
						if (isset($headcounts[$date])) {
							echo ($headcounts[$date] - array_get($totals[$date], 1, 0));
						}
						?>
					</td>
					<?php
				}
				?>
					<td colspan="2">&nbsp;</td>
				</tr>
			</tfoot>
		</table>
		<?php
		include 'templates/bulk_actions.template.php';
		?>
		</form>
		<?php
	}

	private function _printResultsTabular()
	{
		$GLOBALS['system']->includeDBClass('attendance_record_set');
		$GLOBALS['system']->includeDBClass('person');

		$all_dates = $all_attendances = $all_totals = $all_persons = Array();
		if (!empty($this->cohortids)) {
			foreach ($this->cohortids as $cohortid) {
				$congid = $groupid = NULL;
				list($type, $id) = explode('-', $cohortid);
				if ($type == 'c') $congid = $id;
				if ($type == 'g') $groupid = $id;
				list ($cdates, $cattendances, $ctotals) = Attendance_Record_Set::getAttendances((array)$congid, $groupid, $this->age_bracket, $this->start_date, $this->end_date);
				$all_dates = array_merge($all_dates, $cdates);
				$all_totals[$cohortid] = $ctotals;
				foreach ($cattendances as $personid => $cat) {
					foreach ($cat as $k => $v) {
						if (in_array($k, Array('first_name', 'last_name', 'membership_status', 'status'))) {
							$all_persons[$personid][$k] = $v;
						} else {
							$all_attendances[$personid][$cohortid][$k] = $v;
						}
					}
				}
			}
		}
		$all_dates = array_unique($all_dates);
bam($all_attendances);
		?>
		<table class="table table-condensed table-auto-width valign-middle">
			<tr>
				<th>Name</th>
			<?php
			foreach ($all_dates as $date) {
				?>
				<th><?php echo format_date($date); ?></th>
				<?php
			}
			?>

			</tr>

		<?php


		foreach ($all_persons as $personid => $details) {
			?>
			<tr>
				<td>
					<?php echo ents($details['first_name'].' '.$details['last_name']); ?>
				</td>
			<?php
			foreach ($all_dates as $date) {
				if ($this->format == 'totals') {

				} else {
					foreach ($this->cohortids as $cohortid) {
						$catt = array_get($all_attendances[$personid], $cohortid, Array());
						$v = array_get($catt, $date, -1);
						$letter = $this->letters[$v];
						$class = $this->classes[$v];
						echo '<td class="'.$class.'">'.$letter.'</td>';
					}
				}
			}
			?>
			</tr>
			<?php
		}
	}
}
?>
