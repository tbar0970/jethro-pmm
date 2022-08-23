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
		<p class="text alert alert-info">
			<?php
			// We assemble the HTML this way so that it can be translated as a simple string
			$text = _("User accounts are usually created to give staff and ministry leaders access to Jethro.  You need to create their person record first, then add a user account to go with it.  Usually it's best to let church members create their own accounts in the member area");
			$text = str_replace(_('member area'), '<a href="'.BASE_URL.'/members">'._('member area').'</a>', $text);
			$text = str_replace(_('staff and ministry leaders'), '<i>'._('staff and ministry leaders').'</i>', $text);
			$text = str_replace(_('church members'), '<i>'._('church members').'</i>', $text);
			echo $text;
			?>
		</p>
		<?php
		if (empty($_REQUEST['show_inactive'])) {
			?>
			<a class="pull-right hidden-phone" href="<?php echo build_url(Array('show_inactive' => 1)); ?>"><i class="icon-eye-open"></i><?php echo _('Include inactive accounts');?></a>
			<?php
		} else {
			?>
			<a class="pull-right hidden-phone" href="<?php echo build_url(Array('show_inactive' => 0)); ?>"><i class="icon-eye-close"></i><?php echo _('Hide inactive accounts');?></a>
			<?php
		}
		?>
		<p><a href="?view=_add_user_account"><i class="icon-plus-sign"></i><?php echo _('Add User Account');?></a></p>
		<table class="table table-striped table-hover table-min-width">
			<thead>
				<tr>
					<th>Person Name</th>
					<th>Username</th>
					<th>Status</th>
					<th>Permissions</th>
					<th>&nbsp</th>
				</tr>
			</thead>
			<tbody>
		<?php
		$conds = empty($_REQUEST['show_inactive']) ? Array('active' => 1) : Array();
		$congs = $GLOBALS['system']->getDBObjectData('staff_member', $conds);
		foreach ($congs as $id => $sm) {
			?>
			<tr<?php if (!$sm['active']) echo ' class="archived"'; ?>>
				<td><?php echo $sm['first_name'].' '.$sm['last_name']; ?></td>
				<td><?php echo $sm['username']; ?></td>
				<td><?php echo $sm['active'] ? _('Active') : _('Inactive'); ?></td>
				<td style="font-family: monospace"><?php $this->_printPermissions($sm['permissions']); ?></td>
				<td class="narrow"><a href="?view=_edit_user_account&staff_member_id=<?php echo $id; ?>"><i class="icon-wrench"></i><?php echo _('Edit');?></a></td>
			</tr>
			<?php
		}
		?>
			</tbody>
		</table>
		<?php
	}

	private function _printPermissions($level)
	{
		static $PERM_LEVELS = NULL;
		if (is_null($PERM_LEVELS)) include 'include/permission_levels.php';
		foreach ($PERM_LEVELS as $perm => $label) {
			if (((int)$level & (int)$perm) == $perm) {
				echo '&bull;';
			} else {
				//echo "{ $level & $perm = ".((int)$level & (int)$perm).'} ';
				echo '.';
			}
		}
	}
}