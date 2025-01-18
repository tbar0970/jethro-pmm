<?php
/* @var	$assigning	Whether we are assigning notes using this view */

if ($reassigning) {
	require_once 'db_objects/abstract_note.class.php';
	$fake_note = new Abstract_Note();
	$fake_note->fields['assignee']['class'] = 'autofocus';
	?>
	<form method="post">
	<?php
}
?>
<table class="table table-condensed table-striped table-hover valign-middle">
	<thead>
		<tr>
			<th><?php echo _('ID')?></th>
			<th><?php echo _('For')?></th>
			<th><?php echo _('Subject')?></th>
			<th><?php echo _('Assignee')?></th>
			<th><?php echo _('Action Date')?></th>
			<th>&nbsp;</th>
		</tr>
	</thead>
	<tbody>
	<?php
	foreach ($notes as $id => $note) {
		if (!empty($note['familyid'])) {
			$type = 'family';
			$icon = 'home';
			$notee_name = $note['family_name'].' Family';
			$view_url = '?view=families&familyid='.$note['familyid'].'#note_'.$id;
		} else {
			$type = 'person';
			$icon = 'user';
			$notee_name = $note['person_fn'].' '.$note['person_ln'];
			$view_url = '?view=persons&personid='.$note['personid'].'#note_'.$id;
		}
		?>
		<tr>
			<td><?php echo $id; ?></td>
			<td class="nowrap"><i class="icon-<?php echo $icon; ?>"></i> <?php echo ents($notee_name); ?></td>
			<td><?php echo ents($note['subject']); ?></td>
			<td class="nowrap">
				<?php
				if ($reassigning) {
					$fake_note->populate($id, $note);
					if ($fake_note->haveLock() || $fake_note->canAcquireLock()) {
						$fake_note->acquireLock();
						$fake_note->printFieldInterface('assignee', 'note_'.$id.'_');
					} else {
						$fake_note->printFieldValue('assignee');
						echo '<p class="alert alert-error">'._('This note is locked by another user and cannot be edited at this time.').'</p>';
					}
				} else {
					echo ents($note['assignee_fn'].' '.$note['assignee_ln']);
				}
				?>
			</td>
			<td class="nowrap"><?php echo format_date($note['action_date']); ?></td>
			<td class="nowrap action-cell">
				<a href="<?php echo $view_url; ?>"><i class="icon-pencil"></i><?php echo _('View')?></a> &nbsp;
				<a href="?view=_edit_note&note_type=<?php echo $type; ?>&noteid=<?php echo $id; ?>&back_to=<?php echo ents($_REQUEST['view']); ?>"><i class="icon-wrench"></i><?php echo _('Edit/Comment')?></a>
			</td>
		</tr>
		<?php
	}
	?>
	</tbody>
</table>
<?php
if ($reassigning) {
	?>
	<input type="submit" name="reassignments_submitted" value="Save Assignees" class="btn" />
	<a class="btn" href="<?php echo build_url(Array('reassigning' => 0)); ?>"><?php echo _('Cancel')?></a>
	</form>
	<?php
}