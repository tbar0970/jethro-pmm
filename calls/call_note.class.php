<?php

class Call_Note extends Call
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

		$GLOBALS['system']->includeDBClass('person_note');
		$successIds = [];
		$failureIds = [];

		foreach ($personIds as $personId) {
			$note = new Person_Note();
			$note->setValue('personid', $personId);
			$note->setValue('subject', $subject);
			$note->setValue('details', $details);
			$note->setValue('status', $status);
			if ($assignee !== null) {
				$note->setValue('assignee', $assignee);
			}

			if ($note->create()) {
				$successIds[] = $personId;
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
