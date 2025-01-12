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
			return _('Edit attendance at ').$this->_group->getValue('name')._(' for ').$this->_person->toString();
		} else {
			return _('Edit congregational attendance for ').$this->_person->toString();
		}
	}


	function printView()
	{
		$attendances = $this->_person->getAttendance($_REQUEST['startdate'], $_REQUEST['enddate'], $_REQUEST['groupid']);
		$map = Array(1 => 'present', '0' => 'absent', '' => 'unknown')
		?>
		<form method="post">
			<table class="table table-bordered table-condensed table-auto-width">
				<thead>
					<tr>
					<?php
					foreach ($attendances as $att) {
						?>
						<th>
							<?php echo format_date($att['date'], FALSE); ?>
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
										'options' => Array('present' => 'Present', 'unknown' => '?', 'absent' => 'Absent'),
										'type' => 'select',
										'style' => 'colour-buttons',
										'class' => 'vertical',
									),
									$map[$att['present']]
							); ?>
						</td>
						<?php
					}
					?>
					</tr>
				</tbody>
			</table>
			<input type="submit" class="btn" value="Save" />
			<a class="btn" href="?view=persons&personid=<?php echo $this->_person->id; ?>#attendance">Cancel</a>
		</form>
		<?php
	}
}