<?php
require_once 'db_objects/attendance_record_set.class.php';
require_once 'include/size_detector.class.php';

class View_Attendance__Record extends View
{
	private $_record_sets = Array();
	private $_attendance_date = NULL;
	private $_age_brackets = NULL;
	private $_statuses = NULL;

	private $_cohortids = Array();

	private $_show_photos = FALSE;
	private $_parallel_mode = FALSE;

	static function getMenuPermissionLevel()
	{
		return PERM_EDITATTENDANCE;
	}

	function getTitle()
	{
		if (!empty($_POST['attendances_submitted'])) {
			return _('Attendance recorded for ').format_date($this->_attendance_date);
		} else if (!empty($_REQUEST['attendance_date_d'])) {
			return _('Record Attendance for ').format_date($this->_attendance_date);
		} else {
			return _('Record Attendance');
		}
	}

	function processView()
	{
		if (isset($_REQUEST['cohortids'])) {
			foreach ($_REQUEST['cohortids'] as $id) {
				if ($id) $this->_cohortids[] = $id;
			}
			$_SESSION['attendance']['cohortids'] = $this->_cohortids;
		}
		$this->_attendance_date = process_widget('attendance_date', Array('type' => 'date'));
		if (empty($this->_attendance_date)) {
			// Default to last Sunday, unless today is Sunday
			$default_day = defined('ATTENDANCE_DEFAULT_DAY') ? ATTENDANCE_DEFAULT_DAY : 'Sunday';
			if ($default_day == '(Current day)') {
				$this->_attendance_date = date('Y-m-d');
			} else {
				$this->_attendance_date = date('Y-m-d', ((date('l') == $default_day) ? time() : strtotime('last '.$default_day)));
			}
		}

		if (empty($_REQUEST['params_submitted']) && empty($_REQUEST['attendances_submitted'])) {
			if (!empty($_SESSION['attendance'])) {
				$this->_age_brackets = array_get($_SESSION['attendance'], 'age_brackets');
				$this->_statuses = array_get($_SESSION['attendance'], 'statuses');
				$this->_cohortids = array_get($_SESSION['attendance'], 'cohortids');
				$this->_show_photos =  array_get($_SESSION['attendance'], 'show_photos', FALSE);
				$this->_parallel_mode =  array_get($_SESSION['attendance'], 'parallel_mode', FALSE);
			}
		}

		if (!empty($_REQUEST['params_submitted']) || !empty($_REQUEST['attendances_submitted'])) {
			if (!empty($_REQUEST['age_brackets_all'])) unset($_REQUEST['age_brackets']);
			if (!empty($_REQUEST['statuses_all'])) unset($_REQUEST['statuses']);

			$this->_age_brackets = $_SESSION['attendance']['age_brackets'] = array_get($_REQUEST, 'age_brackets');
			$this->_statuses = $_SESSION['attendance']['statuses'] = array_get($_REQUEST, 'statuses');
			$this->_show_photos = $_SESSION['attendance']['show_photos'] = array_get($_REQUEST, 'show_photos', FALSE);
			$this->_parallel_mode = $_SESSION['attendance']['parallel_mode'] = array_get($_REQUEST, 'parallel_mode', FALSE);
		}

		foreach ($this->_cohortids as $id) {
			$this->_record_sets[$id] = new Attendance_Record_set($this->_attendance_date, $id, $this->_age_brackets, $this->_statuses);
			if ($this->_show_photos) $this->_record_sets[$id]->show_photos = TRUE;
		}

		if (!empty($_REQUEST['release'])) {
			foreach ($this->_record_sets as $set) {
				$set->releaseLock();
			}
		} else if (!empty($_REQUEST['params_submitted'])) {
			foreach ($this->_record_sets as $cohortid => $set) {
				if (!$set->checkAllowedDate()) {
					add_message(_('"Attendance for "').$set->getCohortName()._('" cannot be recorded on a "').date('l', strtotime($this->_attendance_date)), 'error');
					unset($this->_record_sets[$cohortid]);
					$this->_cohortids = array_diff($this->_cohortids, Array($cohortid));
					continue;
				}

				if (!$set->acquireLock()) {
					add_message(_('"Another user is currently recording attendance for "').$set->getCohortName()._('".  Please wait until they finish then try again."'), 'error');
					unset($this->_record_sets[$cohortid]);
					$this->_cohortids = array_diff($this->_cohortids, Array($cohortid));
				}
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
					if (!$set->haveLock() && !$set->acquireLock()) {
						add_message("Unfortunately your lock on '".$set->getCohortName()."' has expired and been acquired by another user.  Please wait until they finish and try again.", 'error');
					} else {
						if ($set->processForm($i)) {
							$set->save();
							if ((int)$set->congregationid) {
								Headcount::save('congregation', $this->_attendance_date, $set->congregationid, $_REQUEST['headcount']['congregation'][$set->congregationid]);
							} else {
								Headcount::save('person_group', $this->_attendance_date, $set->groupid, $_REQUEST['headcount']['group'][$set->groupid]);
							}
							$set->releaseLock();
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
		if (!empty($_POST['attendances_submitted'])) {
			$this->printConfirmation();
		} else if ($this->_cohortids && !empty($_REQUEST['params_submitted'])) {
			$this->printForm();
		} else {
			$this->printParams();
		}
	}

	private function printParams()
	{
		// STEP 1 - choose congregation and date
		?>
		<form method="get" class="well well-small clearfix form-inline">
			<input type="hidden" name="view" value="<?php echo ents($_REQUEST['view']); ?>" />
			<table class="attendance-config-table">
				<tr>
					<th><?php echo _('For');?></th>
					<td colspan="2" style="min-width: 240px">
						<?php
						Attendance_Record_Set::printPersonFilters($this->_age_brackets, $this->_statuses);
						?>
					</td>
				</tr>
				<tr>
					<th><?php echo _('In');?></th>

					<td class="valign-top fill-me">
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
					<th><?php echo _('On');?></th>
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
							<?php echo _('Show photos');?>
						</label>
					<?php
					if (!SizeDetector::isNarrow()) {
						?>
						<br />
						<label class="checkbox" title="Tabular format means that multiple groups/congregations will be shown as columns in a combined table">
							<input type="checkbox" name="parallel_mode" value="1" <?php if ($this->_parallel_mode) echo 'checked="checked"'; ?> />
							<?php echo _('Use tabular format');?>
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
			<button type="submit" class="btn attendance-config-submit"><?php echo _('Continue');?> <i class="icon-chevron-right"></i></button>
			<input type="hidden" name="params_submitted" value="1" />
		</form>
		<?php
	}

	private function printForm()
	{
		$_SESSION['enter_attendance_token'] = md5(time());
		// STEP 2 - enter attendances
		ob_start();
		?>
		<form method="post" class="attendance warn-unsaved" action="?view=attendance__record">
			<input type="hidden" name="attendance_date" value="<?php echo $this->_attendance_date; ?>" />
			<input type="hidden" name="show_photos" value="<?php echo $this->_show_photos; ?>" />
			<input type="hidden" name="parallel_mode" value="<?php echo $this->_parallel_mode; ?>" />
			<input type="hidden" name="enter_attendance_token" value="<?php echo $_SESSION['enter_attendance_token']; ?>" />
			<input type="hidden" name="attendances_submitted" value="1" />
			<?php
			print_hidden_fields(Array(
				'cohortids' => $this->_cohortids,
				'age_brackets' => $this->_age_brackets,
				'statuses' => $this->_statuses,
			));
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
			print_message(_("The parameters you have selected will list more persons ")
						. _("than your server can process.  Please narrow down your parameters, ")
						. _("or ask your server administrator to increase the PHP max_input_vars setting")
						. _(" (currently ").ini_get('max_input_vars').')', 'error');
		} else {
			ob_flush();
		}
	}

	private function printFormParallel()
	{
		$params = Array();
		if ($this->_age_brackets) $params['(age_bracketid'] = $this->_age_brackets;
		if ($this->_statuses) {
			$params['(status'] = $this->_statuses;
		} else {
			$params['!status'] = 'archived';
		}
		$totalPersons = Attendance_Record_Set::getPersonDataForCohorts($this->_cohortids, $params);
		$totalPrinted = 0;
		$cancelURL = build_url(Array('*' => NULL, 'view' => 'attendance__record', 'cohortids' => $this->_cohortids, 'attendance_date' => $this->_attendance_date, 'release' => 1));
		$dummy = new Person();

		?>
		<table class="table table-condensed table-auto-width valign-middle">
			<thead>
				<tr>
			<?php
			if (SizeDetector::isWide()) {
				?>
				<th>ID</th>
				<?php
			}
			if ($this->_show_photos) {
				?>
					<th>&nbsp;</th>
				</td>
				<?php
			}
			?>
					<th><?php echo _('Name');?></th>
			<?php
			if (SizeDetector::isWide()) {
				?>
				<th><?php echo _('Status');?></th>
				<?php
			}
			foreach ($this->_record_sets as $prefix => $set) {
				?>
				<th class="center"><?php echo $set->getCohortName();?> </th>
				<?php
			}
			if (SizeDetector::isWide()) {
				?>
				<th><?php echo _('Actions');?></th>
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
							<img style="width: 50px; max-width: 50px" src="?call=photo&personid=<?php echo (int)$personid; ?>" />
						</a>
					</td>
					<?php
				}
				?>
					<td><?php echo ents($detail['first_name'].' '.$detail['last_name']); ?></td>
				<?php
				if (SizeDetector::isWide()) {
					?>
					<td class=""><?php $dummy->printFieldValue('status', $detail['status']); ?></td>
					<?php
				}
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
						<a class="med-popup" tabindex="-1" href="?view=persons&personid=<?php echo $personid; ?>"><i class="icon-user"></i><?php echo _('View');?></a> &nbsp;
						<a class="med-popup" tabindex="-1" href="?view=_edit_person&personid=<?php echo $personid; ?>"><i class="icon-wrench"></i><?php echo _('Edit');?></a> &nbsp;
						<a class="med-popup" tabindex="-1" href="?view=_add_note_to_person&personid=<?php echo $personid; ?>"><i class="icon-pencil"></i><?php echo _('Add Note');?></a>
					</td>
					<?php
				}
				?>
				</tr>
				<?php
			}
			?>
				<tr class="headcount">
					<th class="right" colspan="<?php echo 1+(2*(int)SizeDetector::isWide())+(int)$this->_show_photos; ?>"><?php echo _('Total Headcount:');?> &nbsp;</th>
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
		<a href="<?php echo $cancelURL; ?>" class="btn">Cancel</a>
		<?php


		return $totalPrinted;
	}

	private function printFormSequential()
	{
		$cancelURL = build_url(Array('*' => NULL, 'view' => 'attendance__record', 'cohortids' => $this->_cohortids, 'attendance_date' => $this->_attendance_date, 'release' => 1));
		$totalPrinted = 0;
		foreach ($this->_record_sets as $i => $set) {
			if ((int)$set->congregationid) {
				$congregation = $GLOBALS['system']->getDBObject('congregation', (int)$set->congregationid);
			} else if ($set->groupid) {
				$group = $GLOBALS['system']->getDBObject('person_group', $set->groupid);
			} else {
				return;
			}
			$title = $set->getCohortName();
			$stats = $abs = Array();

			if ($set->statuses) {
				$options = Attendance_Record_Set::getStatusOptions();
				foreach ($set->statuses as $s) {
					$stats[] = $options[$s];
				}
			}
			if ($set->age_brackets) {
				$GLOBALS['system']->includeDBClass('person');
				$p = new Person();
				foreach ($set->age_brackets as $ab) {
					$p->setValue('age_bracketid', $ab);
					$abs[] = $p->getFormattedValue('age_bracketid');
				}
			}
			$title = implode(', ', $stats).' '.implode(',', $abs).' in '.$title;
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
							<?php echo _('Total headcount:');?>
							<?php $set->printHeadcountField(); ?>
						</p>
						<p class="span6 align-right nowrap">
							<input type="submit" class="btn" value="Save All Attendances" />
							<a href="<?php echo $cancelURL; ?>" class="btn">Cancel</a>
						</p>
					</div>
					<?php
				} else {
					?>
					<i><?php echo _('(No persons in this listing)');?></i>
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
				$group = $GLOBALS['system']->getDBObject('person_group', $set->groupid);
				$title = $group->getValue('name').' group';
			}
			echo '<h3>'.$title.'</h3>';
			$set->printStats();
		}
		?>
		<p><a href="?view=<?php echo $_REQUEST['view']; ?>"><i class="icon-pencil"></i><?php echo _('Record more attendances');?></a></p>
		<p><a href="?view=persons__reports"><i class="icon-list"></i><?php echo _('Analyse attendance using a person report');?></a></p>
		<?php
	}

}
