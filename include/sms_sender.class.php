<?php
Class SMS_Sender
{
  /**
	 * Make sure that there are only unique recipients in the recipients list
	 * @param array $recipients		Array of recipients
	 * @return array Array of unique recipients
	 */
	private static function uniqueRecipients($recipients) {
    $recipientIDs = Array();
    $uniqueRecipients = Array();
    if (!empty($recipients)) {
      foreach ($recipients as $recipient) {
        if (!in_array($recipient['id'], $recipientIDs)) {
          $recipientIDs[] = $recipient['id'];
          $uniqueRecipients[] = $recipient;
        }
      }
    }
    return $uniqueRecipients;
  }

    /**
    * Get Recipients from a query
    * @param int $queryid      The ID of a query
    * @return array('recips' => array, 'blanks' => array, 'archived' => array)
    */
  function getRecipientsForQuery($queryid) {
    $recips = $archived = $blanks = Array();
    $query = $GLOBALS['system']->getDBObject('person_query', $queryid);
    $personids = $query->getResultPersonIDs();
    $recips = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, '!mobile_tel' => '', '!status' => 'archived'), 'AND');
    $blanks = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, 'mobile_tel' => '', '!status' => 'archived'), 'AND');
    $archived = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, 'status' => 'archived'), 'AND');

    return Array(self::uniqueRecipients($recips), self::uniqueRecipients($blanks), self::uniqueRecipients($archived));
  }

/**
    * Get Recipients from a group
    * @param int $groupid      The ID of a group
    * @return array('recips' => array, 'blanks' => array, 'archived' => array)
    */
  function getRecipientsForGroup($groupid) {
    $recips = $archived = $blanks = Array();
    $group = $GLOBALS['system']->getDBObject('person_group', $groupid);
    $personids = array_keys($group->getMembers());
    $recips = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, '!mobile_tel' => '', '!status' => 'archived'), 'AND');
    $blanks = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, 'mobile_tel' => '', '!status' => 'archived'), 'AND');
    $archived = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, 'status' => 'archived'), 'AND');

    return Array(self::uniqueRecipients($recips), self::uniqueRecipients($blanks), self::uniqueRecipients($archived));
  }

  /**
    * Get Recipients from a roster
    * @param int $roster_view   The ID of a roster
    * @param string $start_date
    * @param string $end_date
    * @return array('recips' => array, 'blanks' => array, 'archived' => array)
    */
  function getRecipientsForRoster($roster_view, $start_date, $end_date) {
    $recips = $archived = $blanks = Array();
    $view = $GLOBALS['system']->getDBObject('roster_view', $roster_view);
    $recips = $view->getAssignees($start_date, $end_date);
    foreach ($recips as $i => $details) {
      if ($details['status'] == 'archived') {
        $archived[$i] = $details;
        unset($recips[$i]);
      } else if (empty($details['mobile_tel'])) {
        $blanks[$i] = $details;
        unset($recips[$i]);
      }
    }

    return Array(self::uniqueRecipients($recips), self::uniqueRecipients($blanks), self::uniqueRecipients($archived));
  }

  /**
    * Get Recipients for either a person or a family
    * @param int $personid      The ID of a person in the family
    * @return array('recips' => array, 'blanks' => array, 'archived' => array)
    */
  function getRecipientsForFamily($personid  ) {
    $recips = $archived = $blanks = Array();
    $GLOBALS['system']->includeDBClass('family');
    $families = Family::getFamilyDataByMemberIDs($personid);
    $recips = $GLOBALS['system']->getDBObjectData('person', Array('age_bracket' => '0', '(familyid' => array_keys($families), '!mobile_tel' => '', '!status' => 'archived'), 'AND');
    $blanks = $GLOBALS['system']->getDBObjectData('person', Array('age_bracket' => '0', '(familyid' => array_keys($families), 'mobile_tel' => '', '!status' => 'archived'), 'AND');
    $archived = $GLOBALS['system']->getDBObjectData('person', Array('age_bracket' => '0', '(familyid' => array_keys($families), 'status' => 'archived'), 'AND');

    return Array($recips,$blanks,$archived);
  }
  /**
    * Get Recipient information for a person
    * @param $personid      The ID of a person
    * @return array('recips' => array, 'blanks' => array, 'archived' => array)
    */
  function getRecipientForPerson( $personid  ) {
    $recips = $archived = $blanks = Array();
    if (!empty($personid)) {
      $recips = $GLOBALS['system']->getDBObjectData('person', Array('id' => $personid, '!mobile_tel' => '', '!status' => 'archived'), 'AND');
      $blanks = $GLOBALS['system']->getDBObjectData('person', Array('id' => $personid, 'mobile_tel' => '', '!status' => 'archived'), 'AND');
      $archived = $GLOBALS['system']->getDBObjectData('person', Array('id' => $personid, 'status' => 'archived'), 'AND');
      $GLOBALS['system']->includeDBClass('person');
    }

    return Array($recips,$blanks,$archived);
  }

  /**
    * Get Recipients based on the $_REQUEST
    * @return array('recips' => array, 'blanks' => array, 'archived' => array)
    */
  public function getRecipients() {
    $recips = $archived = $blanks = Array();
    if (!empty($_REQUEST['queryid'])) {
        list($recips,$blanks,$archived) = self::getRecipientsForQuery((int)$_REQUEST['queryid']);
    } else if (!empty($_REQUEST['groupid'])) {
        list($recips,$blanks,$archived) = self::getRecipientsForGroup((int)$_REQUEST['groupid']);
    } else if (!empty($_REQUEST['roster_view'])) {
        error_log("Foudn roster " . $_REQUEST['roster_view']);
        list($recips,$blanks,$archived) = self::getRecipientsForRoster((int)$_REQUEST['roster_view'],$_REQUEST['start_date'], $_REQUEST['end_date']);
    } else if (!empty($_REQUEST['personid'])) {
        $smstype = 'person';
        if (!empty($_REQUEST['sms_type'])) {
            $smstype= $_REQUEST['sms_type'];
        }
        switch ($smstype) {
          case 'family':
            list($recips,$blanks,$archived) = self::getRecipientsForFamily($_REQUEST['personid']);
            break;
          case 'person':
          default:
            list($recips,$blanks,$archived) = self::getRecipientForPerson($_REQUEST['personid']);
            break;
        }
    }
    return Array($recips, $blanks, $archived);
  }

  /**
    * Send an SMS message
    * @param string $message
    * @param array $recips	Array of person records
    * @return array('success' => bool, 'successes' => array, 'failures' => array, 'rawresponse' => string)
    */
  function sendMessage($message, $recips)
  {

    $mobile_tels = Array();
    if (!empty($recips)) {
      foreach ($recips as $recip) {
        $mobile_tels[$recip['mobile_tel']] = 1;
      }
      $mobile_tels = array_keys($mobile_tels);
    }

    $response = '';
    $success  = false;
    $content  = SMS_HTTP_POST_TEMPLATE;
    $content  = str_replace('_USER_MOBILE_', urlencode($GLOBALS['user_system']->getCurrentUser('mobile_tel')), $content);
    $content  = str_replace('_USER_EMAIL_', urlencode($GLOBALS['user_system']->getCurrentUser('email')), $content);
    $content  = str_replace('_MESSAGE_', urlencode($message), $content);
    $content  = str_replace('_RECIPIENTS_COMMAS_', urlencode(implode(',', $mobile_tels)), $content);
    $content  = str_replace('_RECIPIENTS_NEWLINES_', urlencode(implode("\n", $mobile_tels)), $content);
    if (ifdef('SMS_RECIPIENT_ARRAY_PARAMETER')) {
        $content = str_replace('_RECIPIENTS_ARRAY_', SMS_RECIPIENT_ARRAY_PARAMETER . '[]=' . implode('&' . SMS_RECIPIENT_ARRAY_PARAMETER . '[]=', $mobile_tels), $content);
    }
    if (strlen(ifdef('SMS_LOCAL_PREFIX'))
        && strlen(ifdef('SMS_INTERNATIONAL_PREFIX'))
        && FALSE !== strpos(SMS_HTTP_POST_TEMPLATE, '_RECIPIENTS_INTERNATIONAL')
    ) {
        $intls = Array();
        foreach ($mobile_tels as $t) {
                $intls[] = self::internationalizeNumber($t);
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
                return array("success" => false, "successes" => array(), "failures" => array(), "rawresponse" => $http_error);
        } else {
                $response = stream_get_contents($fp);
                fclose($fp);
        }
    } catch (Exception $e) {
        $error = "ERROR: Unable to connect to SMS Server. " + $e->getMessage();
        return array("success" => false, "successes" => array(), "failures" => array(), "rawresponse" => $error);
    }
    restore_error_handler(); // Restore system error_handler
    $success   = !empty($response);
    $successes = $failures = Array();
    if ($success) {
      $response = str_replace("\r", '', $response);
      if (SMS_HTTP_RESPONSE_OK_REGEX) {
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
      } else {
        self::logSuccess(count($mobile_tels), $message);
      }
    } //$success

    return array(
      "success" => $success,
      "successes" => $successes,
      "failures" => $failures,
      "rawresponse" => $response,
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
                && (0 === strpos($number,(string)SMS_LOCAL_PREFIX)) // convert to string in case it is just a number, eg 0
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

    public static function printModal() {
      ?>
      <div id="send-sms-modal" class="modal sms-modal hide fade" role="dialog" aria-hidden="true">
        <div class="modal-header">
          <h4>Send SMS to <span class="sms_recipients"></span></h4>
        </div>
        <div class="modal-body">
          Message:<br />
          <div contenteditable="true" autofocus="autofocus" id="sms_message" class="sms_editor" data-maxlength="<?php echo SMS_MAX_LENGTH; ?>"></div>
          <span id="smscharactercount"><?php echo SMS_MAX_LENGTH; ?> characters remaining.</span>
          <div class="results"></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-warning sms-status fade">Send failed - see details</button>
          <?php
            $savebydefault = "";
            if (defined("SMS_SAVE_TO_NOTE_BY_DEFAULT")) {
              if (SMS_SAVE_TO_NOTE_BY_DEFAULT) {
                $savebydefault = 'checked="checked"';
              }
            }
          ?>
          <span class="sms-modal-option"><input type="checkbox" name="saveasnote" class="saveasnote" <?php echo $savebydefault; ?> />Save as Note</span>
          <button class="btn sms-submit" accesskey="s">Send</button>
          <button class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>
        </div>
      </div>
      <?php
    }

  public function saveAsNote($recipients, $message) {
    $GLOBALS['system']->includeDBClass('person_note');
    $subject = "SMS Sent";
    if (!SMS_SAVE_TO_NOTE_SUBJECT) {
      $subect = SMS_SAVE_TO_NOT_SUBJECT;
    } //!SMS_SAVE_TO_NOTE_SUBJECT
    foreach ($recipients as $id => $details) {
      error_log("ID: $id, $subject, $message");
      // Add a note containing the SMS to the user
      $note = new Person_Note();
      $note->setValue('subject', $subject);
      $note->setvalue('details', $message);
      $note->setValue('personid', $id);
      if (!$note->create()) {
        error_log('Failed to save SMS as a note.');
      } //!$note->create()
    } //$recipients as $id => $details
  }
}
?>
