<?php

Class SMS_Sender
{

	/**
	 * Return true if we are able to send a message, considering config, perms etc.
	 */
	public static function canSend()
	{
		return ifdef('SMS_HTTP_URL')
				&& ifdef('SMS_HTTP_POST_TEMPLATE')
				&& $GLOBALS['user_system']->havePerm(PERM_SENDSMS);
	}

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
	 * Remove invalid characters from message.
	 * Only understands GSM0338 at the moment - any other charset does not get filtered.
	 * @see SMS_ENCODING setting/constant.  Defaults to GSM0338.
	 * @param string $message
	 * @return string
	 */
	private static function cleanseMessage($message)
	{
		$encoding = strtoupper(ifdef('SMS_ENCODING', 'GSM0338'));
		switch ($encoding) {
			case 'GSM0338':
				$gsm0338 = array(
					'@','Δ',' ','0','¡','P','¿','p',
					'£','_','!','1','A','Q','a','q',
					'$','Φ','"','2','B','R','b','r',
					'¥','Γ','#','3','C','S','c','s',
					'è','Λ','¤','4','D','T','d','t',
					'é','Ω','%','5','E','U','e','u',
					'ù','Π','&','6','F','V','f','v',
					'ì','Ψ','\'','7','G','W','g','w',
					'ò','Σ','(','8','H','X','h','x',
					'Ç','Θ',')','9','I','Y','i','y',
					"\n",'Ξ','*',':','J','Z','j','z',
					'Ø',"\x1B",'+',';','K','Ä','k','ä',
					'ø','Æ',',','<','L','Ö','l','ö',
					"\r",'æ','-','=','M','Ñ','m','ñ',
					'Å','ß','.','>','N','Ü','n','ü',
					'å','É','/','?','O','§','o','à'
				 );
				if (function_exists('mb_strlen')) {
					$len = mb_strlen($message, 'UTF-8');
					$out = '';
					for ($i=0; $i < $len; $i++) {
						$char = mb_substr($message,$i,1,'UTF-8');
						if (in_array($char, $gsm0338)) {
							$out .= $char;
						} else {
							error_log('SMS sender Discarded invalid char "'.$char.'" (ord '.ord($char).')');
						}
					}
					return $out;
				} else {
					$len = strlen($message);
					$out = '';
					for ($i=0; $i < $len; $i++) {
						$char = substr($message,$i,1);
						if (in_array($char, $gsm0338)) {
							$out .= $char;
						} else {
							error_log('SMS sender Discarded invalid char "'.$char.'" (ord '.ord($char).')');
						}
					}
					return $out;

				}
		}
		return $message;

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
		$message = self::cleanseMessage($message);
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

		$me = $GLOBALS['system']->getDBObject('person', $GLOBALS['user_system']->getCurrentUser('id'));
		if (FALSE !== strpos($content, '_USER_MOBILE_')) {
			if (empty($me)) {
				trigger_error("Your SMS config includes the _USER_MOBILE_ keyword but there is no current user!  Exiting.", E_USER_ERROR);
			}
			if (!strlen($me->getValue('mobile_tel'))) {
				return Array('success' => FALSE, 'successes' => Array(), 'failures' => Array(), 'rawresponse' => '',
					'error' => 'You must save your own mobile number before you can send an SMS');
			}
			$content = str_replace('_USER_MOBILE_', urlencode($me->getValue('mobile_tel')), $content);
		}

		if (FALSE !== strpos($content, '_USER_EMAIL_')) {
			if (empty($me)) {
				trigger_error("Your SMS config includes the _USER_EMAIL_ keyword but there is no current user!  Exiting.", E_USER_ERROR);
			}
			$content = str_replace('_USER_EMAIL_', urlencode($me->getValue('email')), $content);
		}

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
