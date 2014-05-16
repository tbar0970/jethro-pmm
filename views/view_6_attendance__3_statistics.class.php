<?php
class View_Attendance__Statistics extends View
{
	var $_congregations = Array();
	var $_start_date;
	var $_end_date;

	static function getMenuPermissionLevel()
	{
		return PERM_VIEWATTENDANCE;
	}

	function getTitle()
	{
		return 'Attendance Statistics';
	}

	function processView()
	{
		$this->_congregations = array_get($_REQUEST, 'congregations', Array());
		$this->_start_date = process_widget('start_date', Array('type' => 'date'));
		$this->_end_date = process_widget('end_date', Array('type' => 'date'));
		if (is_null($this->_end_date)) {
			$this->_end_date = date('Y-m-d', strtotime(date('Y-m-01').' -1 day'));
		}
		if (is_null($this->_start_date)) {
			$this->_start_date =  date('Y-m-d', strtotime($this->_start_date.'  -3 months'));
		}

	}
	
	function printView()
	{
		$this->_printParams();

		$this->_printResults();
	}

	function _printParams()
	{
		?>
		<form method="get" style="line-height: 200%" class="well">
		<input type="hidden" name="view" value="<?php echo $_REQUEST['view']; ?>" />
		Show the average congregational attendance rate of persons of each status<br />
		between <?php print_widget('start_date', Array('type' => 'date'), $this->_start_date); ?>
		and  <?php print_widget('end_date', Array('type' => 'date'), $this->_end_date); ?>
		<table>
			<tr>
				<td>for members of </td>
				<td><?php print_widget('congregations', Array('type' => 'reference', 'references' => 'congregation', 'allow_multiple' => TRUE), $this->_congregations); ?></td>
			</tr>
		</table>
		<input type="submit" class="btn" value="Go" />
		<p class="smallprint">Note: Any weeks where a person's attendance is left blank (neither present nor absent) are ignored when calculating attendance percentages</p>
		</form>
		<?php
		
	}

	function _printResults()
	{
		if (empty($this->_congregations)) return;

		$GLOBALS['system']->includeDBClass('attendance_record_set');
		$stats = Attendance_Record_Set::getCongregationalAttendanceStats($this->_start_date, $this->_end_date, $this->_congregations);

		$GLOBALS['system']->includeDBClass('person');
		$dummy_person = new Person();
		$status_map = $dummy_person->getStatusOptions();
		?>

		<table class="table table-bordered table-auto-width">

		<?php
		foreach ($status_map as $k => $v) {
			if (isset($stats[$k])) {
				?>
				<tr>
					<th><?php echo ents($v); ?></th>
					<td style="width: 5ex"><?php echo $stats[$k] ?>%</td>
				</tr>
				<?php
			}
		}
		?>
		</table>
		<?php
	}
}
?>
