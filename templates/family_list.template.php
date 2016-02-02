<table class="table table-hover table-condensed table-striped clickable-rows">
<thead>
	<tr>
		<th>ID</th>
		<th>Family Name</th>
		<th>Family Members</th>
	<?php
	include_once 'include/size_detector.class.php';
	if (!SizeDetector::isNarrow()) {
		?>
		<th>Home Phone</th>
		<?php
	}
	?>
		<th>Actions</th>
	</tr>
</thead>
<tbody>
<?php
foreach ($families as $id => $details) {
	$tr_class = ($details['status'] == 'archived') ? ' class="archived"' : '';
	?>
	<tr<?php echo $tr_class; ?>>
		<td><?php echo (int)$id; ?></td>
		<td><?php echo ents($details['family_name']); ?></td>
		<td><?php echo ents($details['members']); ?></td>
	<?php
	if (!SizeDetector::isNarrow()) {
		?>
		<td><?php echo ents($details['home_tel']); ?></td>
		<?php
	}
	?>
		<td class="action-cell narrow">
			<a href="?view=families&familyid=<?php echo $id; ?>"><i class="icon-user"></i>View</a> &nbsp;
		<?php
		if ($GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
			?>
			<a href="?view=_edit_family&familyid=<?php echo $id; ?>"><i class="icon-wrench"></i>Edit</a> &nbsp;
			<?php
		}
		if ($GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
			?>
			<a href="?view=_add_note_to_family&familyid=<?php echo $id; ?>"><i class="icon-pencil"></i>Add Note</a></td>
			<?php
		}
		?>
		</td>
	</tr>
	<?php
}
?>
</tbody>
</table>
