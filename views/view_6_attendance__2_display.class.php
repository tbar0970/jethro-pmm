<?php
class View_Attendance__Display extends View
{
	private $age_bracket = null;
	private $cohortids = Array();
	/*
	private $cohortids = Array();
	private $groupid = null;
	*/
	private $start_date = null;
	private $end_date = null;

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
				$this->_cohortids = array_get($_SESSION['attendance'], 'cohortids');
				$this->start_date = array_get($_SESSION['attendance'], 'start_date', date('Y-m-d', strtotime('-7 weeks')));
				$this->end_date = array_get($_SESSION['attendance'], 'end_date');
			} else {
				$this->start_date = date('Y-m-d', strtotime('-7 weeks'));
			}

		} else {
			$this->age_bracket = $_SESSION['attendance']['age_bracket'] = $_REQUEST['age_bracket'];
			if ($this->age_bracket != '') $this->age_bracket = (int)$this->age_bracket;
			if (!empty($_REQUEST['cohortids']) && is_array($_REQUEST['cohortids'])) {
				foreach ($_REQUEST['cohortids'] as $id) {
					$this->_cohortids[] = $id;
				}
				$_SESSION['attendance']['cohortids'] = $this->_cohortids;
			}
			$this->start_date = $_SESSION['attendance']['start_date'] = process_widget('start_date', Array('type' => 'date'));
			$this->end_date = $_SESSION['attendance']['end_date'] = process_widget('end_date', Array('type' => 'date'));
		}

		// Make sure there are no empty congregation IDs, except the first one
		for ($i = count($this->_cohortids); $i > 0 ; $i--) {
			if (empty($this->_cohortids[$i])) unset($this->_cohortids[$i]);
		}

	}
	
	function printView()
	{
		$this->_printParams();

		if (!empty($_REQUEST['params_submitted'])) {
			$this->_printResults();
		}
	}

	function _printParams()
	{
		?>
		<form method="get" class="well well-small clearfix">
			<input type="hidden" name="view" value="<?php echo $_REQUEST['view']; ?>" />
			<table class="attendance-config-table valign-middle">
				<tr>
					<th>Show attendance for</th>
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
					<th>at</th>
					<td class="valign-top">
						<table class="expandable">
							<?php
							if (empty($this->_cohortids)) {
								Attendance_Record_Set::printCohortChooserRow(NULL);
							} else {
								foreach ($this->_cohortids as $id) {
									Attendance_Record_Set::printCohortChooserRow($id);
								}
							}
							?>
						</table>
					</td>
				</tr>
				<tr>
					<th>between</th>
					<td colspan="2">
						<?php print_widget('start_date', Array('type' => 'date'), $this->start_date); ?>
					</td>
				</tr>
				<tr>
					<th>and</th>
					<td colspan="2">
						<?php print_widget('end_date', Array('type' => 'date'), $this->end_date); ?>
					</td>
				</tr>
			</table>
			<button type="submit" class="btn attendance-config-submit">Go <i class="icon-chevron-right"></i></button>
			<input type="hidden" name="params_submitted" value="1" />
		</form>
		<?php
	}

	function _printResults()
	{
		$GLOBALS['system']->includeDBClass('attendance_record_set');
		$GLOBALS['system']->includeDBClass('person');

		if (!empty($this->_cohortids)) {
			foreach ($this->_cohortids as $cohortid) {
				$this->_printResultSet($cohortid);
			}
		}
	}

	function _printResultSet($cohortid)
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
		$letters = Array(0 => 'A', 1 => 'P', '' => '?');
		$classes = Array(0 => 'absent', 1 => 'present', '' => 'unknown');
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
					$letter = $letters[array_get($record, $date, '')];
					$class = $classes[array_get($record, $date, '')];
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
}
?>
