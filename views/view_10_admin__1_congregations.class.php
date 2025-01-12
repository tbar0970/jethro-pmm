<?php
class View_Admin__Congregations extends View
{
	static function getMenuPermissionLevel()
	{
		return PERM_SYSADMIN;
	}

	function getTitle()
	{
		return _('Congregations');
	}

	function processView()
	{
		if (array_get($_POST, 'action') == 'delete') {
			$cong = $GLOBALS['system']->getDBObject('congregation', (int)$_REQUEST['congregationid']);
			if ($cong) {
				if ($cong->delete()) {
					add_message(_("Congregation deleted"));
				}
			}
		}
	}

	function printView()
	{
		?>
		<p class="text alert alert-info">
			Congregations are Jethro's primary grouping for <a href="?view=persons__list_all">persons</a> and <a href="?view=services__list_all">services</a>. 
			Each person belongs to <b>one</b> congregation, unless their <a href="?view=admin__system_configuration#PERSON_STATUS_OPTIONS">person status</a> allows them to be congregation-less.
			Each service belongs to one congregation.
			You can configure whether each congregation contains persons, services, or both. Depending on your church's structure, you might choose to put all your persons in one big congregation and use <a href="">person groups</a> for further categorisation.
		</p>
		<p>
			<a href="?view=_add_congregation"><i class="icon-plus-sign"></i>Add New Congregation</a>
		</p>
		<table class="table table-hover table-auto-width">
			<thead>
				<tr>
					<th>ID</th>
					<th><?php echo _('Long Name');?></th>
					<th><?php echo _('Short Name');?></th>
					<th><?php echo _('Persons?');?></th>
					<th><?php echo _('Attendance?');?></th>
					<th><?php echo _('Services & Rosters?');?></th>
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody>
			<?php
			$congs = $GLOBALS['system']->getDBObjectData('congregation', Array(), 'OR', 'meeting_time');
			$deletePrinted = FALSE;
			$congObject = new Congregation();
			foreach ($congs as $id => $cong) {
				$congObject->populate($id, $cong);
				$class = $congObject->isActive() ? '' : 'archived';
				?>
				<tr class="<?php echo $class; ?>">
					<td><?php echo $id; ?></td>
					<td><?php echo ents($cong['long_name']); ?></td>
					<td><?php echo ents($cong['name']); ?></td>
					<td>
						<i class="<?php echo $cong['holds_persons'] ? 'icon-ok' : 'icon-remove '; ?>"></i>
						<?php 
						if ($cong['member_count'] > 0) {
							if (!$cong['holds_persons']) echo '(';
							echo (int)$cong['member_count'];
							if (!$cong['holds_persons']) echo ')';
						}
						?>
					</td>
					<td>
						<i class="<?php echo ($cong['attendance_recording_days'] > 0) ? 'icon-ok' : 'icon-remove '; ?>"></i>
					</td>
					<td>
						<i class="<?php echo ($cong['meeting_time'] != '') ? 'icon-ok' : 'icon-remove '; ?>"></i>
						<?php echo ents($cong['meeting_time']); ?>
					</td>
					<td class="action-cell">
						<a href="?view=_edit_congregation&congregationid=<?php echo $id; ?>"><i class="icon-wrench"></i><?php echo _('Edit');?></a> &nbsp;
					<?php
					if ($congObject->canDelete()) {
						?>
						<a href="<?php echo build_url(Array('action' => 'delete', 'congregationid' => $id)); ?>" data-method="post"><i class="icon-trash"></i><?php echo _('Delete');?></a>
						<?php
						$deletePrinted = TRUE;
					}
					?>
					</td>
				</tr>
				<?php
			}
			?>
			</tbody>
		</table>
		<?php
		if (!$deletePrinted) {
			?>
			<p><?php echo _('To delete a congregation, first ensure it contains no persons or services, then it can be deleted via this page');?></p>
			<?php
		}

	}
}