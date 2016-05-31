<?php
class View_Admin__User_Accounts extends View
{
	static function getMenuPermissionLevel()
	{
		return PERM_SYSADMIN;
	}

	function getTitle()
	{
		return _('User Accounts');
	}

	function processView()
	{
	}

	function printView()
	{
		?>
		<p><a href="?view=_add_user_account"><i class="icon-plus-sign"></i><?php echo _('Add User Account');?></a></p>

		<table class="table table-striped table-hover table-min-width">
		<?php
		$congs = $GLOBALS['system']->getDBObjectData('staff_member');
		foreach ($congs as $id => $sm) {
			?>
			<tr<?php if (!$sm['active']) echo ' class="archived"'; ?>>
				<td class="narrow"><?php echo $id; ?></td>
				<td><?php echo $sm['first_name'].' '.$sm['last_name']; ?></td>
				<td><?php echo $sm['active'] ? _('Active') : _('Inactive'); ?></td>
				<td class="narrow"><a href="?view=_edit_user_account&staff_member_id=<?php echo $id; ?>"><i class="icon-wrench"></i><?php echo _('Edit');?></a></td>
			</tr>
			<?php
		}
		?>
		</table>
		<?php
	}
}
?>
