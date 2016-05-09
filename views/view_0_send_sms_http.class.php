<?php
class View__Send_SMS_HTTP extends View
{
  function getTitle()
  {
    return 'Send SMS';
  }

  function getRecipients() {
    $recips = $archived = $blanks = $mobile_tels = Array();
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
    if (!empty($recips)) {
      foreach ($recips as $recip) {
        $mobile_tels[$recip['mobile_tel']] = 1;
      }
      $mobile_tels = array_keys($mobile_tels);
    }

    return Array($recips,$blanks,$archived,$mobile_tels);
  }

  function sendSMS($message, $recips, $mobile_tels)
  {
    $response = '';
    $success = false;
    $content = SMS_HTTP_POST_TEMPLATE;
    $content = str_replace('_USER_MOBILE_', urlencode($GLOBALS['user_system']->getCurrentUser('mobile_tel')), $content);
    $content = str_replace('_USER_EMAIL_', urlencode($GLOBALS['user_system']->getCurrentUser('email')), $content);
    $content = str_replace('_MESSAGE_', urlencode($message), $content);
    $content = str_replace('_RECIPIENTS_COMMAS_', urlencode(implode(',', $mobile_tels)), $content);
    $content = str_replace('_RECIPIENTS_NEWLINES_', urlencode(implode("\n", $mobile_tels)), $content);

    $header  = "" . SMS_HTTP_HEADER_TEMPLATE;
    $header  = $header . "Content-Length: ".strlen($content)."\r\n"
                       . "Content-Type: application/x-www-form-urlencoded\r\n";

    $opts = Array(
      'http' => Array(
        'method'        => 'POST',
        'content'       => $content,
        'header'        => $header
      )
    );
    // To work with HTTP Server errors ourselves, override the system error_handler
    set_error_handler(null);
    try {
      $fp = @fopen(SMS_HTTP_URL, 'r', false, stream_context_create($opts));
      if (!$fp) {
        $http_error = "ERROR: Unable to connect to SMS Server.<br>" . join("<br>", $http_response_header);
        return array("success"=>false, "successes"=>array(), "failures"=>array(),"rawresponse"=>$http_error);
      } else {
        $response = stream_get_contents($fp);
        fclose($fp);
      }
    } catch (Exception $e) {
      $error = "ERROR: Unable to connect to SMS Server. " + $e->getMessage();
      return array("success"=>false, "successes"=>array(), "failures"=>array(),"rawresponse"=>$error);
    }
    restore_error_handler(); // Restore system error_handler
    $success = !empty($response);
    $successes = $failures = Array();
    if ($success) {
      $response = str_replace("\r", '', $response);
      if (SMS_HTTP_RESPONSE_OK_REGEX) {
        foreach ($recips as $id => $recip) {
          $internationalisedmatch = false;
          if ($recip['mobile_tel'][0] === '0') { // some apis return the number in international format - starting with 61 for Australia
            $internationalisedPhone = preg_quote(SMS_COUNTRY_CODE . substr($recip['mobile_tel'], 1));
            $internationalpattern = '/' . str_replace('_RECIPIENT_', $internationalisedPhone, SMS_HTTP_RESPONSE_OK_REGEX) . '/m';
            $internationalisedmatch = preg_match($internationalpattern,$response);
          }

          $pattern = '/'.str_replace('_RECIPIENT_', preg_quote($recip['mobile_tel']), SMS_HTTP_RESPONSE_OK_REGEX).'/m';
          $response = $response . "  IntPattern: $internationalpattern";
          $response = $response . "  Pattern: $pattern";

          if ($internationalisedmatch || preg_match($pattern, $response)) {
            $successes[$id] = $recip;
          } else {
            $failures[$id] = $recip;
          }
        }
      }
    }
    return array("success"=>$success, "successes"=>$successes, "failures"=>$failures,"rawresponse"=>$response);
  }

  function saveAsNote($recipients, $message) {
    $GLOBALS['system']->includeDBClass('person_note');
    $subject = "SMS Sent";
    if (!SMS_SAVE_TO_NOTE_SUBJECT) {
      $subect = SMS_SAVE_TO_NOT_SUBJECT;
    }
    foreach ($recipients as $id => $details) {
      // Add a note containing the SMS to the user
      $note = new Person_Note();
      $note->setValue('subject', $subject);
      $note->setvalue('details', $message);
      $note->setValue('personid', $id);
      if (!$note->create()) {
        add_message('Failed to save SMS as a note.');
      }
    }
  }

  function printAjax()
  {
    $recips = $successes = $failures = $archived = $blanks = Array();
    list($recips,$blanks,$archived,$mobile_tels) = $this->getRecipients();
    $ajax = array();
    if (empty($recips)) {
      $ajax['error']='No recipients selected. Message not sent';
      $ajax['rawresponse']='ERROR: No recipients selected.';
    } else {
      $message = $_POST['message'];
      if (empty($message)) {
        $ajax['error']="Empty message";
      } else if (strlen($message) > SMS_MAX_LENGTH) {
        $ajax['error']="Message too long";
      } else {
        $sendResponse = $this->sendSMS($message,$recips,$mobile_tels);
        $success = $sendResponse['success'];
        $successes = $sendResponse['successes'];
        $failures = $sendResponse['failures'];
        $rawresponse = $sendResponse['rawresponse'];

        $ajax['rawresponse'] = $rawresponse;
        if (!$success) {
          $ajax['error']="Failure communicating with SMS server";
          if (preg_match('#^ERR#i', $rawresponse)) { // Check for ERROR response (could be abstracted into sms provider classes?)
            $ajax['error'] = $rawresponse;
          }
        } else {
          if (preg_match('#^ERR#i', $rawresponse)) { // Check for ERROR response (could be abstracted into sms provider classes?)
            $ajax['error'] = $rawresponse;
          }
          if ((!empty($successes)) || (!empty($failures))) { // we managed to parse the server response to get information
            if (!empty($successes)) {
              $ajax['sent']['count'] = count($successes);
              $ajax['sent']['recipients'] = $successes;
              $ajax['sent']['confirmed'] = true;
	      $ajax['sent']['request'] = $_REQUEST;
              if (isset($_REQUEST['saveasnote'])) {
			if (($_REQUEST['saveasnote'] == '1') || ($_REQUEST['saveasnote'] == 'on')) {
	        	        $this->saveAsNote($successes, $message);
				$ajax['sent']['saveasnote'] = $_REQUEST['saveasnote'];
			}
              }
            }
            if (!empty($failures)) {
              $ajax['failed']['count'] = count($failures);
              $ajax['failed']['recipients'] = $failures;
            }
          } else {
            $ajax['sent']['count'] = count($recips);
            $ajax['sent']['recipients'] = $recips;
            $ajax['sent']['confirmed'] = false;
          }
        }
        if (!empty($archived)) {
          $ajax['failed_archived']['count'] = count($archived);
          $ajax['failed_archived']['recipients'] = $archived;
        }
        if (!empty($blanks)) {
          $ajax['failed_blank']['count'] = count($blanks);
          $ajax['failed_blank']['recipients'] = $blanks;
        }
      }
    }
    echo json_encode($ajax);
  }

  function printView()
  {
    $recips = $successes = $failures = $archived = $blanks = Array();
    list($recips,$blanks,$archived,$mobile_tels) = $this->getRecipients();

    if (empty($recips)) {
      print_message("Did not find any recipients with mobile numbers.  Message not sent.", 'error');
    } else {
      $message = $_POST['message'];
      if (empty($message) || strlen($message) > SMS_MAX_LENGTH) {
        print_message("Your message is empty or too long", "error");
        return;
      }

      list($success,$successes,$failures,$rawresponse) = $this->sendSMS($message,$recips,$mobile_tels);
      $sendResponse = $this->sendSMS($message,$recips,$mobile_tels);
      $success = $sendResponse['success'];
      $successes = $sendResponse['successes'];
      $failures = $sendResponse['failures'];
      $rawresponse = $sendResponse['rawresponse'];
      if (!$success) {
        add_message('Failed communicating with SMS server - please check your config', 'failure');
        return;
      }
      if ((!empty($successes)) || (!empty($failures))) {
        if (!empty($successes)) {
          print_message('SMS sent successfully to '.count($successes).' recipients');
          $this->logSuccess(count($successes), $message);
        }
        if (!empty($failures)) {
          print_message('SMS sending failed for '.count($failures).' recipients', 'failure');
?>
<p><b>Sending an SMS to the following recipients failed.  <span class="clickable" onclick="$('#response').toggle()">Show server response</span></b></p>
<div class="hidden standard" id="response"><?php echo $response; ?></div>
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

  function logSuccess($recip_count, $message) {
    if (defined('SMS_SEND_LOGFILE') && ($file = constant('SMS_SEND_LOGFILE'))) {
      $msg_trunc = strlen($message) > 30 ? substr($message, 0, 27).'...' : $message;
      error_log(date('Y-m-d H:i').': '.$GLOBALS['user_system']->getCurrentUser('username').' (#'.$GLOBALS['user_system']->getCurrentUser('id').') to '.(int)$recip_count.' recipients: "'.$msg_trunc."\"\n", 3, $file);
    }
  }

}
?>
