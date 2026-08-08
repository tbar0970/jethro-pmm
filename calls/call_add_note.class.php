<?php

/**
 * AJAX handler for creating Person_Note records (?call=add_note).
 *
 * Called from two places in jethro.js:
 *
 *   1. #add-note-modal submit handler (single person)
 *      ─ User opens the "Add Note" modal on a person row, fills in subject/
 *        details/status, clicks Save Note. The response is processed by
 *        NoteCallHandler.handleResponse() which displays errors in
 *        #call-failures and returns TRUE if there were issues.
 *
 *   2. SMS submit handlers (bulk and modal, via .saveasnote checkbox)
 *      ─ After an SMS is sent, Call_SMS creates notes server-side if
 *      "Create Note" was checked — no separate AJAX call needed here.
 *
 * Response contract (read by NoteCallHandler.handleResponse in jethro.js):
 *
 *   Failure:
 *     {error: "message"}                          — top-level error (permission, no recipients, empty subject, all failed)
 *     {failed: {count: N, recipients: {id: {first_name, last_name}}}}  — partial failure
 *
 *   Success (all notes created):
 *     {sent: {count: N, recipients: {id: {first_name, last_name}}, confirmed: true}}
 *     ─ No `error` or `failed` keys present, so NoteCallHandler.handleResponse returns FALSE.
 *
 *   Partial success:
 *     Combines `sent` + `failed` keys.
 *
 * URL parameters:
 *   personid          int|int[]  — person ID(s) to attach the note to
 *   subject           string     — note subject (required)
 *   details           string     — note body text
 *   status            string     — note status (default: 'no_action')
 *   assignee          int        — person ID of the assignee
 *   action_date       string     — action date (default: today)
 *   ajax              1          — indicates AJAX request
 */
class Call_Add_Note extends Call
{
	function run(): void
	{
		if (!$GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
			echo json_encode(['error' => 'Permission denied']);
			return;
		}

		$ajax = [];

		// Parse person IDs from request
		$personIds = [];
		$raw = $_REQUEST['personid'] ?? [];
		if (!is_array($raw)) {
			$raw = [$raw];
		}
		foreach ($raw as $id) {
			$id = (int) $id;
			if ($id > 0) {
				$personIds[] = $id;
			}
		}
		$personIds = array_unique($personIds);

		if ($personIds === []) {
			echo json_encode(['error' => 'No valid recipients']);
			return;
		}

		$subject = trim((string) ($_REQUEST['subject'] ?? ''));
		if ($subject === '') {
			echo json_encode(['error' => 'Empty subject']);
			return;
		}

		$details = trim((string) ($_REQUEST['details'] ?? ''));
		$status = $_REQUEST['status'] ?? 'no_action';
		$assignee = !empty($_REQUEST['assignee']) ? (int) $_REQUEST['assignee'] : null;
		$actionDate = $_REQUEST['action_date'] ?? date('Y-m-d');

		$GLOBALS['system']->includeDBClass('person_note');
		$successIds = [];
		$failureIds = [];
		$successNoteIds = [];

		foreach ($personIds as $personId) {
			$note = new Person_Note();
			$note->setValue('personid', $personId);
			$note->setValue('subject', $subject);
			$note->setValue('details', $details);
			$note->setValue('status', $status);
			$note->setValue('action_date', $actionDate);
			if ($assignee !== null) {
				$note->setValue('assignee', $assignee);
			}

			if ($note->create()) {
				$successIds[] = $personId;
				$successNoteIds[(string)$personId] = (int)$note->id;
			} else {
				$failureIds[] = $personId;
			}
		}

		if ($failureIds !== []) {
			$ajax['failed']['count'] = count($failureIds);
			$ajax['failed']['recipients'] = self::_personIdsToRecords($failureIds);
		}
		if ($successIds !== []) {
			$ajax['sent']['count'] = count($successIds);
			$ajax['sent']['recipients'] = self::_personIdsToRecords($successIds);
			$ajax['sent']['confirmed'] = true;
			$ajax['sent']['note_ids'] = $successNoteIds;
		}

		if ($successIds === []) {
			$ajax['error'] = 'Failed to add note to any selected person';
		}

		echo json_encode($ajax);
	}

	/**
	 * Convert person IDs into person-record format (keyed by person ID).
	 *
	 * @param int[] $personIds
	 * @return array<int, array{first_name: string, last_name: string}>
	 */
	private static function _personIdsToRecords(array $personIds): array
	{
		$people = $GLOBALS['system']->getDBObjectData('person', ['id' => $personIds], 'AND');
		$result = [];
		foreach ($personIds as $pid) {
			$result[$pid] = [
				'first_name' => $people[$pid]['first_name'] ?? "Person #$pid",
				'last_name'  => $people[$pid]['last_name'] ?? '',
			];
		}
		return $result;
	}
}
