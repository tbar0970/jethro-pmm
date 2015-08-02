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
		<form method="get" style="line-height: 200%" class="well well-small form-inline">
		<input type="hidden" name="view" value="<?php echo $_REQUEST['view']; ?>" />
		Show the attendance statistics for persons of each (current) status <br />
		between <?php print_widget('start_date', Array('type' => 'date'), $this->_start_date); ?>
		and  <?php print_widget('end_date', Array('type' => 'date'), $this->_end_date); ?>
		<input type="submit" class="btn" value="Go" />
		<p class="smallprint">Note: Any weeks where a person's attendance is left blank (neither present nor absent) are ignored when calculating attendance percentages.</p>
		</form>
		<?php
		
	}

	function _printResults()
	{
		$dummy_person = new Person();
		$this->status_map = $dummy_person->getStatusOptions();
		
		ob_start();
		$congs = $GLOBALS['system']->getDBObjectData('congregation', Array('!attendance_recording_days' => 0), 'OR', 'meeting_time');
		foreach ($congs as $id => $detail) {
			$this->printSet('c-'.$id, $detail['name']);
		}
		$cong_content = ob_get_clean();
		
		ob_start();
		$groups = $GLOBALS['system']->getDBObjectData('person_group', Array('!attendance_recording_days' => 0, 'is_archived' => 0), 'AND');
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
		<div class="span4">
		<table class="table table-bordered attendance-stats">
			<thead>
				<tr>
					<th colspan="4"><?php echo ents($cohortname); ?></th>
				</tr>
				<tr>
					<th>Status</th>
					<th title="Percentage of dates marked present rather than absent">Rate</th>
					<th class="present" title="Average number marked present per date">Avg&nbsp;P</th>
					<th class="absent" title="Average number marked absent per date">Avg&nbsp;A</th>
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
					<td style="width: 6ex; text-align: right"><?php echo $stats[$k]['rate'] ?>%</td>
					<td style="width: 6ex; text-align: right"><?php echo number_format($stats[$k]['avg_present'], 1) ?></td>
					<td style="width: 6ex; text-align: right"><?php echo number_format($stats[$k]['avg_absent'], 1) ?></td>
				</tr>
				<?php
			}
		}
		?>
				<tr class="thick-top-border">
					<th>Overall</th>
					<td style="width: 6ex; text-align: right"><?php echo $stats[NULL]['rate'] ?>%</td>
					<td style="width: 6ex; text-align: right"><?php echo number_format($stats[NULL]['avg_present'], 1) ?></td>
					<td style="width: 6ex; text-align: right"><?php echo number_format($stats[NULL]['avg_absent'], 1) ?></td>
				</tr>
				<tr class="headcount">
					<th colspan="2">
						Avg&nbsp;Headcount
					</th>
					<td class="right">
						<?php 
						$bits = explode('-', $cohortid);
						$hc = Headcount::fetchAverage($bits[0], $bits[1], $this->_start_date, $this->_end_date);
						if ($hc) {
							echo number_format($hc, 1);
						} else {
							echo 'N/A';
						}
						?>
					</td>
					<td colspan="2"></td>
				</tr>
			</tbody>
		</table>
		</div>
		<?php
		return TRUE;
	}
}
?>
