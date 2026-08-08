<?php

/**
 * Print the Add Note modal dialog.
 *
 * This is printed once per page alongside any "Add Note" link that triggers it.
 * The modal submits via AJAX to ?call=note (see calls/call_note.class.php).
 */
function printNoteModal(): void
{
	$GLOBALS['system']->includeDBClass('person_note');
	$note = new \Person_Note();
	?>
	<div id="add-note-modal" class="modal note-modal hide fade" role="dialog" aria-hidden="true">
		<div class="modal-header">
			<h4>Add Note to <span class="note-recipient-name"></span></h4>
		</div>
		<div class="modal-body form-horizontal">
			<input type="hidden" name="note_personid" id="note_personid" value="" />
			<?php $note->printForm(); ?>
		</div>
		<div class="modal-footer">
			<div class="results"></div>
			<button class="btn note-submit" accesskey="s">Save Note</button>
			<button class="btn" data-dismiss="modal" aria-hidden="true">Close</button>
		</div>
	</div>
	<?php
}
