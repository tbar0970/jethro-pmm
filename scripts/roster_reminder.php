<?php
/*****************************************************************
This script will send roster reminder emails, SMSes or both, to people rostered on a configured roster in the upcoming 6 days. Typically this matches the upcoming Sunday, but if another day's allocations are found (e.g. Easter Friday), people are notified once about both.

Set up a cron-job to run the script as follows:

php /path/to/this/script/roster_reminder.php /path/to/ini/file/roster_reminder_sample.ini

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

if ((php_sapi_name() !== 'cli') && !defined('STDIN')) {
	echo "This script must be run from the command line";
	exit;
}

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
	throw new \RuntimeException('Jethro configuration file not found.  You need to copy conf.php.sample to conf.php and edit it before Jethro can run');
	exit(1);
}

require_once JETHRO_ROOT.'/conf.php';
define('DB_MODE', 'private');
//if (!defined('DSN')) define('DSN', constant('PRIVATE_DSN'));
require_once JETHRO_ROOT.'/include/init.php';
require_once JETHRO_ROOT.'/include/user_system.class.php';
$GLOBALS['user_system'] = new User_System();
$GLOBALS['user_system']->setCLIScript();

$ini = parse_ini_file($_SERVER['argv'][1]);

function getvar($name, $default = null) {
	global $ini;	// Access the $ini array from the global scope
	if (!isset($ini[$name])) {
		if ($default === null) {
			throw new \RuntimeException("$name is required");
		} else {
			return $default;
		}
	}
	return $ini[$name];
}

//this is a bit verbose - to aid with fault-finding/testing
$messagetype=getvar('MESSAGE_TYPE');
$sendemail=($messagetype==='email') || ($messagetype==='both');
$sendsms=($messagetype==='sms') || ($messagetype==='both');
if ($sendemail) {
	$roster_coordinator=getvar('ROSTER_COORDINATOR');
	$list_not_table=getvar('LIST_NOT_TABLE');
	$email_from_name=getvar('EMAIL_FROM_NAME');
	$email_from=getvar('EMAIL_FROM');
	$email_subject=getvar('EMAIL_SUBJECT');
	$phpMail=getvar('PHP_MAIL', 0);
}
if ($sendsms) {
	$roster_coordinator_id = getvar('ROSTER_COORDINATOR_ID', '');  // optional: '' means no summary SMS
	$smsfrom = getvar('SMS_FROM');
}
$include_roster_content=getvar('INCLUDE_ROSTER_CONTENT', 1);
$roster_id=getvar('ROSTER_ID');
$pre_message=getvar('PRE_MESSAGE');
$post_message=getvar('POST_MESSAGE', '');
$debug=getvar('DEBUG', 0);
$verbose=getvar('VERBOSE', 0);

//setup the includes etc
//
require_once JETHRO_ROOT.'/include/system_controller.class.php';
$GLOBALS['system'] = System_Controller::get();
require_once JETHRO_ROOT.'/db_objects/roster_view.class.php';
//
//get the roster information using the roster view id
//
$view = $GLOBALS['system']->getDBObject('roster_view', $roster_id);
$start_date_offset = (int)getvar('START_DATE_OFFSET', 0);
$start_date = date('Y-m-d', strtotime("+$start_date_offset day"));
$end_date = date('Y-m-d', strtotime("+" . ($start_date_offset + 6) . " day"));


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
	$roster_array[] = str_getcsv($line, ",", '"', "");
}
$roster_date = '';
$rds = Array();
if (count($roster_array) > 2) {
	$roster_date_index = array_search('Date', $roster_array[1]);
	if ($roster_date_index !== FALSE) {
		for ($i=2; $i < count($roster_array); $i++) {
			$rds[] = $roster_array[$i][$roster_date_index];
		}
	}
	if ($rds) $roster_date = ' ('.implode(', ', $rds).')';
}

// Template substitution for PRE_MESSAGE / POST_MESSAGE / EMAIL_SUBJECT.  Three tokens are available:
//   {roster_date}         - e.g. "Sunday, 29 April" or "Friday, 3 April or Sunday, 5 April"
//   {roster_date_short}   - e.g. "29 Apr" or "3 Apr/5 Apr" (no year or day name; keeps SMS messages short)
//   {roster_day_of_week}  - e.g. "Sunday" or "Friday or Sunday"
// See roster_reminder_sample.ini for examples.
$roster_date_full  = implode(' or ', array_map(fn($d) => date('l, j F', strtotime($d)), $rds));
$roster_date_short = implode('/', array_map(fn($d) => date('j M',    strtotime($d)), $rds));
$day_of_week       = implode(' or ', array_map(fn($d) => date('l',      strtotime($d)), $rds));
$tokens       = ['{roster_date}', '{roster_date_short}', '{roster_day_of_week}'];
$replacements = [$roster_date_full, $roster_date_short, $day_of_week];
$pre_message  = str_replace($tokens, $replacements, $pre_message);
$post_message = str_replace($tokens, $replacements, $post_message);
if ($sendemail) $email_subject = str_replace($tokens, $replacements, $email_subject);

$smsReminderFailed = false;  // true if the SMS reminder reached nobody; makes the script exit 1 at the end
$assignees=$view->getAssignees($start_date, $end_date);

// Work out the sender before the SMS block. A sender problem disables only the
// SMS part: the email reminders below must still go out.
if ($sendsms) {
	require_once JETHRO_ROOT.'/include/jethro_sms.php';
	// SMS_FROM may be a phone number or an alphanumeric Sender ID (e.g. "StBarnabas").
	$smsSenderNumber = \Sms\senderFromString((string) $smsfrom);
	$smsSender = $smsSenderNumber ?? Jethro\Sms\getSenderFromRequest();
	if ($smsSender === null) {
		fwrite(STDERR, "No sender configured. Set SMS_FROM in the ini file, or set SMS_SENDER_OPTIONS/SMS_SENDER in conf.php. No SMS reminders will be sent.\n");
		$smsReminderFailed = true;
		$sendsms = false;
	}
}

if ($sendsms) { // make the sms message!
	$sms_notification = "No SMS Notification was sent for " . $roster_name . ". There were no people assigned.\n";

	$coordinator = [];  // keyed by person ID, as getRecipientsFromPersonRecords() expects
	if ($roster_coordinator_id !== '') {
		if (!ctype_digit($roster_coordinator_id)) {
			throw new \RuntimeException("ROSTER_COORDINATOR_ID must be an integer ID referencing a _person record");
		}
		$sql = 'SELECT person.* FROM person WHERE person.id='.(int)$roster_coordinator_id;
		$coordinator = array_column($GLOBALS['db']->queryAll($sql), null, 'id');
		if ($coordinator === []) {
			throw new \RuntimeException("No person found with ROSTER_COORDINATOR_ID {$roster_coordinator_id} (deleted, or not visible to this account)");
		}
	}

	$nameLookup = [];        // person ID => name, for formatSendSummary()
	foreach ($coordinator as $pid => $person) {
		$nameLookup[(int)$pid] = $person['first_name'] . ' ' . $person['last_name'];
	}

	if (count($assignees) > 0) {

		$smsroster = '';
		if ($include_roster_content) {
			//now turn the array into a list of roster assignments
			$smsroster=$roster_array[0][1]."\n";
			$fields=count($roster_array[1]);
			for ($i=2; $i < count($roster_array); $i++) {
				$x=0;
				while ($x < $fields) {
					$smsroster.= "\n" . $roster_array[1][$x].": ";
					if ($roster_array[$i][$x]=="") {
						$smsroster.= "-";
					} else {
						$smsroster.= preg_replace("/\\n/m", ", ",$roster_array[$i][$x]);
					}
				  $x++;
				}
				if ($i+1 < count($roster_array)) $smsroster .= "\n---\n";

			}
			$smsroster .= "\n";
		}

		$sms_message = $pre_message . $smsroster . "\n" .  $post_message;
		$sms_message = str_replace('<br>', "\n", $sms_message);
		$sms_message = str_replace('<BR>', "\n", $sms_message);
		$sms_message = strip_tags($sms_message);

		if ($debug) {
			bam($sms_message);
			bam("Debug mode - no messages sent");
			$recipientNames = array_map(function($a) {
				return $a['first_name'] . ' ' . $a['last_name'] . ' (' . $a['mobile_tel'] . ')';
			}, $assignees);
			bam("Recipients: " . implode(', ', $recipientNames));
			exit;
		}
		// SMS_MAX_LENGTH limits what staff type in the web UI; it does not apply
		// here, so the whole roster is sent however long it is.
		// Build a name lookup from person records (keyed by person ID)
		foreach ($assignees as $pid => $person) {
			$pid = (int)$pid;
			if ($pid) {
				$nameLookup[$pid] = ($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? '');
			}
		}

		// now, actually send the messages!
		$smsRecipients = Jethro\Sms\getRecipientsFromPersonRecords($assignees);
		if ($smsRecipients === []) {
			// Everyone rostered lacks a mobile number, so there is nobody to send to.
			$sms_notification = "No SMS Notification was sent for " . $roster_name . ". Nobody rostered has a mobile number.\n";
			fwrite(STDERR, $sms_notification);
			$smsReminderFailed = true;
		} else {
			$sendWrapper = Jethro\Sms\sendSms($sms_message, $smsRecipients, $smsSender);
			if ($sendWrapper->isFailure()) {
				$sms_notification = "Unable to send {$roster_name}\n\n" . $sendWrapper->getError() . "\n";
				fwrite(STDERR, $sms_notification);
				$smsReminderFailed = true;
			} else {
				$batch = $sendWrapper->getValue();
				$summary = \Sms\sendSummary($batch->deliveries, $smsRecipients);
				$sms_notification = Jethro\Sms\formatSendSummary($summary, $nameLookup, $roster_name);
				// Partial failures (e.g. a bad mobile number) are reported in the summary but aren't script errors.
				if ($summary instanceof \Sms\Failed) {
					fwrite(STDERR, $sms_notification);
					$smsReminderFailed = true;
				}
			}
		}
	}
	// Notify about sending the notifications!
	if (!empty($coordinator)) {
		$sendWrapper = Jethro\Sms\sendSms($sms_notification, Jethro\Sms\getRecipientsFromPersonRecords($coordinator), $smsSender);
		if ($sendWrapper->isFailure()) {
			fwrite(STDERR, $sendWrapper->getError() . "\n");
			$smsReminderFailed = true;
		} else {
			$batch = $sendWrapper->getValue();
			$summary = \Sms\sendSummary($batch->deliveries, Jethro\Sms\getRecipientsFromPersonRecords($coordinator));
			if ($summary instanceof \Sms\Failed) {
				fwrite(STDERR, Jethro\Sms\formatSendSummary($summary, $nameLookup, 'notification'));
				$smsReminderFailed = true;
			} elseif (!empty($verbose)) {
				echo "$sms_notification\n";
				echo Jethro\Sms\formatSendSummary($summary, $nameLookup, 'notification') . "\n";
			}
		}
	}
}

if ($sendemail) {
	$emails=array();
	$no_emails=array();
	$eol = PHP_EOL;
	$uid = md5(uniqid(time()));

/**
 * Compute From/Reply-To headers for the raw mail() path.
 * Mirrors Jethro_Email::setFrom() behaviour.
 *
 * @return array{string, string, string} [$fromAddress, $fromName, $headerPrefix]
 */
function roster_raw_mail_from(string $from_address, string $from_name): array
{
    if (ifdef('OVERRIDE_EMAIL_FROM')) {
        $actualFrom = OVERRIDE_EMAIL_FROM;
        $actualName = ifdef('OVERRIDE_EMAIL_FROM_NAME', '');
        $header = "From: \"".addslashes($actualName)."\" <".$actualFrom.">".PHP_EOL;
        $replyToHeader = $from_name ? "\"".addslashes($from_name)."\" <".$from_address.">" : $from_address;
        $header .= "Reply-To: ".$replyToHeader.PHP_EOL;
        return [$actualFrom, $actualName, $header];
    }
    return [$from_address, $from_name, "From: \"".addslashes($from_name)."\" <".$from_address.">".PHP_EOL];
}

	if (count($assignees) > 0) {
		// build the roster list to be included in the stream_context_set_params
		if ((int)$list_not_table==1) {
			//now turn the array into a list of roster assignments
			$roster=$roster_array[0][1].'<br>';
			$fields=count($roster_array[1]);
			for ($i=2; $i<count($roster_array); $i++) {
				$x=0;
				while ($x < $fields) {
					$roster.= '<b>'.$roster_array[1][$x].'</b><br>';
					if ($roster_array[$i][$x]=="") {
						$roster.= '<i>-</i><br><br>';
					} else {
						$roster.= preg_replace("/\\n/m", "<br />",$roster_array[$i][$x]).'<br><br>';
					}
					$x++;
				}
				if ($i+1 < count($roster_array)) $roster .= '<br />-----<br />';
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
		if ((int)$debug==1){
			// in debug mode send to co-ordinator only
			$emails = explode(',',$roster_coordinator);
		}
		$no_emails = array_unique($no_emails);
		//send the emails

		//if DEBUG then echo the email content and recipients, then exit without sending
		if ((int)$debug==1){
			echo "Sending the following message:\n\n";
			echo $longstring."\n\n";
			echo "Recipients:\n";
			foreach ($assignees as $a) {
				if (!empty($a['email'])) {
					echo "  " . $a['first_name'] . ' ' . $a['last_name'] . ' <' . $a['email'] . ">\n";
				} else {
					echo "  " . $a['first_name'] . ' ' . $a['last_name'] . " (no email)\n";
				}
			}
			echo "\nDebug mode - no messages sent\n";
			exit;
		}
		//
		//send using built in email class
		//
		if ((int)$phpMail==0) {
			require_once JETHRO_ROOT.'/include/emailer.class.php';
			$message = Emailer::newMessage()
			  ->setSubject($email_subject . "$roster_date")
			  ->setFrom(array($email_from => $email_from_name))
			  ->text("Roster reminder email")
			  ->html($longstring)
			  ->setTo(explode(',',$roster_coordinator))
			  ->setBcc($emails);
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
		  [$fromAddress, $fromName, $header] = roster_raw_mail_from($email_from, $email_from_name);
		  $header .= "MIME-Version: 1.0".$eol;
		  $header .= "Bcc: ".implode(',',$emails).$eol;
		  $header .= "Content-Type: multipart/mixed; boundary=\"".$uid."\"";
		  $message = "--".$uid.$eol;
		  $message .= "Content-type:text/html; charset=iso-8859-1".$eol;
		  $message .= "Content-Transfer-Encoding: 8bit".$eol.$eol;
		  $message .= $longstring.$eol;
		  $message .= "--".$uid."--";
		   if (mail($email_to, $email_subject . "$roster_date", "$message", $header, "-f ".$fromAddress)) {
		   	echo "Mail send roster reminder - ".$roster_name." sent OK <br>";
		   } else {
		   	echo "Mail send roster reminder - ".$roster_name." send ERROR!";
		   }
		}
    }


	// SEND SUMMARY MESSAGE TO CO-ORDINATOR
	$summary = '';
	if (empty($assignees)) {
		$summary = 'No roster reminders were sent for '.$roster_name.' because nobody was assigned to the roster';
	} else if (empty($emails)) {
		$summary = 'No roster reminders were sent for '.$roster_name.', because none of the assignees have email addresses';
	} else if (empty($no_emails)) {
		$summary = 'Roster reminders for '.$roster_name.' were sent to all '.count($emails).' assignees: <br /><br /> ';
		$summary .= implode(', ', $emails);
	} else {
		// Mixed results
		$summary = 'Roster reminders for '.$roster_name.' were sent to '.count($emails).' assignees, but '.count($no_emails).' assignees had no email address.<br /><br />';
		$summary .= 'Assignees with no email:';
		$summary .= '<ul><li>'.implode('</li><li>', $no_emails).'</li></ul>';
		$summary .= '<br /><br />Reminders were successfully sent to '.implode(', ', $emails);
	}
	$summary_notification_subject = "Roster notification summary for $roster_name $roster_date";
	if ((int)$phpMail==0) {
		$message2 = Emailer::newMessage()
			->setSubject($summary_notification_subject)
			->setFrom(array($email_from => $email_from_name))
			->text($summary_notification_subject)
			->html($summary)
			->setTo(explode(',',$roster_coordinator));
		$res = Emailer::send($message2);
		if (!$res) {
			echo "Failed to send roster ($roster_name) reminder summary to coordinator\n";
			exit(1);
		} else {
			if (!empty($verbose)) {
				echo "Sent roster ($roster_name) reminder notification to coordinator.\n";
			}
		}
	} else {
		$email_to=$roster_coordinator;
		[$fromAddress, $fromName, $header] = roster_raw_mail_from($email_from, $email_from_name);
		$header .= "MIME-Version: 1.0".$eol;
		$header .= "Content-Type: multipart/mixed; boundary=\"".$uid."\"".$eol;
		$message = "--".$uid.$eol;
		$message .= "Content-type:text/html; charset=iso-8859-1".$eol;
		$message .= "Content-Transfer-Encoding: 8bit".$eol.$eol;
		$message .= $summary.$eol;
		$message .= "--".$uid."--";
		if (mail($email_to,$summary_notification_subject . "$roster_date","$message",$header, "-f ".$fromAddress)) {
			if (!empty($verbose)) {
				echo "Sent roster ($roster_name) reminder notification to coordinator.\n";
			}
		} else {
			echo "Failed to send roster ($roster_name) reminder notification to coordinator\n";
			exit(1);
		}
	}
}

// Exit nonzero (after notifying the coordinator) if the SMS reminder failed to send to anyone.
if ($smsReminderFailed) {
	exit(1);
}
