<div class="notes-history-container">
<?php
$GLOBALS['system']->includeDBClass('abstract_note');
$dummy = new Abstract_Note();
foreach ($notes as $id => $entry) {
	include 'single_note.template.php';
}
?>
</div>
