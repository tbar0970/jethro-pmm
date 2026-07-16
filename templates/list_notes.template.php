<div class="notes-history-container">
<?php
$GLOBALS['system']->includeDBClass('abstract_note');
$dummy = new Abstract_Note();
foreach ($notes as $id => $entry) {
	$dummy->reset();
	$dummy->populate($id, $entry);
	include 'single_note.template.php';
}
?>
</div>

<script>
	$(function() {
		initNoteFilters(<?php echo (int)$GLOBALS['user_system']->getCurrentUser('id'); ?>);
	});
</script>
