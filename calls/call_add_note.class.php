<?php

/**
 * AJAX handler for creating a Person_Note record (?call=add_note).
 *
 * Sole caller: the '#add-note-modal .note-submit' click handler in
 * resources/js/jethro.js, which posts one person at a time from the shared note
 * modal (templates/note_modal.template.php). Failures it returns are rendered by
 * NoteCallHandler.handleResponse() into #call-failures; on success the note is
 * linked from the page via a #note_<id> hash.
 *
 * SMS sends do NOT use this call: when "Create Note" is ticked, Call_SMS creates
 * the note server-side (see include/jethro_sms.php).
 *
 * See docs/docs/developer/reference/note-modal.mdx.
 *
 * Response contract (JSON):
 *   Success: {"sent": {"note_id": <int>}}
 *   Failure: {"error": "<message>"}
 *
 * URL parameters:
 *   personid     int     — person to attach the note to (required)
 *   subject      string  — note subject (required)
 *   details      string  — note body text
 *   status       string  — note status (default: 'no_action')
 *   assignee     int     — person ID of the assignee
 *   action_date  string  — action date (default: today)
 *   ajax         1       — indicates AJAX request
 */
class Call_Add_Note extends Call
{
	function run(): void
	{
		if (!$GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
			echo json_encode(['error' => 'Permission denied']);
			return;
		}

		$personId = (int) ($_REQUEST['personid'] ?? 0);
		if ($personId <= 0) {
			echo json_encode(['error' => 'No person specified']);
			return;
		}

		$subject = trim((string) ($_REQUEST['subject'] ?? ''));
		if ($subject === '') {
			echo json_encode(['error' => 'Empty subject']);
			return;
		}

		$GLOBALS['system']->includeDBClass('person_note');
		$note = new Person_Note();
		$note->setValue('personid', $personId);
		$note->setValue('subject', $subject);
		$note->setValue('details', trim((string) ($_REQUEST['details'] ?? '')));
		$note->setValue('status', $_REQUEST['status'] ?? 'no_action');
		$note->setValue('action_date', $_REQUEST['action_date'] ?? date('Y-m-d'));
		if (!empty($_REQUEST['assignee'])) {
			$note->setValue('assignee', (int) $_REQUEST['assignee']);
		}

		if (!$note->create()) {
			echo json_encode(['error' => 'Failed to add note']);
			return;
		}

		echo json_encode(['sent' => ['note_id' => (int) $note->id]]);
	}
}
