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
				$members = $GLOBALS['system']->getDBObjectData('person', Array('congregationid' => $cong->id));
				if (count($members)) {
					add_message(_("Cannot delete congregation because it is not empty", "error"));
				} else {
					$cong->delete();
					add_message(_("Congregation deleted"));
				}
			}
		}
	}

	function printView()
	{
		?>
		<p class="text alert alert-info">Each person can be in one congregation.  A person with status 'contact' can be congregationless.  You may want to create extra congregations to represent kids church, housebound persons, etc.</p>
		<p>
			<a href="?view=_add_congregation"><i class="icon-plus-sign"></i>Add New Congregation</a>
		</p>
		<table class="table table-hover table-auto-width">
			<thead>
				<tr>
					<th>ID</th>
					<th><?php echo _('Long Name');?></th>
					<th><?php echo _('Short Name');?></th>
					<th><?php echo _('Code Name');?></th>
					<th><?php echo _('Members');?></th>
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody>
			<?php
			$congs = $GLOBALS['system']->getDBObjectData('congregation', Array(), 'OR', 'meeting_time');
			$deletePrinted = FALSE;
			foreach ($congs as $id => $cong) {
				?>
				<tr>
					<td><?php echo $id; ?></td>
					<td><?php echo ents($cong['long_name']); ?></td>
					<td><?php echo ents($cong['name']); ?></td>
					<td><?php echo ents($cong['meeting_time']); ?></td>
					<td><?php echo $cong['member_count']; ?></td>
					<td class="action-cell">
						<a href="?view=_edit_congregation&congregationid=<?php echo $id; ?>"><i class="icon-wrench"></i><?php echo _('Edit');?></a> &nbsp;
					<?php
					if ($cong['member_count'] == 0) {
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
			<p><?php echo _('To delete a congregation, first ensure it contains no members, then it can be deleted via this page');?></p>
			<?php
		}

	}
}
?>
