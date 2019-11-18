<?php
/*****************************************************************
This script will send roster reminder emails
set-up a cron-job to run the script ast follows

php /path/to/this/script/roster_reminder.php /path/to/ini/file/roster_reminder_sample.ini

Note: This script will only work with roster views that are made public
Where's the roster id number?  When you view a roster via the /jethro/public directory you'll see the roster id number in the url (eg. &roster_view=1)

Use .ini file to set the following
 - how to send the messages: sms, email, both
 - roster coordinator id
 - roster coordinator email / group
 - roster view number
 - pre_message to go with roster reminder
 - post_message to go with roster reminder
 - sms from number
 - email from address
 - email from name
 - email subject
 - debug (only send to roster coordinator)
 - email method (email_class or php mail())

TWO MESSAGES WILL BE SENT
 - one to the assignees (to: roster coordinator bcc: assignees - content = roster table, roster message, how to tell the roster coordinator if you can't do the task allocated to you etc.
 - second to the roster coordinator including a note listing those assignees w/o an email (i.e. who will not have received the email update)

IMPROVEMENTS?
When setting up a roster view there could be an option to include roster reminders. If including roster reminders then also the person (person id) or group (group id) who is/are the roster coordinator/s. And the time when you want the roster reminder to be sent (remembering that the server Jethro sits on may be operating in a different time-zone).
******************************************************************/
//
//pull varialbes in from ini file
//
if (empty($_SERVER['argv'][1]) || !is_readable($_SERVER['argv'][1])) {
	echo "You must specify an ini file as the first argument \n";
	echo "Eg:  php email_report.php email_report_sample.ini \n";
	exit;
}
ini_set('display_errors', 1);
define('JETHRO_ROOT', dirname(dirname(__FILE__)));
set_include_path(get_include_path().PATH_SEPARATOR.JETHRO_ROOT);
if (!is_readable(JETHRO_ROOT.'/conf.php')) {
	trigger_error('Jethro configuration file not found.  You need to copy conf.php.sample to conf.php and edit it before Jethro can run', E_USER_ERROR);
	exit(1);
}

require_once JETHRO_ROOT.'/conf.php';
define('DB_MODE', 'private');
//if (!defined('DSN')) define('DSN', constant('PRIVATE_DSN'));
require_once JETHRO_ROOT.'/include/init.php';
require_once JETHRO_ROOT.'/include/user_system.class.php';
$GLOBALS['user_system'] = new User_System();
$GLOBALS['user_system']->setPublic();

$ini = parse_ini_file($_SERVER['argv'][1]);
//this is a bit verbose - to aid with fault-finding/testing
$messagetype=$ini['MESSAGE_TYPE'];
$sendemail=($messagetype==='email') || ($messagetype==='both');
$sendsms=($messagetype==='sms') || ($messagetype==='both');
$roster_coordinator=$ini['ROSTER_COORDINATOR'];
$roster_coordinator_id=$ini['ROSTER_COORDINATOR_ID'];
$roster_id=$ini['ROSTER_ID'];
$pre_message=$ini['PRE_MESSAGE'];
$post_message=$ini['POST_MESSAGE'];
$email_from=$ini['EMAIL_FROM'];
$email_from_name=$ini['EMAIL_FROM_NAME'];
$email_subject=$ini['EMAIL_SUBJECT'];
$list_not_table=$ini['LIST_NOT_TABLE'];
$include_roster_content=array_get($ini, 'INCLUDE_ROSTER_CONTENT', 1);
//
$debug=$ini['DEBUG'];
$verbose=$ini['VERBOSE'];
$phpMail=$ini['PHP_MAIL'];
//
//setup the includes etc
//
require_once JETHRO_ROOT.'/include/system_controller.class.php';
$GLOBALS['system'] = System_Controller::get();
require_once JETHRO_ROOT.'/db_objects/roster_view.class.php';
//
//get the roster information using the roster view id
//
$view = $GLOBALS['system']->getDBObject('roster_view', $roster_id);
				$start_date = date("Ymd");
				$end_date = date('Ymd', strtotime("+6 day"));


//
//build the roster table to be included in the messages/s
//
//get the roster name
ob_start();
$view->printFieldValue('name');
$roster_name = ob_get_contents();
ob_end_clean();
//we will include this weeks roster either as a list or as a table
//start with a list
//get the roster as csv
ob_start();
$view->printCSV($start_date, $end_date);
$roster_csv = ob_get_contents();
ob_end_clean();

//break the csv into lines. trick is there are new lines in some fields so break when new-line and quotes /n" but we want to retain the " so use lookahead(?=  ).
$roster_lines = preg_split('/(?=\n")/',$roster_csv);
$roster_array = array();
foreach ($roster_lines as $line) {
	$roster_array[] = str_getcsv($line);
}

$roster_date = '';
if (count($roster_array) > 2) {
	$roster_date_index = array_search('Date', $roster_array[1]);
	$roster_date = " (" . $roster_array[2][$roster_date_index] . ")";
}

$assignees=$view->getAssignees($start_date, $end_date);

if ($sendsms) { // make the sms message!
	$sms_notification = "No SMS Notification was sent for " . $roster_name . ". There were no people assigned.\n";

	$coordinator=new Person($roster_coordinator_id);
	$sql = 'SELECT person.* FROM person WHERE person.id='.$roster_coordinator_id;
	$coordinator = $GLOBALS['db']->queryAll($sql);

	if (count($assignees) > 0) {

		$smsroster = '';
		if ($include_roster_content) {
			//now turn the array into a list of roster assignments
			$smsroster=$roster_array[0][1]."\n";
			$fields=count($roster_array[1]);
			$x=0;
			while ($x < $fields) {
				$smsroster.= "\n" . $roster_array[1][$x].": ";
				if ($roster_array[2][$x]=="") {
					$smsroster.= "-";
				} else {
					$smsroster.= preg_replace("/\\n/m", ", ",$roster_array[2][$x]);
				}
			  $x++;
			}
			$smsroster .= "\n";
			if ((int)$debug==1){
			 $assignees=Array();
			}
		}

		$assignees = array_merge($assignees, $coordinator);

		$sms_message = $pre_message . $smsroster . "\n" .  $post_message;
		$sms_message = str_replace('<br>', "\n", $sms_message);
		$sms_message = str_replace('<BR>', "\n", $sms_message);
		$sms_message = strip_tags($sms_message);
		if (strlen($sms_message) > SMS_MAX_LENGTH) {
			$assignees = $coordinator;
			$sms_message = 'Roster email is too long for SMS. Increase SMS size limit.';
		}
		// now, actually send the messages!
		require_once JETHRO_ROOT.'/include/sms_sender.class.php';
		$notification_sms = '';
		if (!empty($assignees))  {
			$sendResponse = SMS_Sender::sendMessage($sms_message, $assignees, FALSE);
			$successes = $failures = $rawresponse = Array();
			$success = $sendResponse['success'];
			$successes = array_values($sendResponse['successes']);
			$failures = array_values($sendResponse['failures']);
			$rawresponse = $sendResponse['rawresponse'];
			$error = $sendResponse['error'];
			if (!$success) {
				$sms_notification = "Unable to send SMS\n\n$error\n";
			} else {
				if ((count($successes) <= 0) && (count($failures) <= 0)) {
					$sms_notification = "SMS for $roster_name sent, but sending cannot be confirmed.\n";
				}
				if (count($successes) > 0 ) {
					$sms_notification = "Sent roster successfully to:\n";
					for ($i=0; $i < count($successes); $i++) {
						$sms_notification .= $successes[$i]['first_name'] . ' ' . $successes[$i]['last_name'];
						if ($i < (count($successes) - 1)) { $sms_notification .= ', '; } else { $sms_notification .= ".\n\n";}
					}
				}
				if (count($failures) > 0 ) {
					$sms_notification .= "Failed to send roster to:\n";
					for ($i=0; $i < count($failures); $i++) {
						$sms_notification .= $failures[$i]['first_name'] . ' ' . $failures[$i]['last_name'];
						if ($i < (count($failures) - 1)) { $sms_notification .= ', '; } else { $sms_notification .= ".\n\n";}
					}
				}
			}
		}
	}
	// Notify about sending the notifications!
	$sendResponse = SMS_Sender::sendMessage($sms_notification, $coordinator, FALSE);
	if (!empty($verbose)) {
		echo "$sms_notification\n";
		if (!$sendResponse['success']) {
			echo "Unable to send Notification SMS:\n\n" . $sendResponse['error'] . "\n";
		}
		echo "\nFull Server Response:\n\n" . $sendResponse['rawresponse'] . "\n";
	}
}

if ($sendemail) {
	$emails=array();
	$no_emails=array();
	$eol = PHP_EOL;
	$uid = md5(uniqid(time()));
	$email_notification = "Sent roster reminder for $roster_name $roster_date";
	$email_notification_subject="Roster notifications for $roster_name";
	$no_email_address_message = "";

	if (count($assignees) > 0) {
		// build the roster list to be included in the stream_context_set_params
		if ((int)$list_not_table==1) {
			//now turn the array into a list of roster assignments
			$roster=$roster_array[0][1].'<br>';
			$fields=count($roster_array[1]);
			$x=0;
			while ($x < $fields) {
				$roster.= '<b>'.$roster_array[1][$x].'</b><br>';
				if ($roster_array[2][$x]=="") {
					$roster.= '<i>-</i><br><br>';
				} else {
					$roster.= preg_replace("/\\n/m", "<br />",$roster_array[2][$x]).'<br><br>';
				}
		  	$x++;
	 		}
		} else {
			// otherwise include as a table
			ob_start();
			$view->printView($start_date, $end_date, FALSE, TRUE);
			$roster = ob_get_contents();
			ob_end_clean();
		}
		//put the other details into the email
		$longstring = "<html><head><style>html body { font-family: sans-serif;} </style></head><body>";
		$longstring.=$pre_message."<br>";
		$longstring.="<b>Roster: ";
		$longstring.=$roster_name."</b>";
		if ($include_roster_content) $longstring.='<br>'.$roster;
		$longstring.="<br>".$post_message;
		$longstring.="<br><br><i>This email was generated by the ".SYSTEM_NAME." Jethro system.</i><br>";
		$longstring.="</body></html>";
		//
		//build the email address array / no email address array
		//
		foreach($assignees as $row => $innerArray) {
			if (!empty($innerArray['email'])) {
		  	$emails[]=$innerArray['email'];
		  } else {
				$no_emails[]=$innerArray['first_name']." ".$innerArray['last_name'];
		  }
	 	}
		//strip any duplicates
		$emails = array_unique($emails);
		$emails_string = (implode(',',$emails));
		// if DEBUG then only send an email to the specified roster coordinator
		if ((int)$debug==1){
		 $emails_string=$roster_coordinator;
		}
		$no_emails = array_unique($no_emails);
		$no_emails_string = (implode('<br>', $no_emails));
		//
		//send the emails
		//
		$no_email_address_message="Jethro has just sent a roster reminder email regarding this roster - <b>".$roster_name."</b><br><br> However, the following people do not have an email address recorded. As such, they have not been sent the reminder:<br><span style='padding-left: 1.5em;'>$no_emails_string</span>";

		//if DEBUG then echo the email content
		if ((int)$debug==1){
			echo "Sending the following message:\n\n";
			echo $longstring."\n\n";
		}
		//
		//send using built in email class
		//
		if ((int)$phpMail==0) {
			require_once JETHRO_ROOT.'/include/emailer.class.php';
			$message = Emailer::newMessage()
			  ->setSubject($email_subject . "$roster_date")
			  ->setFrom(array($email_from => $email_from_name))
			  ->setBody("Roster reminder email")
			  ->addPart($longstring, 'text/html')
			  ->setTo(explode(',',$roster_coordinator))
			  ->setBcc(explode(',',$emails_string));
			$res = Emailer::send($message);
		  if (!$res) {
				echo "Failed to send roster reminder (".$roster_name.")\n";
				exit(1);
			} else {
				if (!empty($verbose)) {
					echo "Sent roster reminder (".$roster_name.")\n";
				}
			}
		} else { // using php mail()
		  $email_to=$roster_coordinator;
		  $header = "From: ".$email_from.$eol;
		  $header .= "MIME-Version: 1.0".$eol;
		  $header .= "Bcc: ".$emails_string.$eol;
		  $header .= "Content-Type: multipart/mixed; boundary=\"".$uid."\"";
		  $message = "--".$uid.$eol;
		  $message .= "Content-type:text/html; charset=iso-8859-1".$eol;
		  $message .= "Content-Transfer-Encoding: 8bit".$eol.$eol;
		  $message .= $longstring.$eol;
		  $message .= "--".$uid."--";
		   if (mail($email_to, $email_subject . "$roster_date", "$message", $header)) {
		   	echo "Mail send roster reminder - ".$roster_name." sent OK <br>";
		   } else {
		   	echo "Mail send roster reminder - ".$roster_name." send ERROR!";
		   }
		}
	} else {
        $email_notification = "No email notification was sent for " . $roster_name . ". There were no people assigned.\n";
    }
	if (!empty($no_emails)) {
		$email_notification = $no_email_address_message;
	}
	// send an email to the roster coordinator if anyone does not have an email address
	if ((int)$phpMail==0) {
		$message2 = Emailer::newMessage()
			->setSubject($email_notification_subject . "$roster_date")
			->setFrom(array($email_from => $email_from_name))
			->setBody("Roster notification for $roster_name")
			->addPart($email_notification, 'text/html')
			->setTo(explode(',',$roster_coordinator));
		$res = Emailer::send($message2);
		if (!$res) {
			echo "Failed to send roster ($roster_name) reminder notification to coordinator\n";
			exit(1);
		} else {
			if (!empty($verbose)) {
				echo "Sent roster ($roster_name) reminder notification to coordinator.\n";
			}
		}
	} else {
		$email_to=$roster_coordinator;
		$header = "From: ".$email_from.$eol;
		$header .= "MIME-Version: 1.0".$eol;
		$header .= "Content-Type: multipart/mixed; boundary=\"".$uid."\"";
		$message = "--".$uid.$eol;
		$message .= "Content-type:text/html; charset=iso-8859-1".$eol;
		$message .= "Content-Transfer-Encoding: 8bit".$eol.$eol;
		$message .= $email_notification.$eol;
		$message .= "--".$uid."--";
		if (mail($email_to,$email_notification_subject . "$roster_date","$message",$header)) {
			if (!empty($verbose)) {
				echo "Sent roster ($roster_name) reminder notification to coordinator.\n";
			}
		} else {
			echo "Failed to send roster ($roster_name) reminder notification to coordinator\n";
			exit;
		}
	}
}

?>
