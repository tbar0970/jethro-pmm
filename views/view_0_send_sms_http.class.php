<?php
class View__Send_SMS_HTTP extends View
{
	function getTitle()
	{
		return 'Send SMS';
	}

	function printView() 
	{
		$recips = $successes = $failures = $archived = $blanks = Array();
		if (!empty($_REQUEST['queryid'])) {
			$query = $GLOBALS['system']->getDBObject('person_query', (int)$_REQUEST['queryid']);
			$personids = $query->getResultPersonIDs();
			$recips = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, '!mobile_tel' => '', '!status' => 'archived'), 'AND');
			$blanks = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, 'mobile_tel' => '', '!status' => 'archived'), 'AND');
			$archived = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, 'status' => 'archived'), 'AND');
		} else if (!empty($_REQUEST['groupid'])) {
			$group = $GLOBALS['system']->getDBObject('person_group', (int)$_REQUEST['groupid']);
			$personids = array_keys($group->getMembers());
			$recips = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, '!mobile_tel' => '', '!status' => 'archived'), 'AND');
			$blanks = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, 'mobile_tel' => '', '!status' => 'archived'), 'AND');
			$archived = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, 'status' => 'archived'), 'AND');
		} else if (!empty($_REQUEST['roster_view'])) {
			$view = $GLOBALS['system']->getDBObject('roster_view', (int)$_REQUEST['roster_view']);
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

		if (empty($recips)) {
			print_message("Did not find any recipients with mobile numbers.  Message not sent.", 'error');
		} else {

			$mobile_tels = array();
			foreach ($recips as $recip) {
				$mobile_tels[$recip['mobile_tel']] = 1;
			}
			$mobile_tels = array_keys($mobile_tels);

			$message = $_POST['message'];
			if (empty($message) || strlen($message) > SMS_MAX_LENGTH) {
				print_message("Your message is empty or too long", "error");
				return;
			}

			$content = SMS_HTTP_POST_TEMPLATE;
			$content = str_replace('_USER_MOBILE_', urlencode($GLOBALS['user_system']->getCurrentUser('mobile_tel')), $content);
			$content = str_replace('_USER_EMAIL_', urlencode($GLOBALS['user_system']->getCurrentUser('email')), $content);
			$content = str_replace('_MESSAGE_', urlencode($message), $content);
			$content = str_replace('_RECIPIENTS_COMMAS_', urlencode(implode(',', $mobile_tels)), $content);
			$content = str_replace('_RECIPIENTS_NEWLINES_', urlencode(implode("\n", $mobile_tels)), $content);

			$header  = "";
			if ((FIVECENT_SMS_EMAIL) && (FIVECENT_SMS_API_KEY)) {
				$header = $header . "User: " . FIVECENT_SMS_EMAIL . "\r\n"
						  . "Api-Key: " . FIVECENT_SMS_API_KEY . "\r\n";
			}
			$header  = $header . "Content-Length: ".strlen($content)."\r\n"
				           . "Content-Type: application/x-www-form-urlencoded\r\n";
			$opts = Array(
				'http' => Array(
					'method'	=> 'POST',
					'content'	=> $content,
					'header'	=> $header
				)
			);
						
			$response = '';
			$fp = fopen(SMS_HTTP_URL, 'r', false, stream_context_create($opts));
			if ($fp) {
				$response = stream_get_contents($fp);
				fclose($fp);
			}

			if (empty($response)) {
				add_message('Failed communicating with SMS server - please check your config', 'failure');
				return;
			}

			$response = str_replace("\r", '', $response);
			if (SMS_HTTP_RESPONSE_OK_REGEX) {
				foreach ($recips as $id => $recip) {
					$pattern = '/'.str_replace('_RECIPIENT_', preg_quote($recip['mobile_tel']), SMS_HTTP_RESPONSE_OK_REGEX).'/m';
					print "Pattern: " . $pattern;
					if (preg_match($pattern, $response)) { 
						$successes[$id] = $recip;

						// Add a note containing the SMS to the user
						$GLOBALS['system']->includeDBClass('person_note');
						$note = new Person_Note();
						$note->setValue('subject', 'SMS Sent');
						$note->setvalue('details', $_POST['message']);
						$note->setValue('personid', $id);
						if ($note->create()) {
							add_message('Note added');
						}

					} else {
						$failures[$id] = $recip;
					}
				}
				if (!empty($successes)) {
					print_message('SMS sent successfully to '.count($successes).' recipients');
					$this->logSuccess(count($successes), $message);
				}
				if (!empty($failures)) {
					print_message('SMS sending failed for '.count($failures).' recipients', 'failure');
					?>
					<p><b>Sending an SMS to the following recipients failed.  <span class="clickable" onclick="$('#response').toggle()">Show server response</span></b></p>
					<div class="hidden standard" id="response"><?php bam($response); ?></div>
					<?php
					$persons = $failures;
					require 'templates/person_list.template.php';
				}
			} else {
				// No check of the response - give a less confident success message
				print_message('SMS sent to '.count($recips).' recipients');
				$this->logSuccess(count($recips), $message);
				?>
				<span class="clickable" onclick="$('#response').toggle()">Show SMS server response</span></b></p>
				<div class="hidden standard" id="response"><?php bam($response); ?></div>
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

	function logSuccess($recip_count, $message) {
		if (defined('SMS_SEND_LOGFILE') && ($file = constant('SMS_SEND_LOGFILE'))) {
			$msg_trunc = strlen($message) > 30 ? substr($message, 0, 27).'...' : $message;
			error_log(date('Y-m-d H:i').': '.$GLOBALS['user_system']->getCurrentUser('username').' (#'.$GLOBALS['user_system']->getCurrentUser('id').') to '.(int)$recip_count.' recipients: "'.$msg_trunc."\"\n", 3, $file);
		}
	}
	
}
?>
