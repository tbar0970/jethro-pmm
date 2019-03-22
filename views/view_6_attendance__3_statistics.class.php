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
		return _('Attendance Statistics');
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
		<?php echo _('Show the attendance statistics for persons of each (current) status');?> <br />
		<?php echo _('between');?> <?php print_widget('start_date', Array('type' => 'date'), $this->_start_date); ?>
		<?php echo _('and');?>  <?php print_widget('end_date', Array('type' => 'date'), $this->_end_date); ?>
		<input type="submit" class="btn" value="Go" />
		<p class="smallprint"><?php echo _('Note: Any weeks where a person attendance is left blank (neither present nor absent) are ignored when calculating attendance percentages.');?></p>
		</form>
		<?php

	}

	function _printResults()
	{
		$dummy_person = new Person();
		$this->status_map = $dummy_person->getStatusOptions();

		ob_start();
		$printed = 0;
		$congs = $GLOBALS['system']->getDBObjectData('congregation', Array('!attendance_recording_days' => 0), 'OR', 'meeting_time');
		$congs['*'] = Array('name' => 'Combined Congregations');
		foreach ($congs as $id => $detail) {
			if ($this->printSet('c-'.$id, $detail['name'])) {
				$printed++;
				if ($printed % 3 == 0) {
					?>
					</div>
					<div class="row">
					<?php
				}
			}
		}
		$cong_content = ob_get_clean();

		ob_start();
		$printed = 0;
		$groups = $GLOBALS['system']->getDBObjectData('person_group', Array('!attendance_recording_days' => 0, 'is_archived' => 0), 'AND');
		$catids = Array();
		foreach ($groups as $id => $detail) {
			if (!empty($detail['categoryid'])) $catids[$detail['categoryid']] = 1;
			if ($this->printSet('g-'.$id, $detail['name'])) {
				$printed++;
				if ($printed % 3 == 0) {
					?>
					</div>
					<div class="row">
					<?php
				}
			}
		}
		$cats = $GLOBALS['system']->getDBObjectData('person_group_category');
		foreach ($catids as $catid => $null) {
			if (empty($catid)) continue;
			$this->printSet('gc-'.$catid, 'Combined '.$cats[$catid]['name']);
				$printed++;
				if ($printed % 3 == 0) {
					?>
					</div>
					<div class="row">
					<?php
				}
		}
		$group_content = ob_get_clean();

		if ($cong_content) {
			?>
			<h3><?php echo _('Congregations');?></h3>
			<div class="row">
			<?php echo $cong_content; ?>
			</div>
			<?php
		}

		if ($group_content) {
			?>
			<h3><?php echo _('Groups');?></h3>
			<div class="row">
			<?php echo $group_content; ?>
			</div>
			<?php
		}

	}

	private function printSet($cohortid, $cohortname)
	{
		$stats = Attendance_Record_Set::getStatsForPeriod($this->_start_date, $this->_end_date, $cohortid);
		if (empty($stats) || $stats[NULL]['rate'] == 0) {
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
					<th><?php echo _('Segment');?></th>
					<th title=<?php echo _('"Percentage of dates marked present rather than absent"');?>><?php echo _('Rate');?></th>
					<th class="present" title=<?php echo _('"Average number marked present per date"');?>><?php echo _('Avg&nbsp;P');?></th>
					<th class="absent" title="<?php echo _('Average number marked absent per date"');?>><?php echo _('Avg&nbsp;A');?></th>
			</thead>
			<tbody>
		<?php
		$map['age_bracketid'] = Age_Bracket::getMap();
		if ($cohortid[0] == 'g') {
			list($map['status'], $default) = Person_Group::getMembershipStatusOptionsAndDefault();
		} else {
			$map['status'] = $this->status_map;
		}
		foreach (Array('status', 'age_bracketid') as $grouping) {
			$isFirst = TRUE;
			foreach ($map[$grouping] as $k => $v) {
				if (!isset($stats[$grouping][$k])) continue;
				?>
				<tr <?php if ($isFirst && $grouping == 'age_bracketid') echo 'class="thick-top-border"'; ?>>
					<th><?php echo ents($v); ?></th>
				<?php
				if (isset($stats[$grouping][$k])) {
					?>
					<td><?php echo $stats[$grouping][$k]['rate'] ?>%</td>
					<td><?php echo number_format($stats[$grouping][$k]['avg_present'], 1) ?></td>
					<td><?php echo number_format($stats[$grouping][$k]['avg_absent'], 1) ?></td>
					<?php
				} else {
					?>
					<td>-</td>
					<td>-</td>
					<td>-</td>
					<?php

				}
				?>
				</tr>
				<?php
				$isFirst = FALSE;
			}
		}
		?>
				<tr class="thick-top-border">
					<th><?php echo _('Overall');?></th>
					<td><?php echo $stats[NULL]['rate'] ?>%</td>
					<td><?php echo number_format($stats[NULL]['avg_present'], 1) ?></td>
					<td><?php echo number_format($stats[NULL]['avg_absent'], 1) ?></td>
				</tr>
			<?php
			$bits = explode('-', $cohortid);
			if (($bits[0] != 'gc') && ($bits[1] != '*')) {
				?>
				<tr class="headcount">
					<th colspan="2">
						<?php echo _('Avg&nbsp;Headcount');?>
					</th>
					<td class="right">
						<?php
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
				<?php
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
