<?php
class View_Attendance__Display extends View
{
	private $age_bracket = null;
	private $congregationids = Array();
	private $groupid = null;
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
				$this->congregationids = array_get($_SESSION['attendance'], 'congregationids');
				$this->groupid = array_get($_SESSION['attendance'], 'groupid');
				$this->start_date = array_get($_SESSION['attendance'], 'start_date', date('Y-m-d', strtotime('-7 weeks')));
				$this->end_date = array_get($_SESSION['attendance'], 'end_date');
			} else {
				$this->start_date = date('Y-m-d', strtotime('-7 weeks'));
			}

		} else {
			$this->age_bracket = $_SESSION['attendance']['age_bracket'] = $_REQUEST['age_bracket'];
			if ($this->age_bracket != '') $this->age_bracket = (int)$this->age_bracket;
			if (!empty($_REQUEST['congregationid']) && is_array($_REQUEST['congregationid'])) {
				foreach ($_REQUEST['congregationid'] as $congid) {
					$this->congregationids[] = (int)$congid;
				}
				$_SESSION['attendance']['congregationids'] = $this->congregationids;
			}
			$this->groupid = $_SESSION['attendance']['groupid'] = array_get($_REQUEST, 'groupid');
			$this->start_date = $_SESSION['attendance']['start_date'] = process_widget('start_date', Array('type' => 'date'));
			$this->end_date = $_SESSION['attendance']['end_date'] = process_widget('end_date', Array('type' => 'date'));
		}

		// Make sure there are no empty congregation IDs, except the first one
		for ($i = count($this->congregationids); $i > 0 ; $i--) {
			if (empty($this->congregationids[$i])) unset($this->congregationids[$i]);
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
					<th rowspan="2">at</th>
					<td class="valign-top">
						<label class="radio">
							<input type="radio" name="for_type" 
								value="congregationid" id="for_type_congregation" 
								data-toggle="enable" data-target="#congregationchooser select"
								<?php if (empty($this->groupid)) echo 'checked="checked"'; ?>
							>
							Congregation(s) &nbsp;
						</label>
					</td>
					<td class="fill-me">
						<table id="congregationchooser" class="expandable table-condensed no-padding" cellspacing="0">
						<?php
						$congs = $this->congregationids ? $this->congregationids : Array(0);
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
								<?php if (!empty($this->groupid)) echo 'checked="checked"'; ?>
							>
							Group
						</label>
					</td>
					<td class="fill-me" id="groupchooser">
						<?php print_widget('groupid', Array('type' => 'reference', 'references' => 'person_group', 'filter' => Array('can_record_attendance' => '1', 'is_archived' => 0)), $this->groupid); ?>
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
		list ($dates, $attendances) = Attendance_Record_Set::getAttendances($this->congregationids, $this->groupid, $this->age_bracket, $this->start_date, $this->end_date);
		if (empty($attendances)) {
			?>
			<p><i>No attendance records found.  Try adjusting your criteria.</i></p>
			<?php
			return;
		}

		$letters = Array(0 => 'A', 1 => 'P', '?' => '?');
		$classes = Array(0 => 'absent', 1 => 'present', '?' => 'unknown');
		$dummy = new Person();
		?>
		<form method="post" action="" class="bulk-person-action">
		<table class="table table-hover table-auto-width nowrap table-bordered table-condensed">
			<thead>
				<tr>
					<th>Last Name</th>
					<th>First Name</th>
					<th><?php echo $this->groupid ? 'Membership Status' : 'Status'; ?></th>
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
				<tr>
					<td><?php echo ents($record['last_name']); ?></td>
					<td><?php echo ents($record['first_name']); ?></td>
					<td>
						<?php
						if ($this->groupid) {
							echo ents($record['membership_status']);
						} else {
							$dummy->printFieldValue('status', $record['status']);
						}
						?>
					</td>
				<?php
				foreach ($dates as $date) {
					$letter = $letters[array_get($record, $date, '?')];
					$class = $classes[array_get($record, $date, '?')];
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
		</table>
		<?php
		include 'templates/bulk_actions.template.php';
		?>
		</form>
		<?php
	}
}
?>
