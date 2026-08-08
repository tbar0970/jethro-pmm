<div class="notes-history-container">
<?php
$GLOBALS['system']->includeDBClass('abstract_note');
$dummy = new Abstract_Note();

// Attach SMS delivery status for notes created via "Save as Note"
if ($notes !== []) {
	$noteIds = array_keys($notes);
	$db = $GLOBALS['db'];
	$sql = 'SELECT sn.note_id, sd.status, s.id AS msg_id'
		. ' FROM sms_note sn'
		. ' JOIN smsdelivery sd ON sd.id = sn.smsdelivery_id'
		. ' JOIN sms s ON s.id = sd.sms_id'
		. ' WHERE sn.note_id IN (' . implode(',', array_map([$db, 'quote'], $noteIds)) . ')';
	$smsLinks = $db->queryAll($sql, null, null, true, true, true); // group by note_id
	foreach ($smsLinks as $noteId => $rows) {
		if (isset($notes[$noteId])) {
			$notes[$noteId]['_sms_status'] = $rows[0]['status'];
			$notes[$noteId]['_msg_id'] = $rows[0]['msg_id'];
		}
	}
}

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
