<?php
class View_Rosters__Define_Roster_Views extends View
{
	static function getMenuPermissionLevel()
	{
		return PERM_MANAGEROSTERS;
	}

	function processView()
	{
		if (!empty($_REQUEST['delete_viewid'])) {
			$view =& $GLOBALS['system']->getDBOBject('roster_view', (int)$_REQUEST['delete_viewid']);
			if ($view) {
				$view->delete();
				add_message('View Deleted');
				redirect('rosters__define_roster_views', Array());
			}
		}

	}

	function getTitle()
	{
		return 'Define Roster Views';
	}

	
	function printView()
	{
		?>
		<div class="container row-fluid margin-bottom">
			<div class="span10">
				<p><i>A roster view is a collection of roster roles, used when setting or displaying roster assignments.<br />You might like to create views such as "morning congregation" or "all preachers".<br />A roster role can belong to several roster views.</i></p>
			</div>
			<div class="span2 align-right">
				<a href="?view=_add_roster_view"><i class="icon-plus-sign"></i>Add View</a>
			</div>
		</div>
		<table class="table">
			<thead>
				<tr>
					<th class="nowrap">View name</th>
					<th>Roles</th>
					<th>&nbsp;</th>
				</tr>
			</thead>
		<?php
		$views = $GLOBALS['system']->getDBObjectData('roster_view', Array(), 'OR', 'name');
		foreach ($views as $id => $details) {
			?>
			<tr>
				<td class="nowrap"><?php echo htmlentities($details['name']); ?></td>
				<td><?php echo htmlentities($details['members']); ?></td>
				<td class="nowrap">
					<a href="?view=_edit_roster_view&roster_viewid=<?php echo $id; ?>"><i class="icon-wrench"></i>Edit</a> &nbsp;
					<a href="<?php echo build_url(Array('delete_viewid'=>$id)); ?>"><i class="icon-trash"></i>Delete</a>
				</td>
			</tr>
			<?php
		}
		?>
		</table>
	<?php
	}
}
?>
