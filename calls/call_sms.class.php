<?php
class Call_sms extends Call
{
  function run() 
  {
    require_once('include/sms_sender.class.php');
    $SMS = new SMS_Sender();
    $recips = $successes = $failures = $archived = $blanks = Array();
    list($recips, $blanks, $archived) = $SMS::getRecipients();
    
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
        $sendResponse = $SMS::sendMessage($message,$recips);
        $success = $sendResponse['success'];
        $successes = $sendResponse['successes'];
        $failures = $sendResponse['failures'];
        $rawresponse = $sendResponse['rawresponse'];

        $ajax['rawresponse'] = $rawresponse;
        if (!$success) {
          $ajax['error']="Failure communicating with SMS server";
          if (preg_match(SMS_HTTP_RESPONSE_ERROR_REGEX, $rawresponse)) {
            $ajax['error'] = $rawresponse;
          }
        } else {
          if (preg_match(SMS_HTTP_RESPONSE_ERROR_REGEX, $rawresponse)) {
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
                  $SMS::saveAsNote($successes, $message);
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

}


?>
