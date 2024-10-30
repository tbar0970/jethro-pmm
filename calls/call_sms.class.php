<?php
class Call_sms extends Call
{
  function run()
  {
    require_once('include/sms_sender.class.php');
    $recips = $successes = $failures = $archived = $blanks = Array();
    list($recips, $blanks, $archived) = SMS_Sender::getRecipients();

    $ajax = array();

    if (!empty($archived)) {
      $ajax['failed_archived']['count'] = count($archived);
      $ajax['failed_archived']['recipients'] = $archived;
    }
    if (!empty($blanks)) {
      $ajax['failed_blank']['count'] = count($blanks);
      $ajax['failed_blank']['recipients'] = $blanks;
    }
        
    if (!empty($recips))  {
      $message = $_POST['message'];
      if (empty($message)) {
        $ajax['error'] = "Empty message";
      } else if (strlen($message) > SMS_MAX_LENGTH) {
        $ajax['error'] = "Message too long";
      } else {
        $successes = $failures = $rawresponse = Array();
		$sendResponse = SMS_Sender::sendMessage($message, $recips, array_get($_REQUEST, 'saveasnote'));
		$executed = $sendResponse['executed'];
		$successes = $sendResponse['successes'];
		$failures = $sendResponse['failures'];
		$rawresponse = $sendResponse['rawresponse'];
		$error = $sendResponse['error'];

		$ajax['rawresponse'] = $rawresponse;

        if (!$executed) {
          $ajax['error'] = "Unable to send SMS\n" . $error;
        } else {
          if ((!empty($successes)) || (!empty($failures))) { // we managed to parse the server response to get information
            if (!empty($successes)) {
              $ajax['sent']['count'] = count($successes);
              $ajax['sent']['recipients'] = $successes;
              $ajax['sent']['confirmed'] = true;
              $ajax['sent']['request'] = $_REQUEST;
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
      }
    }
    echo json_encode($ajax);
  }

}
