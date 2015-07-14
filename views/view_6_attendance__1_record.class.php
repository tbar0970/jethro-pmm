<?php
require_once 'db_objects/attendance_record_set.class.php';
require_once 'include/size_detector.class.php';

class View_Attendance__Record extends View
{
	var $_record_sets = Array();
	var $_attendance_date = NULL;
	var $_age_bracket = NULL;

	var $_cohortids = Array();

	var $_show_photos = FALSE;
	var $_parallel_mode = FALSE;

	static function getMenuPermissionLevel()
	{
		return PERM_EDITATTENDANCE;
	}

	function getTitle()
	{
		if (!empty($_POST['attendances_submitted'])) {
			return 'Attendance recorded for '.format_date($this->_attendance_date);
		} else if (!empty($_REQUEST['attendance_date_d'])) {
			return 'Record Attendance for '.format_date($this->_attendance_date);
		} else {
			return 'Record Attendance';
		}
	}

	function processView()
	{
		if (empty($_REQUEST['params_submitted']) && empty($_REQUEST['attendances_submitted'])) {
			if (!empty($_SESSION['attendance'])) {
				$this->_age_bracket = array_get($_SESSION['attendance'], 'age_bracket');
				$this->_cohortids = array_get($_SESSION['attendance'], 'cohortids');
				$this->_show_photos =  array_get($_SESSION['attendance'], 'show_photos', FALSE);
				$this->_parallel_mode =  array_get($_SESSION['attendance'], 'parallel_mode', FALSE);
			}
			// Default to last Sunday, unless today is Sunday
			$default_day = defined('ATTENDANCE_DEFAULT_DAY') ? ATTENDANCE_DEFAULT_DAY : 'Sunday';
			$this->_attendance_date = date('Y-m-d', ((date('l') == $default_day) ? time() : strtotime('last '.$default_day)));
		}

		if (!empty($_REQUEST['params_submitted']) || !empty($_REQUEST['attendances_submitted'])) {
			$this->_attendance_date = process_widget('attendance_date', Array('type' => 'date'));
			$this->_age_bracket = $_SESSION['attendance']['age_bracket'] = array_get($_REQUEST, 'age_bracket');
			$this->_show_photos = $_SESSION['attendance']['show_photos'] = array_get($_REQUEST, 'show_photos', FALSE);
			$this->_parallel_mode = $_SESSION['attendance']['parallel_mode'] = array_get($_REQUEST, 'parallel_mode', FALSE);

			foreach ($_REQUEST['cohortids'] as $id) {
				if ($id) $this->_cohortids[] = $id;
				$_SESSION['attendance']['cohortids'] = $this->_cohortids;
			}

			$status = NULL; // TODO
			foreach ($this->_cohortids as $id) {
				$this->_record_sets[$id] = new Attendance_Record_set($this->_attendance_date, $id, $this->_age_bracket, $status);
				if ($this->_show_photos) $this->_record_sets[$id]->show_photos = TRUE;
			}
		}

		if (!empty($_REQUEST['attendances_submitted'])) {
			// Process step 2
			if ($_SESSION['enter_attendance_token'] == $_REQUEST['enter_attendance_token']) {
				
				// Clear the token from the session on disk
				$_SESSION['enter_attendance_token'] = NULL;
				session_write_close();
				session_start();

				// Process the form
				foreach ($this->_record_sets as $i => $set) {
					if ($set->processForm($i)) {
						$set->save();
						if ((int)$set->congregationid) {
							Headcount::save('congregation', $this->_attendance_date, $set->congregationid, $_REQUEST['headcount']['congregation'][$set->congregationid]);
						} else {
							Headcount::save('person_group', $this->_attendance_date, $set->groupid, $_REQUEST['headcount']['group'][$set->groupid]);
						}
					}
				}
			} else {
				trigger_error('Could not save attendances - synchronizer token does not match.  This probably means the request was duplicated somewhere along the line.  If you see your changes below, they have been saved by the other request');
				sleep(3); // Give the other one time to finish before we load again

				// Pretend we are back in step 2
				$_POST['attendances_submitted'] = FALSE;
				$_SESSION['enter_attendance_token'] = md5(time());
			}
		}
	}
	
	function printView()
	{
		if (empty($this->_record_sets)) {
			$this->printParams();
		} else if (empty($_POST['attendances_submitted'])) {
			$this->printForm();
		} else {
			$this->printConfirmation();
		}
	}

	private function printParams()
	{
		// STEP 1 - choose congregation and date
		?>
		<form method="get" class="well well-small clearfix form-inline">
			<input type="hidden" name="view" value="<?php echo $_REQUEST['view']; ?>" />
			<table class="attendance-config-table">
				<tr>
					<th>For</th>
					<td colspan="2" class="fill-me">
						<?php
						print_widget('age_bracket', Array(
								'type'			=> 'select',
								'options'		=> Array('' => 'All age brackets') + explode(',', AGE_BRACKET_OPTIONS),
								'default'		=> '',
								'allow_empty'	=> false,
						), $this->_age_bracket);
						?>
					</td>
				</tr>
				<tr>
					<th>In</th>

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
				<tr>
					<th>On</th>
					<td colspan="2">
						<?php
						print_widget('attendance_date', Array('type' => 'date'), $this->_attendance_date); ?>
					</td>
				</tr>
			<?php
			if ($GLOBALS['system']->featureEnabled('PHOTOS')) {
				?>
				<tr>
					<th></th>
					<td>
						<label class="checkbox">
							<input type="checkbox" name="show_photos" value="1" <?php if ($this->_show_photos) echo 'checked="checked"'; ?> />
							Show photos
						</label>
					<?php
					if (!SizeDetector::isNarrow()) {
						?>
						<br />
						<label class="checkbox" title="Tabular format means that multiple groups/congregations will be shown as columns in a combined table">
							<input type="checkbox" name="parallel_mode" value="1" <?php if ($this->_parallel_mode) echo 'checked="checked"'; ?> />
							Use tabular format
						</label>
						<?php
					}
					?>
					</td>
				</tr>
				<?php
			}
			?>
			</table>
			<button type="submit" class="btn attendance-config-submit">Continue <i class="icon-chevron-right"></i></button>
			<input type="hidden" name="params_submitted" value="1" />
		</form>
		<?php
	}
	
	private function printForm()
	{
		foreach ($this->_cohortids as $i => $cohortid) {
			list($type, $id) = explode('-', $cohortid);
			if ($type == 'g') {
				$group = $GLOBALS['system']->getDBObject('person_group', $id);
				if (!$group->canRecordAttendanceOn($this->_attendance_date)) {
					print_message("Attendance for the group ".$group->getValue('name').' cannot be recorded on a '.date('l', strtotime($this->_attendance_date)), 'error');
					unset($this->_record_sets[$cohortid]);
				}
			}
			if ($type == 'c') {
				$cong = $GLOBALS['system']->getDBObject('congregation', $id);
				if (!$cong->canRecordAttendanceOn($this->_attendance_date)) {
					print_message("Attendance for the congregation ".$cong->getValue('name').' cannot be recorded on a '.date('l', strtotime($this->_attendance_date)), 'error');
					unset($this->_record_sets[$cohortid]);
				}				
			}
		}

		$_SESSION['enter_attendance_token'] = md5(time());
		// STEP 2 - enter attendances
		ob_start();
		?>
		<form method="post" class="attendance warn-unsaved" action="?view=attendance__record">
			<input type="hidden" name="attendance_date" value="<?php echo $this->_attendance_date; ?>" />
			<input type="hidden" name="age_bracket" value="<?php echo $this->_age_bracket; ?>" />
			<input type="hidden" name="show_photos" value="<?php echo $this->_show_photos; ?>" />
			<input type="hidden" name="parallel_mode" value="<?php echo $this->_parallel_mode; ?>" />
			<input type="hidden" name="enter_attendance_token" value="<?php echo $_SESSION['enter_attendance_token']; ?>" />
			<input type="hidden" name="attendances_submitted" value="1" />
			<?php
			print_hidden_fields(Array('cohortids' => $this->_cohortids));
			?>

			<p class="visible-desktop smallprint">For greatest speed, press P for present and A for absent.  The cursor will automatically progress to the next person.  To go back, use the arrow keys.</p>

			<?php
			if ($this->_parallel_mode && (count($this->_cohortids) > 1 ) && !SizeDetector::isNarrow()) {
				$totalPrinted = $this->printFormParallel();
			} else {
				$totalPrinted = $this->printFormSequential();
			}
			?>
		</form>
		<?php
		if (ini_get('max_input_vars') && ($totalPrinted > ini_get('max_input_vars'))) {
			ob_end_clean();
			print_message("The parameters you have selected will list more persons "
						. "than your server can process.  Please narrow down your parameters, "
						. "or ask your server administrator to increase the PHP max_input_vars setting"
						. " (currently ".ini_get('max_input_vars').')', 'error');
		} else {
			ob_flush();
		}
	}

	private function printFormParallel()
	{
		$totalPersons = Attendance_Record_Set::getPersonIDsForCohorts($this->_cohortids);
		$totalPrinted = 0;
		?>
		<table class="table table-condensed table-auto-width valign-middle">
			<thead>
				<tr>
			<?php
			if (SizeDetector::isWide()) {
				?>
				<th></th>
				<?php
			}
			if ($this->_show_photos) {
				?>
					<th>&nbsp;</th>
				</td>
				<?php
			}
			?>
					<th>Name</th>
			<?php
			foreach ($this->_record_sets as $prefix => $set) {
				?>
				<th class="center"><?php echo $set->getCohortName();?> </th>
				<?php
			}
			if (SizeDetector::isWide()) {
				?>
				<th>Actions</th>
				<?php
			}
			?>
				</tr>
			</thead>
			<tbody>


			<?php
			foreach ($totalPersons as $personid => $detail) {
				?>
				<tr>
				<?php
				if (SizeDetector::isWide()) {
					?>
					<td><?php echo $personid; ?></td>
					<?php
				}
				if ($this->_show_photos) {
					?>
					<td>
						<a class="med-popup" tabindex="-1" href="?view=persons&personid=<?php echo $personid; ?>">
							<img style="width: 50px; max-width: 50px" src="?call=person_photo&personid=<?php echo (int)$personid; ?>" />
						</a>
					</td>
					<?php
				}
				?>
					<td><?php echo ents($detail['first_name'].' '.$detail['last_name']); ?></td>
				<?php
				foreach ($this->_record_sets as $prefix => $set) {
					?>
					<td class="parallel-attendance">
						<?php
						$totalPrinted += $set->printWidget($prefix, $personid);
						?>
					</td>
					<?php
				}
				if (SizeDetector::isWide()) {
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
			}
			?>
				<tr class="headcount">
					<th class="right" colspan="<?php echo 1+(int)SizeDetector::isWide()+(int)$this->_show_photos; ?>">Total Headcount: &nbsp;</th>
				<?php
				foreach ($this->_record_sets as $prefix => $set) {
					?>
					<td class="center parallel-attendance"><?php $set->printHeadcountField(); ?></td>
					<?php
				}
					?>
					<td>&nbsp;</td>
				</tr>
			</tbody>
		</table>
		<input type="submit" class="btn" value="Save All Attendances" />
		<a href="?view=attendance__record" class="btn">Cancel</a>
		<?php


		return $totalPrinted;
	}
	
	private function printFormSequential()
	{
		$totalPrinted = 0;
		foreach ($this->_record_sets as $i => $set) {
			if ((int)$set->congregationid) {
				$congregation = $GLOBALS['system']->getDBObject('congregation', (int)$set->congregationid);
			} else if ($set->groupid) {
				$group =& $GLOBALS['system']->getDBObject('person_group', $set->groupid);
			} else {
				return;
			}
			$title = $set->getCohortName();
			if (strlen($set->age_bracket)) {
				$GLOBALS['system']->includeDBClass('person');
				$p = new Person();
				$p->setValue('age_bracket', $set->age_bracket);
				$title = $p->getFormattedValue('age_bracket').' in '.$title;
			}
			?>
			<h3><?php echo ents($title); ?></h3>
			<div class="width-really-auto form-inline">
				<?php
				$setPrinted = $set->printForm($i);
				if ($setPrinted > 0) {
					$totalPrinted += $setPrinted;
					?>
					<div class="container row-fluid control-group">
						<p class="span6">
							Total headcount:
							<?php $set->printHeadcountField(); ?>
						</p>
						<p class="span6 align-right nowrap">
							<input type="submit" class="btn" value="Save All Attendances" />
							<a href="?view=attendance__record" class="btn">Cancel</a>
						</p>
					</div>
					<?php
				} else {
					?>
					<i>(No persons in this listing)</i>
					<?php
				}
				?>
			</div>
			<?php

		}
		return $totalPrinted;
	}

	private function printConfirmation()
	{
		// STEP 3 - confirmation
		foreach ($this->_record_sets as $set) {
			if ($set->congregationid) {
				$congregation = $GLOBALS['system']->getDBObject('congregation', (int)$set->congregationid);
				$title = $congregation->getValue('name').' congregation';
			} else {
				$group =& $GLOBALS['system']->getDBObject('person_group', $set->groupid);
				$title = $group->getValue('name').' group';
			}
			echo '<h3>'.$title.'</h3>';
			$set->printStats();
		}
		?>
		<p><a href="?view=<?php echo $_REQUEST['view']; ?>"><i class="icon-pencil"></i>Record more attendances</a></p>
		<p><a href="?view=persons__reports"><i class="icon-list"></i>Analyse attendance using a person report</a></p>
		<?php
	}
}
