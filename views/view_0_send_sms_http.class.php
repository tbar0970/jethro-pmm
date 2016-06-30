<?php
class View__Send_SMS_HTTP extends View
{
	function getTitle()
	{
		return 'Send SMS';
	}

	function printView() 
	{
                require_once('include/sms_sender.class.php');
                $SMS = new SMS_Sender();
                
		$recips = $successes = $failures = $archived = $blanks = Array();
		if (!empty($_REQUEST['queryid'])) {
                    list($recips,$blanks,$archived,$mobile_tels) = $SMS->getRecipientsForQuery((int)$_REQUEST['queryid']); 
		} else if (!empty($_REQUEST['groupid'])) {
                    list($recips,$blanks,$archived,$mobile_tels) = $SMS->getRecipientsForGroup((int)$_REQUEST['groupid']); 
		} else if (!empty($_REQUEST['roster_view'])) {
                    list($recips,$blanks,$archived,$mobile_tels) = $SMS->getRecipientsForRoster((int)$_REQUEST['roster_view'],$_REQUEST['start_date'], $_REQUEST['end_date']); 
		} else {
                    list($recips,$blanks,$archived,$mobile_tels) = $SMS->getRecipients(array_get($_REQUEST, 'sms_type'), $_POST['personid']); 
		}

		if (empty($recips)) {
			print_message("Did not find any recipients with mobile numbers.  Message not sent.", 'error');
		} else {
                    $message = array_get($_POST, 'message');
                    // Known issue: if their session was timed out when they entered the message,
                    // the message is not propagated after they submit the login form, so they'll get the
                    // "message is empty" error here.
                    if (empty($message) || strlen($message) > SMS_MAX_LENGTH) {
                            print_message("Your message is empty or too long", "error");
                            return;
                    }
                    
                    $sendResponse = $SMS->sendSMS($message,$recips,$mobile_tels);
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
