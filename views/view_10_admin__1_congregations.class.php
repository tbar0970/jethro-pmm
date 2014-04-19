<?php
class View_Admin__Congregations extends View
{
	static function getMenuPermissionLevel()
	{
		return PERM_SYSADMIN;
	}

	function getTitle()
	{
		return 'Congregations';
	}

	function processView()
	{
	}

	function printView()
	{
		?>
		<p>
			<a href="?view=_add_congregation"><i class="icon-plus-sign"></i>Add New Congregation</a>
		</p>
		<table class="table table-hover table-auto-width">
			<thead>
				<tr>
					<th>ID</th>
					<th>Long Name</th>
					<th>Short Name</th>
					<th>Code Name</th>
					<th>Print Qty</th>
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody>
			<?php
			$congs = $GLOBALS['system']->getDBObjectData('congregation', Array(), 'OR', 'meeting_time');
			foreach ($congs as $id => $cong) {
				?>
				<tr>
					<td><?php echo $id; ?></td>
					<td><?php echo htmlentities($cong['long_name']); ?></td>
					<td><?php echo htmlentities($cong['name']); ?></td>
					<td><?php echo htmlentities($cong['meeting_time']); ?></td>
					<td><?php echo (int)($cong['print_quantity']); ?></td>
					<td class="action-cell"><a href="?view=_edit_congregation&congregationid=<?php echo $id; ?>"><i class="icon-wrench"></i>Edit</a></td>
				</tr>
				<?php
			}
			?>
			</tbody>
		</table>
		<?php
	}
}
?>
