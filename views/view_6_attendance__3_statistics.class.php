<?php
class View_Attendance__Statistics extends View
{
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
		<form method="get" style="line-height: 200%" class="well form-inline">
		<input type="hidden" name="view" value="<?php echo $_REQUEST['view']; ?>" />
		Show the average attendance rate, at all congregations and groups, for persons of each (current) status <br />
		between <?php print_widget('start_date', Array('type' => 'date'), $this->_start_date); ?>
		and  <?php print_widget('end_date', Array('type' => 'date'), $this->_end_date); ?>
		<input type="submit" class="btn" value="Go" />
		<p class="smallprint">Note: Any weeks where a person's attendance is left blank (neither present nor absent) are ignored when calculating attendance percentages</p>
		</form>
		<?php
		
	}

	function _printResults()
	{
		$dummy_person = new Person();
		$this->status_map = $dummy_person->getStatusOptions();
		
		ob_start();
		$congs = $GLOBALS['system']->getDBObjectData('congregation', Array(), 'OR', 'meeting_time');
		foreach ($congs as $id => $detail) {
			$this->printSet('c-'.$id, $detail['name']);
		}
		$cong_content = ob_get_clean();
		
		ob_start();
		$groups = $GLOBALS['system']->getDBObjectData('person_group', Array('can_record_attendance' => 1, 'is_archived' => 0), 'AND');
		foreach ($groups as $id => $detail) {
			$this->printSet('g-'.$id, $detail['name']);
		}
		$group_content = ob_get_clean();
	
		if ($cong_content) {
			?>
			<h3>Congregations</h3>
			<div class="row">
			<?php echo $cong_content; ?>
			</div>
			<?php
		}
		
		if ($group_content) {
			?>
			<h3>Groups</h3>
			<div class="row">
			<?php echo $group_content; ?>
			</div>
			<?php
		}

	}
	
	private function printSet($cohortid, $cohortname)
	{
		$stats = Attendance_Record_Set::getStatsForPeriod($this->_start_date, $this->_end_date, $cohortid);
		if (empty($stats)) {
			return FALSE;
		}
		?>
		<div class="span3">
		<table class="table table-bordered">
			<thead>
				<tr>
					<th colspan="2"><?php echo ents($cohortname); ?></th>
				</tr>	
			</thead>
			<tbody>
		<?php
		if ($cohortid[0] == 'g') {
			list($map, $default) = Person_Group::getMembershipStatusOptionsAndDefault();
		} else {
			$map = $this->status_map;
		}
		foreach ($map as $k => $v) {
			if (isset($stats[$k])) {
				?>
				<tr>
					<th><?php echo ents($v); ?></th>
					<td style="width: 6ex; text-align: right"><?php echo $stats[$k] ?>%</td>
				</tr>
				<?php
			}
		}
		?>
			</tbody>
		</table>
		</div>
		<?php
		return TRUE;
	}
}
?>
