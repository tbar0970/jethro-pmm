<?php
class View_Rosters__Define_Roster_Roles extends View
{

	static function getMenuPermissionLevel()
	{
		return PERM_MANAGEROSTERS;
	}

	function processView()
	{

	}

	function getTitle()
	{
		return 'Define Roster Roles';
	}

	
	function printView()
	{
		?>
		<p class="text alert alert-info">
			<?php 
			echo _('A roster role represents an activity somebody does which is organised by a roster.
				Roster roles are often congregation-specific; for example each congregation probably has its own separate "bible reader" role.
				Other roles may be congregation-independent, such as cleaning or gardening.');
			?>
		</p>
		<p><a href="?view=_add_roster_role"><i class="icon-plus-sign"></i><?php echo _('Add Role'); ?></a></p>
		<?php

		$congs = $GLOBALS['system']->getDBObjectData('congregation', Array('!meeting_time' => ''), 'AND', 'meeting_time');
		$congs += Array(0 => Array('name' => 'Non-Congregational'));
		$allroles = Array();
		foreach ($congs as $cid => $details) {
			$croles = $GLOBALS['system']->getDBObjectData('roster_role', Array('congregationid' => $cid), 'OR', 'active DESC, title ASC');
			if ($croles) $allroles[$cid] = $croles;
		}
		if (empty($allroles)) {
			?>
			<i><?php echo _("No roles have been created yet"); ?>.</i>
			<?php
			return;
		}
		foreach ($allroles as $cid => $roles) {
			?>
			<h3><?php echo ents($congs[$cid]['name']); ?> Roles</h3>
			<?php
			?>
			<table class="table table-striped table-hover table-min-width">
				<thead>
					<tr>
						<th>ID</th>
						<th>Role Title</th>
						<th>Volunteer Group</th>
						<th>&nbsp;</th>
					</tr>
				</thead>
				<tbody>
				<?php
				foreach ($roles as $rid => $rdetails) {
					?>
					<tr<?php if (!$rdetails['active']) echo ' class="archived"'; ?>>
						<td class="narrow"><?php echo $rid; ?></td>
						<td><?php echo ents($rdetails['title']); ?></td>
						<td>
							<?php
							if (!empty($rdetails['volunteer_group'])) {
								echo '<a target="jethro" href="'.BASE_URL.'?view=groups&groupid='.$rdetails['volunteer_group'].'">'.ents($rdetails['volunteer_group_name'].' (#'.$rdetails['volunteer_group'].')').'</a>';
							}
							?>
						</td>
						<td class="narrow"><a href="?view=_edit_roster_role&roster_roleid=<?php echo $rid; ?>"><i class="icon-wrench"></i>Edit</a></td>
					</tr>
					<?php
				}
				?>
				</tbody>
			</table>
			<?php
		}
	}
}
?>
