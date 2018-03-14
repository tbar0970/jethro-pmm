<?php

Class SMS_Sender
{

	/**
	 * Get Recipients based on the $_REQUEST
	 * @return array('recips' => array, 'blanks' => array, 'archived' => array)
	 */
	public static function getRecipients()
	{
		$personids = Array();
		if (!empty($_REQUEST['personid'])) {
			if (is_array($_REQUEST['personid'])) {
				$personids = $_REQUEST['personid'];
			} else {
				$personids = explode(',', $_REQUEST['personid']);
			}
		}
		if (empty($personids)) $personids = Array(); // to overcome blank strings etc
		if (!empty($_REQUEST['queryid'])) {
			$query = $GLOBALS['system']->getDBObject('person_query', (int)$_REQUEST['queryid']);
			$personids = array_merge($personids, $query->getResultPersonIDs());
		}
		if (!empty($_REQUEST['groupid'])) {
			$group = $GLOBALS['system']->getDBObject('person_group', (int)$_REQUEST['groupid']);
			$personids = array_merge($personids, array_keys($group->getMembers()));
		}

		$smstype = array_get($_REQUEST, 'sms_type', 'person');
		switch ($smstype) {
			case 'family':
				$families = Family::getFamilyDataByMemberIDs($personids);
				$recips = $GLOBALS['system']->getDBObjectData('person', Array('(age_bracketid' => Age_Bracket::getAdults(), '(familyid' => array_keys($families), '!mobile_tel' => '', '!status' => 'archived'), 'AND');
				$blanks = $GLOBALS['system']->getDBObjectData('person', Array('(age_bracketid' => Age_Bracket::getAdults(), '(familyid' => array_keys($families), 'mobile_tel' => '', '!status' => 'archived'), 'AND');
				$archived = $GLOBALS['system']->getDBObjectData('person', Array('(age_bracketid' => Age_Bracket::getAdults(), '(familyid' => array_keys($families), 'status' => 'archived'), 'AND');
				break;
			case 'person':
			default:
				$recips = $GLOBALS['system']->getDBObjectData('person', Array('id' => $personids, '!mobile_tel' => '', '!status' => 'archived'), 'AND');
				$blanks = $GLOBALS['system']->getDBObjectData('person', Array('id' => $personids, 'mobile_tel' => '', '!status' => 'archived'), 'AND');
				$archived = $GLOBALS['system']->getDBObjectData('person', Array('id' => $personids, 'status' => 'archived'), 'AND');
				$GLOBALS['system']->includeDBClass('person');
		}
		return Array($recips, $blanks, $archived);
	}

	/**
	 * Send an SMS message
	 * @param string $message
	 * @param array $recips	Array of person records
	 * @param boolean $saveAsNote Whether to save a note against the recipients
	 * @return array('success' => bool, 'successes' => array, 'failures' => array, 'rawresponse' => string)
	 */
	public static function sendMessage($message, $recips, $saveAsNote=FALSE)
	{
		$mobile_tels = Array();
		if (!empty($recips)) {
			foreach ($recips as $recip) {
				$mobile_tels[$recip['mobile_tel']] = 1;
			}
			$mobile_tels = array_keys($mobile_tels);
		}

		$response = '';
		$success = false;
		$content = SMS_HTTP_POST_TEMPLATE;
		$content = str_replace('_USER_MOBILE_', urlencode($GLOBALS['user_system']->getCurrentUser('mobile_tel')), $content);
		$content = str_replace('_USER_EMAIL_', urlencode($GLOBALS['user_system']->getCurrentUser('email')), $content);
		$content = str_replace('_MESSAGE_', urlencode($message), $content);
		$content = str_replace('_RECIPIENTS_COMMAS_', urlencode(implode(',', $mobile_tels)), $content);
		$content = str_replace('_RECIPIENTS_NEWLINES_', urlencode(implode("\n", $mobile_tels)), $content);
		if (ifdef('SMS_RECIPIENT_ARRAY_PARAMETER')) {
			$content = str_replace('_RECIPIENTS_ARRAY_', SMS_RECIPIENT_ARRAY_PARAMETER . '[]=' . implode('&' . SMS_RECIPIENT_ARRAY_PARAMETER . '[]=', $mobile_tels), $content);
		}
		if (strlen(ifdef('SMS_LOCAL_PREFIX')) && strlen(ifdef('SMS_INTERNATIONAL_PREFIX')) && FALSE !== strpos(SMS_HTTP_POST_TEMPLATE, '_RECIPIENTS_INTERNATIONAL')
		) {
			$intls = Array();
			foreach ($mobile_tels as $t) {
				$intls[] = self::internationaliseNumber($t);
			}
			$content = str_replace('_RECIPIENTS_INTERNATIONAL_COMMAS_', urlencode(implode(',', $intls)), $content);
			$content = str_replace('_RECIPIENTS_INTERNATIONAL_NEWLINES_', urlencode(implode("\n", $intls)), $content);
			if (ifdef('SMS_RECIPIENT_ARRAY_PARAMETER')) {
				$content = str_replace('_RECIPIENTS_INTERNATIONAL_ARRAY_', SMS_RECIPIENT_ARRAY_PARAMETER . '[]=' . implode('&' . SMS_RECIPIENT_ARRAY_PARAMETER . '[]=', $intls), $content);
			}
		}

		$header = "" . ifdef('SMS_HTTP_HEADER_TEMPLATE', '');
		$header = $header . "Content-Length: " . strlen($content) . "\r\n" . "Content-Type: application/x-www-form-urlencoded\r\n";

		$opts = Array(
			'http' => Array(
				'method' => 'POST',
				'content' => $content,
				'header' => $header
			)
		);
		// To work with HTTP Server errors ourselves, override the system error_handler
		set_error_handler(function($errno, $errstr, $errfile, $errline, array $errcontext) {
			// error was suppressed with the @-operator
			if (0 === error_reporting()) {
				return false;
			}
			throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		});
		try {
			$fp = fopen(SMS_HTTP_URL, 'r', false, stream_context_create($opts));
			if (!$fp) {
				$http_error = "ERROR: Unable to connect to SMS Server.<br>" . join("<br>", $http_response_header);
				return array("success" => false, "successes" => array(), "failures" => array(), "rawresponse" => $http_error, "error" => $http_error);
			} else {
				$response = stream_get_contents($fp);
				fclose($fp);
			}
		} catch (Exception $e) {
			$error = "ERROR: Unable to connect to SMS Server. " + $e->getMessage();
			return array("success" => false, "successes" => array(), "failures" => array(), "rawresponse" => $error, "error" => $error);
		}
		restore_error_handler(); // Restore system error_handler
		$success = !empty($response);
		$error = null;
		if (ifdef('SMS_HTTP_RESPONSE_ERROR_REGEX')) {
			if (preg_match("/" . SMS_HTTP_RESPONSE_ERROR_REGEX . "/", $response)) {
				$success = FALSE;
				$error = "$response";
			}
		}
		$successes = $failures = Array();
		if ($success) {
			$response = str_replace("\r", '', $response);
			if (ifdef('SMS_HTTP_RESPONSE_OK_REGEX')) {
				foreach ($recips as $id => $recip) {
					$reps['_RECIPIENT_INTERNATIONAL_'] = self::internationaliseNumber($recip['mobile_tel']);
					$reps['_RECIPIENT_'] = $recip['mobile_tel'];
					$pattern = '/' . str_replace(array_keys($reps), array_values($reps), SMS_HTTP_RESPONSE_OK_REGEX) . '/m';
					if (preg_match($pattern, $response)) {
						$successes[$id] = $recip;
					} else {
						$failures[$id] = $recip;
					}
				} //$recips as $id => $recip
				self::logSuccess(count($successes), $message);
				if ($saveAsNote) self::saveAsNote($successes, $message);
			} else {
				self::logSuccess(count($mobile_tels), $message);
				if ($saveAsNote) self::saveAsNote($recips, $message);
			}
		} //$success

		return array(
			"success" => $success,
			"successes" => $successes,
			"failures" => $failures,
			"rawresponse" => $response,
			"error" => $error
		);
	}

	/**
	 * Returns the international version of the supplied number
	 * @see config: SMS_LOCAL_PREFIX SMS_INTERNATIONAL_PREFIX
	 * @param string $number  Number in local format
	 * @return string  Nummber in international format, if prefixes configured, else unchanged number
	 */
	private static function internationaliseNumber($number)
	{
		if (strlen(ifdef('SMS_LOCAL_PREFIX'))
				&& strlen(ifdef('SMS_INTERNATIONAL_PREFIX'))
				&& (0 === strpos($number, (string) SMS_LOCAL_PREFIX)) // convert to string in case it is just a number, eg 0
		) {
			$number = SMS_INTERNATIONAL_PREFIX . substr($number, strlen(SMS_LOCAL_PREFIX));
		}
		return $number;
	}

	private static function logSuccess($recip_count, $message)
	{
		if (defined('SMS_SEND_LOGFILE') && ($file = constant('SMS_SEND_LOGFILE'))) {
			$msg_trunc = strlen($message) > 30 ? substr($message, 0, 27) . '...' : $message;
			error_log(date('Y-m-d H:i') . ': ' . $GLOBALS['user_system']->getCurrentUser('username') . ' (#' . $GLOBALS['user_system']->getCurrentUser('id') . ') to ' . (int) $recip_count . ' recipients: "' . $msg_trunc . "\"\n", 3, $file);
		}
	}

	public static function printModal()
	{
		?>
		<div id="send-sms-modal" class="modal sms-modal hide fade" role="dialog" aria-hidden="true">
			<div class="modal-header">
				<h4>Send SMS to <span class="sms_recipients"></span></h4>
			</div>
			<div class="modal-body">
				Message:<br />
				<div contenteditable="true" autofocus="autofocus" id="sms_message" class="sms_editor" data-maxlength="<?php echo SMS_MAX_LENGTH; ?>"></div>
				<span class="pull-right smscharactercount"><?php echo SMS_MAX_LENGTH; ?> characters remaining.</span>
			<?php
			if ($GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
				?>
				<label class="checkbox">
					<?php
					$savebydefault = "";
					if (defined("SMS_SAVE_TO_NOTE_BY_DEFAULT")) {
						if (SMS_SAVE_TO_NOTE_BY_DEFAULT) {
							$savebydefault = 'checked="checked"';
						}
					}
					?>
					<input type="checkbox" name="saveasnote" class="saveasnote" <?php echo $savebydefault; ?> />
					Save as Note
				</label>
				<?php
			}
			?>
			</div>
			<div class="modal-footer">
				<div class="results"></div>
				<button class="btn sms-submit" accesskey="s">Send</button>
				<button class="btn" data-dismiss="modal" aria-hidden="true">Close</button>
			</div>
		</div>
		<?php
	}

	private static function saveAsNote($recipients, $message)
	{
		$GLOBALS['system']->includeDBClass('person_note');
		$subject = ifdef('SMS_SAVE_TO_NOTE_SUBJECT', 'SMS Sent');
		foreach ($recipients as $id => $details) {
			// Add a note containing the SMS to the user
			$note = new Person_Note();
			$note->setValue('subject', $subject);
			$note->setvalue('details', $message);
			$note->setValue('personid', $id);
			if (!$note->create()) {
				trigger_error('Failed to save SMS as a note.');
			}
		}
	}

}
