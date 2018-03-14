<table class="table table-hover table-condensed table-striped clickable-rows">
<thead>
	<tr>
		<th>ID</th>
		<th><?php echo _('Family Name')?></th>
		<th><?php echo _('Family Members')?></th>
	<?php
	include_once 'include/size_detector.class.php';
	if (!SizeDetector::isNarrow()) {
		?>
		<th><?php echo _('Home Phone')?></th>
		<?php
	}
	?>
		<th><?php echo _('Actions')?></th>
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
			<a href="?view=families&familyid=<?php echo $id; ?>"><i class="icon-user"></i><?php echo _('View');?></a> &nbsp;
		<?php
		if ($GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
			?>
			<a href="?view=_edit_family&familyid=<?php echo $id; ?>"><i class="icon-wrench"></i><?php echo _('Edit');?></a> &nbsp;
			<?php
		}
		if ($GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
			?>
			<a href="?view=_add_note_to_family&familyid=<?php echo $id; ?>"><i class="icon-pencil"></i><?php echo _('Add Note');?></a></td>
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
