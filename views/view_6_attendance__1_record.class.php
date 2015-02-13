<?php
require_once 'db_objects/attendance_record_set.class.php';
class View_Attendance__Record extends View
{
	var $_record_sets = Array();
	var $_attendance_date = NULL;
	var $_age_bracket = NULL;
	var $_congregationids = Array();
	var $_groupid = NULL;

	static function getMenuPermissionLevel()
	{
		return PERM_EDITATTENDANCE;
	}

	function getTitle()
	{
		return 'Record Attendance';
	}

	function processView()
	{
		if (empty($_REQUEST['params_submitted']) && empty($_REQUEST['attendances_submitted'])) {
			if (!empty($_SESSION['attendance'])) {
				$this->_age_bracket = array_get($_SESSION['attendance'], 'age_bracket');
				$this->_congregationids = array_get($_SESSION['attendance'], 'congregationids');
				$this->_groupid = array_get($_SESSION['attendance'], 'groupid');
			}
			// Default to last Sunday, unless today is Sunday
			$this->_attendance_date = date('Y-m-d', ((date('D') == 'Sun') ? time() : strtotime('last Sunday')));
		}

		if (!empty($_REQUEST['params_submitted']) || !empty($_REQUEST['attendances_submitted'])) {
			$this->_attendance_date = process_widget('attendance_date', Array('type' => 'date'));
			$this->_age_bracket = $_SESSION['attendance']['age_bracket'] = array_get($_REQUEST, 'age_bracket');

			if ($_REQUEST['for_type'] == 'congregationid') {
				$cids = process_widget('congregationid', Array('type' => 'reference', 'references' => 'congregation', 'multiple' => true));
				foreach ($cids as $cid) {
					if ($cid && !in_array($cid, $this->_congregationids)) {
						$this->_congregationids[] = $cid;
						$this->_record_sets[] = new Attendance_Record_Set($this->_attendance_date, $this->_age_bracket, $cid, 0);
					}
				}
				$_SESSION['attendance']['congregationids'] = $this->_congregationids;
				$_SESSION['attendance']['groupid'] = null;
			} else {
				$this->_groupid = process_widget('groupid', Array('type' => 'reference', 'references' => 'person_group', 'allow_empty' => false));
				if ($this->_groupid) {
					$this->_record_sets[] = new Attendance_Record_Set($this->_attendance_date, $this->_age_bracket, NULL, $this->_groupid);
					$_SESSION['attendance']['congregationids'] = Array();
					$_SESSION['attendance']['groupid'] = $this->_groupid;
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
					$set->processForm($i);
					$set->save();
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
			// STEP 1 - choose congregation and date
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
							), $this->_age_bracket);
							?>
						</td>
					</tr>
					<tr>
						<th rowspan="2">In</th>
						<td class="valign-top">
							<label class="radio">
								<input type="radio" name="for_type" 
									value="congregationid" id="for_type_congregation" 
									data-toggle="enable" data-target="#congregationchooser select"
									<?php if (empty($this->_groupid)) echo 'checked="checked"'; ?>
								>
								Congregation(s) &nbsp;
							</label>
						</td>
						<td class="fill-me">
							<table id="congregationchooser" class="expandable table-condensed no-padding" cellspacing="0">
						<?php
						$congs = $this->_congregationids ? $this->_congregationids : Array(0);
						foreach ($congs as $congid) {
							?>
							<tr>
								<td>
								<?php print_widget('congregationid[]', Array('type' => 'reference', 'references' => 'congregation', 'order_by' => 'name', 'allow_empty' => true, 'empty_text' => '-- Choose --'), $congid); ?>
								</td>
							</tr>
							<?php
						}
						?>
							</table>
						</td>
					</tr>
					<tr>
						<td>
							<label class="radio">
								<input type="radio" name="for_type" 
									value="groupid" id="for_type_group"
									data-toggle="enable" data-target="#groupchooser select" 
									<?php if (!empty($this->_groupid)) echo 'checked="checked"'; ?>
								>
								Group
							</label>
						</td>
						<td class="fill-me" id="groupchooser">
							<?php print_widget('groupid', Array('type' => 'reference', 'references' => 'person_group', 'filter' => Array('can_record_attendance' => '1', 'is_archived' => 0)), $this->_groupid); ?>
						</td>
					</tr>
					<tr>
						<th>On</th>
						<td colspan="2">
							<?php
							print_widget('attendance_date', Array('type' => 'date'), $this->_attendance_date); ?>
						</td>
					</tr>
				</table>
				<button type="submit" class="btn attendance-config-submit">Continue <i class="icon-chevron-right"></i></button>
				<input type="hidden" name="params_submitted" value="1" />
			</form>
			<?php

		} else if (empty($_POST['attendances_submitted'])) {
			$_SESSION['enter_attendance_token'] = md5(time());
			// STEP 2 - enter attendances
			?>
			<form method="post" class="attendance warn-unsaved" action="?view=attendance__record">
				<input type="hidden" name="for_type" value="<?php echo ents($_REQUEST['for_type']); ?>" />
				<input type="hidden" name="attendance_date" value="<?php echo $this->_attendance_date; ?>" />
				<input type="hidden" name="age_bracket" value="<?php echo $this->_age_bracket; ?>" />
				<input type="hidden" name="enter_attendance_token" value="<?php echo $_SESSION['enter_attendance_token']; ?>" />
				<input type="hidden" name="attendances_submitted" value="1" />

				<p class="visible-desktop smallprint">For greatest speed, press P for present and A for absent.  The cursor will automatically progress to the next person.  To go back, use the arrow keys.</p>
				<?php
				foreach ($this->_record_sets as $i => $set) {
					if ((int)$set->congregationid) {
						?>
						<input type="hidden" name="congregationid[]" value="<?php echo $set->congregationid; ?>" />
						<?php
						$congregation = $GLOBALS['system']->getDBObject('congregation', (int)$set->congregationid);
						$title = '"'.$congregation->getValue('name').'" congregation, '.date('j M Y', strtotime($this->_attendance_date));
					} else if ($set->groupid) {
						$group =& $GLOBALS['system']->getDBObject('person_group', $set->groupid);
						$title = '"'.$group->getValue('name').'" group, '.date('j M Y', strtotime($this->_attendance_date));
						?>
						<input type="hidden" name="groupid" value="<?php echo $set->groupid; ?>" />
						<?php
					} else {
						return;
					}
					if (strlen($set->age_bracket)) {
						$GLOBALS['system']->includeDBClass('person');
						$p = new Person();
						$p->setValue('age_bracket', $set->age_bracket);
						$title = $p->getFormattedValue('age_bracket').' in '.$title;
					}
					?>
					<h3><?php echo ents($title); ?></h3>
					<div class="align-right width-really-auto">
						<?php
						$set->printForm($i);
						?>
						<input type="submit" class="btn" value="Save All Attendances" />
						<a href="?view=attendance__record" class="btn">Cancel</a>
					</div>
					<?php

				}
				?>
			</form>
			<?php

		} else {
			// STEP 3 - confirmation
			foreach ($this->_record_sets as $set) {
				if ($set->congregationid) {
					$congregation = $GLOBALS['system']->getDBObject('congregation', (int)$set->congregationid);
					$title = 'Attendance recorded for the "'.$congregation->getValue('name').'" congregation on '.date('j M Y', strtotime($this->_attendance_date));
				} else {
					$group =& $GLOBALS['system']->getDBObject('person_group', $set->groupid);
					$title = 'Attendance recorded for the "'.$group->getValue('name').'" group on '.date('j M Y', strtotime($this->_attendance_date));
				}
				echo '<h4>'.$title.'</h4>';
				$set->printStats();
			}
			?>
			<p><a href="?view=<?php echo $_REQUEST['view']; ?>"><i class="icon-pencil"></i>Record more attendances</a></p>
			<p><a href="?view=persons__reports"><i class="icon-list"></i>Analyse attendance using a person report</a></p>
			<?php
		}
	}
}
?>
