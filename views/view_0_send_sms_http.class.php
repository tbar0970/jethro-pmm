<?php
class View__Send_SMS_HTTP extends View
{

	function getTitle()
	{
		return 'Send SMS';
	}

	private function getRecipients()
	{
		$recips = $archived = $blanks = Array();
		if (!empty($_REQUEST['queryid'])) {
			$query = $GLOBALS['system']->getDBObject('person_query', (int) $_REQUEST['queryid']);
			$personids = $query->getResultPersonIDs();
			$recips = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, '!mobile_tel' => '', '!status' => 'archived'), 'AND');
			$blanks = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, 'mobile_tel' => '', '!status' => 'archived'), 'AND');
			$archived = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, 'status' => 'archived'), 'AND');
		} else if (!empty($_REQUEST['groupid'])) {
			$group = $GLOBALS['system']->getDBObject('person_group', (int) $_REQUEST['groupid']);
			$personids = array_keys($group->getMembers());
			$recips = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, '!mobile_tel' => '', '!status' => 'archived'), 'AND');
			$blanks = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, 'mobile_tel' => '', '!status' => 'archived'), 'AND');
			$archived = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, 'status' => 'archived'), 'AND');
		} else if (!empty($_REQUEST['roster_view'])) {
			$view = $GLOBALS['system']->getDBObject('roster_view', (int) $_REQUEST['roster_view']);
			$recips = $view->getAssignees($_REQUEST['start_date'], $_REQUEST['end_date']);
			foreach ($recips as $i => $details) {
				if ($details['status'] == 'archived') {
					$archived[$i] = $details;
					unset($recips[$i]);
				} else if (empty($details['mobile_tel'])) {
					$blanks[$i] = $details;
					unset($recips[$i]);
				}
			}
		} else {
			switch (array_get($_REQUEST, 'sms_type')) {
				case 'family':
					$GLOBALS['system']->includeDBClass('family');
					$families = Family::getFamilyDataByMemberIDs($_POST['personid']);
					$recips = $GLOBALS['system']->getDBObjectData('person', Array('age_bracket' => '0', '(familyid' => array_keys($families), '!mobile_tel' => '', '!status' => 'archived'), 'AND');
					$blanks = $GLOBALS['system']->getDBObjectData('person', Array('age_bracket' => '0', '(familyid' => array_keys($families), 'mobile_tel' => '', '!status' => 'archived'), 'AND');
					$archived = $GLOBALS['system']->getDBObjectData('person', Array('age_bracket' => '0', '(familyid' => array_keys($families), 'status' => 'archived'), 'AND');
					break;
				case 'person':
				default:
					if (!empty($_POST['personid'])) {
						$recips = $GLOBALS['system']->getDBObjectData('person', Array('id' => $_POST['personid'], '!mobile_tel' => '', '!status' => 'archived'), 'AND');
						$blanks = $GLOBALS['system']->getDBObjectData('person', Array('id' => $_POST['personid'], 'mobile_tel' => '', '!status' => 'archived'), 'AND');
						$archived = $GLOBALS['system']->getDBObjectData('person', Array('id' => $_POST['personid'], 'status' => 'archived'), 'AND');
						$GLOBALS['system']->includeDBClass('person');
					}
					break;
			}
		}
		return Array($recips, $blanks, $archived);
	}

	function saveAsNote($recipients, $message)
	{
		$GLOBALS['system']->includeDBClass('person_note');
		$subject = ifdef('SMS_SAVE_TO_NOTE_SUBJECT', 'SMS Sent');
		foreach ($recipients as $id => $details) {
			// Add a note containing the SMS to the user
			$note = new Person_Note();
			$note->setValue('subject', $subject);
			$note->setvalue('details', '"'.$message.'"');
			$note->setValue('personid', $id);
			if (!$note->create()) {
				add_message('Failed to save SMS as a note.');
			}
		}
	}

	public function printView()
	{
		$recips = $successes = $failures = $archived = $blanks = Array();
		list($recips, $blanks, $archived) = $this->getRecipients();

		if (empty($recips)) {
			print_message("Did not find any recipients with mobile numbers.  Message not sent.", 'error');
		} else {
			$message = $_POST['message'];
			if (empty($message) || strlen($message) > SMS_MAX_LENGTH) {
				print_message("Your message is empty or too long", "error");
				return;
			}

			$sendResponse = SMS_Sender::send($message, $recips);
			$success = $sendResponse['success'];
			$successes = $sendResponse['successes'];
			$failures = $sendResponse['failures'];
			if (!$success) {
				add_message('Failed communicating with SMS server - please check your config', 'failure');
				return;
			}
			if ((!empty($successes)) || (!empty($failures))) {
				if (!empty($successes)) {
					print_message('SMS sent successfully to ' . count($successes) . ' recipients');
					$this->saveAsNote($successes, $message);
				}
				if (!empty($failures)) {
					print_message('SMS sending failed for ' . count($failures) . ' recipients', 'failure');
					?>
					<p><b>Sending an SMS to the following recipients failed.  <span class="clickable" onclick="$('#response').toggle()">Show technical details</span></b></p>
					<div style="display: none" class="well error" id="response"><?php bam($sendResponse['rawrequest']); bam($sendResponse['rawresponse']) ?></div>
					<?php
					$persons = $failures;
					require 'templates/person_list.template.php';
				}
			} else {
				// No check of the response - give a less confident success message
				print_message('SMS dispatched to ' . count($recips) . ' recipients');
				?>
				<span class="clickable" onclick="$('#response').toggle()">Show SMS server response</span></b></p>
				<div class="hidden standard" id="response"><?php $response; ?></div>
				<?php
			}
		}

		if (!empty($archived)) {
			?>
			<h4>Archived Recipients</h4>
			<p style="clear: both"><?php echo count($archived); ?> of the intended recipients were not sent the message because they are archived.</p>
			<?php
		}
		if (!empty($blanks)) {
			?>
			<h4>Recipients with blank mobile number</h4>
			<p style="clear:both">The following persons were not sent the message because they don't have a mobile number recorded:</b>
			<?php
			$persons = $blanks;
			$include_special_fields = FALSE;
			require 'templates/person_list.template.php';
		}
		if (empty($archived) && empty($blanks)) {
			?>
			<a href="javascript:window.history.back()" class="btn"><i class="icon-chevron-left"></i> Back</a>
			<?php
		}
	}

}
?>
