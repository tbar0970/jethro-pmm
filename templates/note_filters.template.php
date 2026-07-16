<?php
/**
 * Note sidebar: filter checkboxes and action links for the Notes section.
 *
 * Expected variables:
 * @var string|null $add_note_html  HTML for the "Add Note" link, or null
 */
$GLOBALS['system']->includeDBClass('abstract_note');
$statusDummy = new Abstract_Note();
$statusOptions = $statusDummy->fields['status']['options'];
?>
<div class="panel-sidebar pull-right">
	<i><?php if (!empty($add_note_html)) echo "<i>".$add_note_html."</i>"; ?></i>
	<fieldset class="hidden-phone">
		<legend><?php echo _('Status:'); ?></legend>
		<?php foreach ($statusOptions as $value => $label): ?>
		<label class="checkbox">
			<i><input type="checkbox" class="note-status-filter" value="<?php echo $value; ?>" checked>
			<?php echo ents($label); ?></i>
		</label>
		<?php endforeach; ?>
		<legend><?php echo _('Assignee:'); ?></legend>
		<label class="checkbox">
			<i><input type="checkbox" id="note-assignee-filter">
			<?php echo _('Assigned to me'); ?></i>
		</label>
	</fieldset>
</div>
