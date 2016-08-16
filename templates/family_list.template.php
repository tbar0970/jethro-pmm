<table class="table table-hover table-striped clickable-rows table-responsive-vertical">
<thead>
	<tr>
		<th><?php echo _('Family Name')?></th>
		<th><?php echo _('Family Members')?></th>
		<th><?php echo _('Home Phone')?></th>
		<th><?php echo _('Actions')?></th>
	</tr>
</thead>
<tbody>
<?php
foreach ($families as $id => $details) {
	$tr_class = ($details['status'] == 'archived') ? ' class="archived"' : '';
	?>
	<tr<?php echo $tr_class; ?>>
		<td data-title="<?php echo _("Family Name"); ?>"><?php echo ents($details['family_name']); ?></td>
		<td data-title="<?php echo _('Family Members');?>"><?php echo ents($details['members']); ?></td>
		<td data-title="<?php echo _('Home Phone');?>"><?php echo ents($details['home_tel']); ?></td>
		<td class="action-cell">
			<a class="btn btn-raised btn-primary" href="?view=families&familyid=<?php echo $id; ?>"><i class="material-icons">person_pin</i><?php echo _('View');?></a>
		<?php
		if ($GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
			?>
			<a class="btn btn-raised btn-primary" href="?view=_edit_family&familyid=<?php echo $id; ?>"><i class="material-icons">edit</i><?php echo _('Edit');?></a>
			<?php
		}
		if ($GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
			?>
			<a class="btn btn-raised btn-primary" href="?view=_add_note_to_family&familyid=<?php echo $id; ?>"><i class="material-icons">note_add</i><?php echo _('Add Note');?></a></td>
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
