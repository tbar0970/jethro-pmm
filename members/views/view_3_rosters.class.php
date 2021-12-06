<?php
class View_Rosters extends View
{
	public static function getMenuRequiredFeature()
	{
		return 'ROSTERS&SERVICES';
	}

	var $_roster_view = null;

	function processView()
	{
		if (!empty($_REQUEST['roster_view'])) {
			$this->_roster_view = $GLOBALS['system']->getDBObject('roster_view', (int)$_REQUEST['roster_view']);
		}
	}

	function getTitle()
	{
		if ($this->_roster_view) {
			return $this->_roster_view->getValue('name');
		} else {
			return 'Rosters';
		}
	}

	function printView()
	{
		if ($this->_roster_view) {
			$this->_roster_view->printView(NULL, NULL, FALSE, TRUE);
		} else {
			?>
			<ul>
			<?php
			$views = $GLOBALS['system']->getDBObjectData('roster_view', Array('!visibility' => ''), 'AND', 'name');
			foreach ($views as $id => $detail) {
				?>
				<li><a href="<?php echo build_url(Array('roster_view' => $id)); ?>"><?php echo ents($detail['name']); ?></a></li>
				<?php
			}
			?>
			</ul>
			<?php
			if (empty($views)) {
				?>
				<i>This system does not yet have any rosters configured.</i>
				<?php
			}
			$person = new Person($GLOBALS['user_system']->getCurrentPerson('id'));
			$fmembers = $person->getFamily()->getMemberData();

			?>
			<h3>Planned absences for <?php $family->printFieldValue('family_name'); ?> family</h3>
			<?php
			$params = Array(
				'>=end_date' => date('Y-m-d'),
				'(personid' => array_keys($fmembers),
			);
			$absences = $GLOBALS['system']->getDBObjectData('planned_absence', $params, 'AND', 'start_date');
			if ($absences) {
				?>
				<table class="table table-condensed table-bordered table-auto-width">
					<thead>
						<tr>
							<th>Person</th>
							<th>From</th>
							<th>To</th>
							<th>Comment</th>
							<th>&nbsp;</th>
						</tr>
					</thead>
					<tbody>
					<?php
					foreach ($absences as $id => $row) {
						$fmember = $fmembers[$row['personid']];
						$tooltip = 'Saved by '.$row['creator_name'].' on '.format_datetime($row['created']);
						?>
						<tr>
							<td><?php echo ents($fmember['first_name'].' '.$fmember['last_name']); ?></td>
							<td><?php echo format_date($row['start_date']); ?></td>
							<td><?php echo format_date($row['end_date']); ?></td>
							<td><?php echo ents($row['comment']); ?></td>
							<td>
								<i class="icon-info-sign" title="<?php echo ents($tooltip); ?>"></i>
								<a class="confirm-title" href="?view=_delete_planned_absence&id=<?php echo $id; ?>" title="Delete this planned absence" data-method="post"><i class="icon-trash"></i></a>
							</td>
						</tr>
						<?php
					}
					?>
					</tbody>
				</table>
				<?php
			} else {
				?>
				<p><i>The <?php $family->printFieldValue('family_name'); ?> family has no upcoming planned absences</i></p>
				<?php
			}
			?>
			<p><i class="icon-plus-sign"></i> <a href="?view=_add_planned_absence">Add a planned absence</a></p>
			<?php
		}
	}
}
