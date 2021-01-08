<div class="notes-history-container">
<?php
$GLOBALS['system']->includeDBClass('abstract_note');
$dummy = new Abstract_Note();
foreach ($comms as $id => $entry) {
	include 'single_comm.template.php';
}
?>
</div>
