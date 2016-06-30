<?php

class SMS_Send
{
    
  function hasmobile_tel($recips) {
    $mobile_tels = Array();
    if (!empty($recips)) {
      foreach ($recips as $recip) {
        $mobile_tels[$recip['mobile_tel']] = 1;
      }
      $mobile_tels = array_keys($mobile_tels);
    }
    
    return $mobile_tels;
  }
    
  function getRecipientsForQuery($queryid) {
    $recips = $archived = $blanks = $mobile_tels = Array();
    $query = $GLOBALS['system']->getDBObject('person_query', $queryid);
    $personids = $query->getResultPersonIDs();
    $recips = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, '!mobile_tel' => '', '!status' => 'archived'), 'AND');
    $blanks = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, 'mobile_tel' => '', '!status' => 'archived'), 'AND');
    $archived = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, 'status' => 'archived'), 'AND');
    
    $mobile_tels = hasmobile_tel($recips);
    return Array($recips,$blanks,$archived,$mobile_tels);
  }
  
  function getRecipientsForGroup($groupid) {
    $recips = $archived = $blanks = $mobile_tels = Array();
    $group = $GLOBALS['system']->getDBObject('person_group', $groupid);
    $personids = array_keys($group->getMembers());
    $recips = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, '!mobile_tel' => '', '!status' => 'archived'), 'AND');
    $blanks = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, 'mobile_tel' => '', '!status' => 'archived'), 'AND');
    $archived = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, 'status' => 'archived'), 'AND');
    
    $mobile_tels = hasmobile_tel($recips);
    return Array($recips,$blanks,$archived,$mobile_tels);
  }
  
  function getRecipientsForRoster($roster_view, $start_date, $end_date) {
    $recips = $archived = $blanks = $mobile_tels = Array();
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
    
    $mobile_tels = hasmobile_tel($recips);
    return Array($recips,$blanks,$archived,$mobile_tels);
  }
  
  function getRecipients( $sms_type, $personid  ) {
    $recips = $archived = $blanks = $mobile_tels = Array();
    switch ($sms_type) {
        case 'family':
          $GLOBALS['system']->includeDBClass('family');
          $families = Family::getFamilyDataByMemberIDs($personid);
          $recips = $GLOBALS['system']->getDBObjectData('person', Array('age_bracket' => '0', '(familyid' => array_keys($families), '!mobile_tel' => '', '!status' => 'archived'), 'AND');
          $blanks = $GLOBALS['system']->getDBObjectData('person', Array('age_bracket' => '0', '(familyid' => array_keys($families), 'mobile_tel' => '', '!status' => 'archived'), 'AND');
          $archived = $GLOBALS['system']->getDBObjectData('person', Array('age_bracket' => '0', '(familyid' => array_keys($families), 'status' => 'archived'), 'AND');
          break;
        case 'person':
        default:
          if (!empty($personid)) {
            $recips = $GLOBALS['system']->getDBObjectData('person', Array('id' => $personid, '!mobile_tel' => '', '!status' => 'archived'), 'AND');
            $blanks = $GLOBALS['system']->getDBObjectData('person', Array('id' => $personid, 'mobile_tel' => '', '!status' => 'archived'), 'AND');
            $archived = $GLOBALS['system']->getDBObjectData('person', Array('id' => $personid, 'status' => 'archived'), 'AND');
            $GLOBALS['system']->includeDBClass('person');
          }
          break;
      }

    $mobile_tels = hasmobile_tel($recips);
    return Array($recips,$blanks,$archived,$mobile_tels);
  }

  
  function sendSMS($message, $recips, $mobile_tels)
  {
    $response = '';
    $success  = false;
    $content  = SMS_HTTP_POST_TEMPLATE;
    $content  = str_replace('_USER_MOBILE_', urlencode($GLOBALS['user_system']->getCurrentUser('mobile_tel')), $content);
    $content  = str_replace('_USER_EMAIL_', urlencode($GLOBALS['user_system']->getCurrentUser('email')), $content);
    $content  = str_replace('_MESSAGE_', urlencode($message), $content);
    $content  = str_replace('_RECIPIENTS_COMMAS_', urlencode(implode(',', $mobile_tels)), $content);
    $content  = str_replace('_RECIPIENTS_NEWLINES_', urlencode(implode("\n", $mobile_tels)), $content);
    if (defined('SMS_RECIPIENT_ARRAY_PARAMETER')) {
      $content = str_replace('_RECIPIENTS_ARRAY_', SMS_RECIPIENT_ARRAY_PARAMETER . '[]=' . implode('&' . SMS_RECIPIENT_ARRAY_PARAMETER . '[]=', $mobile_tels), $content);
    } //defined('SMS_RECIPIENT_ARRAY_PARAMETER')
    
    $header = "" . SMS_HTTP_HEADER_TEMPLATE;
    $header = $header . "Content-Length: " . strlen($content) . "\r\n" . "Content-Type: application/x-www-form-urlencoded\r\n";
    
    $opts = Array(
      'http' => Array(
        'method' => 'POST',
        'content' => $content,
        'header' => $header
      )
    );
    // To work with HTTP Server errors ourselves, override the system error_handler
    set_error_handler(null);
    try {
      $fp = @fopen(SMS_HTTP_URL, 'r', false, stream_context_create($opts));
      if (!$fp) {
        $http_error = "ERROR: Unable to connect to SMS Server.<br>" . join("<br>", $http_response_header);
        return array(
          "success" => false,
          "successes" => array(),
          "failures" => array(),
          "rawresponse" => $http_error
        );
      } //!$fp
      else {
        $response = stream_get_contents($fp);
        fclose($fp);
      }
    }
    catch (Exception $e) {
      $error = "ERROR: Unable to connect to SMS Server. " + $e->getMessage();
      return array(
        "success" => false,
        "successes" => array(),
        "failures" => array(),
        "rawresponse" => $error
      );
    }
    restore_error_handler(); // Restore system error_handler
    $success   = !empty($response);
    $successes = $failures = Array();
    if ($success) {
      $response = str_replace("\r", '', $response);
      if (SMS_HTTP_RESPONSE_OK_REGEX) {
        foreach ($recips as $id => $recip) {
          $internationalisedmatch = false;
          if ($recip['mobile_tel'][0] === '0') { // some apis return the number in international format - starting with 61 for Australia
            $internationalisedPhone = preg_quote(SMS_COUNTRY_CODE . substr($recip['mobile_tel'], 1));
            $internationalpattern   = '/' . str_replace('_RECIPIENT_', $internationalisedPhone, SMS_HTTP_RESPONSE_OK_REGEX) . '/m';
            $internationalisedmatch = preg_match($internationalpattern, $response);
          } //$recip['mobile_tel'][0] === '0'
          
          $pattern  = '/' . str_replace('_RECIPIENT_', preg_quote($recip['mobile_tel']), SMS_HTTP_RESPONSE_OK_REGEX) . '/m';
          $response = $response . "  IntPattern: $internationalpattern";
          $response = $response . "  Pattern: $pattern";
          
          if ($internationalisedmatch || preg_match($pattern, $response)) {
            $successes[$id] = $recip;
          } //$internationalisedmatch || preg_match($pattern, $response)
          else {
            $failures[$id] = $recip;
          }
        } //$recips as $id => $recip
      } //SMS_HTTP_RESPONSE_OK_REGEX
    } //$success
    return array(
      "success" => $success,
      "successes" => $successes,
      "failures" => $failures,
      "rawresponse" => $response
    );
  }
  
  
  function saveAsNote($recipients, $message)
  {
    $GLOBALS['system']->includeDBClass('person_note');
    $subject = "SMS Sent";
    if (!SMS_SAVE_TO_NOTE_SUBJECT) {
      $subect = SMS_SAVE_TO_NOT_SUBJECT;
    } //!SMS_SAVE_TO_NOTE_SUBJECT
    foreach ($recipients as $id => $details) {
      // Add a note containing the SMS to the user
      $note = new Person_Note();
      $note->setValue('subject', $subject);
      $note->setvalue('details', $message);
      $note->setValue('personid', $id);
      if (!$note->create()) {
        add_message('Failed to save SMS as a note.');
      } //!$note->create()
    } //$recipients as $id => $details
  }
  
}
?>
