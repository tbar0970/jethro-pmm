<?php
class View_Attendance__Display extends View
{
	private $age_brackets = null;
	private $statuses = null;
	private $cohortids = Array();
	private $start_date = null;
	private $end_date = null;
	private $format = 'sequential';
	private $order = NULL;

	private $letters = Array(0 => 'A', 1 => 'P', '' => '?', '-1' => '');
	private $classes = Array(0 => 'absent', 1 => 'present', '' => 'unknown', '-1' => '');

	static function getMenuPermissionLevel()
	{
		return PERM_VIEWATTENDANCE;
	}

	function getTitle()
	{
		return _('Display attendance');
	}

	function processView()
	{
		if (empty($_REQUEST['params_submitted'])) {
			if (!empty($_SESSION['attendance'])) {
				$this->age_brackets = array_get($_SESSION['attendance'], 'age_brackets', Array());
				$this->statuses = array_get($_SESSION['attendance'], 'statuses', Array());
				$this->cohortids = array_get($_SESSION['attendance'], 'cohortids');
				$this->start_date = array_get($_SESSION['attendance'], 'start_date', date('Y-m-d', strtotime('-7 weeks')));
				$this->end_date = array_get($_SESSION['attendance'], 'end_date');
				$this->format = array_get($_SESSION['attendance'], 'format');
				$this->order =  array_get($_SESSION['attendance'], 'order', FALSE);
			} else {
				$this->start_date = date('Y-m-d', strtotime('-7 weeks'));
			}

		} else {
			if (!empty($_REQUEST['age_brackets_all'])) unset($_REQUEST['age_brackets']);
			if (!empty($_REQUEST['statuses_all'])) unset($_REQUEST['statuses']);
			$this->age_brackets = $_SESSION['attendance']['age_brackets'] = array_get($_REQUEST, 'age_brackets', Array());
			$this->statuses = $_SESSION['attendance']['statuses'] = array_get($_REQUEST, 'statuses', Array());

			if (!empty($_REQUEST['cohortids']) && is_array($_REQUEST['cohortids'])) {
				foreach ($_REQUEST['cohortids'] as $id) {
					$this->cohortids[] = $id;
				}
				$_SESSION['attendance']['cohortids'] = $this->cohortids;
			}
			$this->start_date = $_SESSION['attendance']['start_date'] = process_widget('start_date', Array('type' => 'date'));
			$this->end_date = $_SESSION['attendance']['end_date'] = process_widget('end_date', Array('type' => 'date'));
			$this->format = $_SESSION['attendance']['format'] = $_REQUEST['format'];
			$this->order = $_SESSION['attendance']['order'] = $_REQUEST['order'];
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
			if (empty($this->cohortids)) {
				print_message("Please choose a congregation or group", 'error');
			} else {
				if ($this->format == 'sequential') {
					$this->_printResultsSequential();
				} else {
					$this->_printResultsTabular();
				}
			}
		}
	}

	private function _printParams()
	{
		?>
		<form method="get" class="well well-small clearfix form-inline no-print">
			<input type="hidden" name="view" value="<?php echo $_REQUEST['view']; ?>" />
			<table class="attendance-config-table">
				<tr>
					<th><?php echo _('For');?></th>
					<td colspan="2" style="min-width: 240px">
						<?php
						Attendance_Record_Set::printPersonFilters($this->age_brackets, $this->statuses);
						?>
					</td>
				</tr>
				<tr>
					<th><?php echo _('In');?></th>
					<td class="valign-top fill-me">
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
					<th><?php echo _('Between');?></th>
					<td colspan="2">
						<?php print_widget('start_date', Array('type' => 'date'), $this->start_date); ?>
					</td>
				</tr>
				<tr>
					<th><?php echo _('And');?></th>
					<td colspan="2">
						<?php print_widget('end_date', Array('type' => 'date'), $this->end_date); ?>
					</td>
				</tr>
				<tr>
					<th>Order&nbsp;by</th>
					<td>
						<?php print_widget('order', Array('type'=>'select', 'options' => Attendance_Record_Set::getOrderOptions()), $this->order); ?>
					</td>
				</tr>
				<tr>
					<th><?php echo _('Format');?></th>
					<td>
						<?php
						print_widget(
							'format',
							Array(
								'type' => 'select',
								'options' => Array(
									'sequential' => 'Sequential',
									'tabular' => 'Tabular',
									'totals' => 'Date Totals',
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
		if ($GLOBALS['user_system']->havePerm(PERM_RUNREPORT)) {
			?>
			<div class="alert alert-info"><small><i class="icon-info-sign"></i> You can also use <a href="?view=persons__reports">Person Reports</a> to show and analyze attendance</div>
			<?php
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
			echo _(' Congregation');
		} else {
			$group = $GLOBALS['system']->getDBObject('person_group', $groupid);
			$group->printFieldValue('name');
			echo _(' Group');
		}
		echo '</h3>';
		$params = Array();
		if ($this->age_brackets) $params['(age_bracketid'] = $this->age_brackets;
		if ($this->statuses) $params['(status'] = $this->statuses;
		
		foreach ($this->statuses as $status) {
			if ($status && ($status[0] == 'g') && empty($groupid)) {
				print_message(_('Congregational attendance cannot be filtered by a group membership status. Please clear the status filter to display attendance for this congregation.'), 'error');
				return;
			}
		}
		list ($dates, $attendances, $totals) = Attendance_Record_Set::getAttendances((array)$congid, $groupid, $params, $this->start_date, $this->end_date, $this->order);
		if (empty($dates)) {
			?>
			<p><i><?php echo _('No attendance records found.  Try adjusting your criteria.');?></i></p>
			<?php
			return;
		}
		$headcounts = Headcount::fetchRange(($congid ? 'congregation' : 'person_group'), $congid ? $congid : $groupid, $this->start_date, $this->end_date);
		$dummy = new Person();
		?>
		<form method="post" action="" class="bulk-person-action" enctype="multipart/form-data">
		<table class="table table-hover table-auto-width nowrap table-bordered table-condensed">
			<thead>
				<tr>
					<th><?php echo _('Name');?></th>
					<th><?php echo $groupid ? 'Membership Status' : 'Status'; ?></th>
				<?php
				foreach ($dates as $date) {
					?>
					<th><?php echo date('j M', strtotime($date)); ?></th>
					<?php
				}
				?>
					<th class="action-cell"><?php echo _('Actions');?></th>
					<th class="narrow selector form-inline"><input type="checkbox" class="select-all" title="Select all" /></th>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ($attendances as $personid => $record) {
				$class = in_array($record['status'], Person_Status::getArchivedIDs()) ? 'class="archived"' : '';
				?>
				<tr data-personid="<?php echo $personid; ?>" <?php echo $class; ?>>
					<td><?php echo ents($record['first_name'].' '.$record['last_name']); ?></td>
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
					$raw = array_get($record, $date, ''); // might have a trailing * to indicate planned absence
					$char = substr($raw, 0, 1);
					$letter = $this->letters[$char];
					$class = $this->classes[$char];
					if ($raw == '0*') {
						echo '<td class="'.$class.'" title="(Planned absence)">('.$letter.')</td>';
					} else {
						echo '<td class="'.$class.'">'.$letter.'</td>';
					}
				}
				$this->_printActionsAndSelector($personid);
				?>

				</tr>
				<?php
			}
			?>
			</tbody>
			<tfoot class="attendance-stats">
			<?php
			if (empty($params)) {
				?>
				<tr class="headcount">
					<th colspan="2"><?php echo _('Total Headcount'); ?></th>
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
				<?php
			}
			?>
				<tr class="present">
					<th colspan="2"><?php echo empty($params) ? 'Total Present' : 'Present'; ?></th>
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
					<th colspan="2"><?php echo empty($params) ? 'Total Absent' : 'Absent'; ?></th>
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
			<?php
			if (empty($params)) {
				?>
				<tr class="extras">
					<th colspan="2">Extras</th>
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
				<?php
			}
			?>
			</tfoot>
		</table>
		<?php
		include 'templates/bulk_actions.template.php';
		?>
		</form>
		<?php
	}
	
	private function _printActionsAndSelector($personid)
	{
		?>
		<td class="narrow action-cell">
			<a class="med-popup" href="?view=persons&personid=<?php echo $personid; ?>"><i class="icon-user"></i><?php echo _('View');?></a> &nbsp;
		<?php
		if ($GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
			?>
			<a class="med-popup" href="?view=_edit_person&personid=<?php echo $personid; ?>"><i class="icon-wrench"></i><?php echo _('Edit');?></a> &nbsp;
			<?php
		}
		if ($GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
			?>
			<a class="med-popup" href="?view=_add_note_to_person&personid=<?php echo $personid; ?>"><i class="icon-pencil"></i><?php echo _('Add Note');?></a>
			<?php
		}
		?>
		</td>
		<td class="selector"><input name="personid[]" type="checkbox" value="<?php echo $personid; ?>" /></td>
		<?php
	}

	private function _printResultsTabular()
	{
		$GLOBALS['system']->includeDBClass('attendance_record_set');
		$GLOBALS['system']->includeDBClass('person');
		$dummy = new Person();

		$params = Array();
		if ($this->age_brackets) $params['(age_bracketid'] = $this->age_brackets;
		if ($this->statuses) $params['(status'] = $this->statuses;
		
		$all_persons = Attendance_Record_Set::getPersonDataForCohorts($this->cohortids, $params, $this->order);
		$all_dates = $all_attendances = $all_totals = $all_headcounts = Array();
		if (!empty($this->cohortids)) {
			foreach ($this->cohortids as $cohortid) {
				$congid = $groupid = NULL;
				list($type, $id) = explode('-', $cohortid);
				if ($type == 'c') $congid = $id;
				if ($type == 'g') $groupid = $id;
				list ($cdates, $cattendances, $ctotals) = Attendance_Record_Set::getAttendances((array)$congid, $groupid, $params, $this->start_date, $this->end_date);
				if (empty($params)) {
					// Headcounts and extras don't make sense when we are only viewing a segment of the total attendance
					$hc = Headcount::fetchRange(($congid ? 'congregation' : 'person_group'), $congid ? $congid : $groupid, $this->start_date, $this->end_date);
					foreach ($hc as $date => $c) $all_headcounts[$date][$cohortid] = $c;
				}
				$all_dates = array_merge($all_dates, $cdates);
				foreach ($ctotals as $date => $t) $all_totals[$date][$cohortid] = $t;
				foreach ($cattendances as $personid => $cat) {
					$all_persons[$personid]['cohortids'][] = $cohortid;
					$all_persons[$personid]['group_status'] = '';
					if (isset($cat['membership_status'])) {
						$all_persons[$personid]['group_status'] = $cat['membership_status'];
					}
					foreach ($cat as $k => $v) {
						if (!in_array($k, Array('first_name', 'last_name', 'membership_status', 'status'))) {
							$all_attendances[$personid][$cohortid][$k] = $v;
						}
					}
				}
			}
		}
		$all_dates = array_unique($all_dates);
		?>
		<form method="post" action="" class="bulk-person-action" enctype="multipart/form-data">
		<table class="table table-hover table-condensed table-auto-width valign-middle table-bordered parallel-attendance-report">
			<thead>
				<tr>
					<th <?php if ($this->format != 'totals') echo 'rowspan="2"'; ?>><?php echo _('Name');?>
            		<input type="hidden" name="data_type" value="attendance_tabular">

					</th>
				<?php
				if (SizeDetector::isWide()) {
					?>
					<th <?php if ($this->format != 'totals') echo 'rowspan="2"'; ?>><?php echo _('Status');?></th>
					<?php
				}
				if ($this->format == 'totals') {
					$colspan = 1;
				} else {
					$colspan = count($this->cohortids);
				}
				foreach ($all_dates as $date) {
					$classes = 'center nowrap';
					if ($this->format != 'totals') $classes .= ' new-cohort';
					?>
					<th class="<?php echo $classes; ?>" colspan="<?php echo $colspan; ?>"><?php echo format_date($date); ?>
					<input type="hidden" name="dates[]" value="<?php echo $date; ?>">
					</th>
					<?php
				}
				?>
					<th <?php if ($this->format != 'totals') echo 'rowspan="2"'; ?>></th>
					<th class="narrow selector form-inline" rowspan="2"><input type="checkbox" class="select-all" title="<?php echo _('Select all');?>" /></th>
				</tr>

			<?php
			if ($this->format != 'totals') {
				?>
				<tr>
				<?php
				$firstdate = TRUE;
				foreach ($all_dates as $date) {
					$first = TRUE;
					foreach ($this->cohortids as $cohortid) {
						$name = '';
						list ($type, $id) = explode('-', $cohortid);
						if ($type == 'c') {
							$congregation = $GLOBALS['system']->getDBObject('congregation', $id);
							$name = $congregation->getValue('name');
						} else if ($type == 'g') {
							$group = $GLOBALS['system']->getDBObject('person_group', $id);
							$name = $group->getValue('name');
						}
						$name_bits = explode(' ', $name);
						$short = reset($name_bits);
						if ((strlen($short) > 5) && !preg_match('/[0-9]/', $short)) $short = substr($short, 0, 3).'…';
						$class = $first ? 'new-cohort' : '';
						echo '<th class="nowrap '.$class.'" title="'.ents($name).'">'.ents($short);
						if ($firstdate) {
							echo '<input type="hidden" name="groups[]" value="'.$name.'">';
						}
						echo '</th>';
						$first = FALSE;
					}
					$firstdate = FALSE;
				}
				?>
				</tr>
				<?php
			}
			?>
			</thead>
			<tbody>
			<?php

			foreach ($all_persons as $personid => $details) {
				if (!isset($all_attendances[$personid])) continue;
                                $letters = '';
				$class = in_array($details['status'], Person_Status::getArchivedIDs()) ? 'class="archived"' : '';
				?>
				<tr <?php echo $class; ?>>
					<td class="nowrap">
						<?php echo ents($details['first_name'].' '.$details['last_name']); ?>
					</td>
				<?php
				if (SizeDetector::isWide()) {
					?>
					<td><?php $dummy->printFieldValue('status', $details['status']); ?></td>
					<?php
				}
				foreach ($all_dates as $date) {
					$first = TRUE;
					if ($this->format == 'totals') {
						$score = '';
						foreach ($this->cohortids as $cohortid) {
							$catt = array_get($all_attendances[$personid], $cohortid, Array());
							$x = (array_get($catt, $date, ''));
							if (strlen($x)) $score = (int)$score + rtrim($x, '*'); // The '*' suffix indicates a planned absence
						}
						$class = $this->classes[$score > 0 ? 1 : $score];
						if ($score === '') $score = '?';
						echo '<td class="center '.$class.'">'.$score.'</td>';
					} else {
						foreach ($this->cohortids as $cohortid) {
							if (!in_array($cohortid, array_get($all_persons[$personid], 'cohortids', Array()))) {
								$class = 'disabled';
								$letter = '';
								$pa = FALSE;
							} else {
								$catt = array_get($all_attendances[$personid], $cohortid, Array());
								$v = array_get($catt, $date, '');
								$pa = ($v == '0*'); // planned absence
								$v = substr($v, 0, 1);
								$letter = $this->letters[$v];
								$class = $this->classes[$v];
							}
							if ($first) $class .= ' new-cohort';
							if ($pa) {
								echo '<td class="'.$class.'" title="(Planned absence)">('.$letter.')';
							} else {
								echo '<td class="'.$class.'">'.$letter;
							}
							$first = FALSE;
                                                        $letters .= $letter.',';
						}
					}
				}
				$group_status = ' ';
				if (isset($details['group_status'])) {
					$group_status = $details['group_status'];
				}
                echo "\n".'<input type="hidden" name="data2['.$personid.'][]" value="'.$letters.';'.$group_status.'">';
				$this->_printActionsAndSelector($personid);
				?>	
				</tr>
				<?php
			}

			?>
			</tbody>
			<?php
		if ($this->format != 'totals') { // headcounts don't make sense when we collapse groups down into totals
			$colspan = SizeDetector::isWide() ? 'colspan="2"' : '';
			?>
			<tfoot class="attendance-stats">
			<?php
			if (empty($params)) {
				?>
				<tr class="headcount">
					<th <?php echo $colspan; ?>><?php echo _('Total Headcount');?>
				<?php
				echo '<input type="hidden" name="tables[]" value="headcount"></th>';
				foreach ($all_dates as $date) {
					$hc = array_get($all_headcounts, $date, Array());
					foreach ($this->cohortids as $cohortid) {
						echo '<td>'.array_get($hc, $cohortid, '').'<input type="hidden" name="headcount[]" value="'.array_get($hc, $cohortid, '').'"></td>';
					}
				}
				?>
					<td colspan="2">&nbsp;</td>
				</tr>
				<?php
			}
			?>
				<tr class="present">
					<th <?php echo $colspan; ?>><?php echo empty($params) ? _('Total Present') : _('Present'); ?>
				<?php
				echo '<input type="hidden" name="tables[]" value="present"></th>';
				foreach ($all_dates as $date) {
					$tots = array_get($all_totals, $date, Array());
					foreach ($this->cohortids as $cohortid) {
						echo '<td>'.array_get(array_get($tots, $cohortid, Array()), 1, 0).'<input type="hidden" name="present[]" value="'.array_get(array_get($tots, $cohortid, Array()), 1, 0).'"></td>';
					}
				}
				?>
					<td colspan="2">&nbsp;</td>
				</tr>
				<tr class="absent">
					<th <?php echo $colspan; ?>><?php echo empty($params) ? _('Total Absent') : _('Absent'); ?>
				<?php
				echo '<input type="hidden" name="tables[]" value="absent"></th>';
				foreach ($all_dates as $date) {
					$tots = array_get($all_totals, $date, Array());
					foreach ($this->cohortids as $cohortid) {
						echo '<td>'.array_get(array_get($tots, $cohortid, Array()), 0, 0).'<input type="hidden" name="absent[]" value="'.array_get(array_get($tots, $cohortid, Array()), 0, 0).'"></td>';
					}
				}
				?>
					<td colspan="2">&nbsp;</td>
				</tr>
			<?php
			if (empty($params)) {
				?>
				<tr class="extras">
					<th <?php echo $colspan; ?>><?php echo _('Extras');?>
				<?php
				echo '<input type="hidden" name="tables[]" value="extra"></th>';
				foreach ($all_dates as $date) {
					$tots = array_get($all_totals, $date, Array());
					$hc = array_get($all_headcounts, $date, Array());
					foreach ($this->cohortids as $cohortid) {
						$present = array_get(array_get($tots, $cohortid, Array()), 1, 0);
						$headcount = array_get($hc, $cohortid, NULL);
						?>
						<td>
							<?php 
							if ($headcount) {
								$count = $headcount - $present;
								echo $count;
							} else {
								$count = 0;
							}
							echo '<input type="hidden" name="extra[]" value="'.$count.'"></td>';
					}
				}
				?>
					<td colspan="2">&nbsp;</td>
				</tr>
				<?php
			}
			?>
			</tfoot>
			<?php
		}
		?>
		</table>
		<?php
		$merge_type_person_attendance = TRUE;
		include 'templates/bulk_actions.template.php';
		?>
		</form>
		<?php
		if ($GLOBALS['user_system']->havePerm(PERM_RUNREPORT)) {
			?>
			<div class="alert alert-info"><small><i class="icon-info-sign"></i> You can also use <a href="?view=persons__reports">Person Reports</a> to show and analyze attendance</div>
			<?php
		}
	}
}