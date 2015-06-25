<?php
class View__Edit_Attendance extends View
{
	private $_person;
	private $_group;

	static function getMenuPermissionLevel()
	{
		return PERM_EDITATTENDANCE;
	}

	function processView()
	{
		$this->_person = new Person((int)$_REQUEST['personid']);
		if ((int)$_REQUEST['groupid']) $this->_group = new Person_Group((int)$_REQUEST['groupid']);

		if (!empty($_POST['attendances'])) {
			$this->_person->saveAttendance($_POST['attendances'], $_REQUEST['groupid']);
			redirect('persons', Array('personid' => $this->_person->id), 'attendance');

		}
	}

	function getTitle()
	{
		if ($this->_group) {
			return 'Edit attendance at '.$this->_group->getValue('name').' for '.$this->_person->toString();
		} else {
			return 'Edit congregational attendance for '.$this->_person->toString();
		}
	}


	function printView()
	{
		$attendances = $this->_person->getAttendance($_REQUEST['startdate'], $_REQUEST['enddate'], $_REQUEST['groupid']);
		?>
		<form method="post">
		<?php
		if (SizeDetector::isWide() && count($attendances) < 8) {
			// horizontal layout
			?>
			<table class="table table-bordered table-auto-width">
				<thead>
					<tr>
					<?php
					foreach ($attendances as $att) {
						?>
						<th>
							<?php echo format_date($att['date']); ?>
						</th>
						<?php
					}
					?>
					</tr>
				</thead>
				<tbody>
					<tr>
					<?php
					foreach ($attendances as $att) {
						?>
						<td>
							<?php print_widget(
									'attendances['.$att['date'].']',
									Array(
										'options' => Array('unknown' => '?', 'present' => 'Present', 'absent' => 'Absent'),
										'type' => 'select',
										'style' => 'colour-buttons',
									),
									$att['present'] ? 'present' : 'absent'
							); ?>
						</td>
						<?php
					}
					?>
					</tr>
				</tbody>
			</table>
			<?php

		} else {
			// vertical layout
			?>
			<table class="table table-bordered table-auto-width valign-middle">
				<tbody>
				<?php
				foreach ($attendances as $att) {
					?>
					<tr>
						<th><?php echo format_date($att['date']); ?></th>
						<td>
								<?php print_widget(
										'attendances['.$att['date'].']',
										Array(
											'options' => Array('unknown' => '?', 'present' => 'Present', 'absent' => 'Absent'),
											'type' => 'select',
											'style' => 'colour-buttons',
										),
										$att['present'] ? 'present' : 'absent'
								); ?>
						</td>
					</tr>
					<?php
				}
				?>
				</tbody>
			</table>
			<?php
		}
		?>
		<input type="submit" class="btn" value="Save" />
		<a class="btn" href="?view=persons&personid=<?php echo $this->_person->id; ?>#attendance">Cancel</a>
		</form>
		<?php
	}
}
?>
