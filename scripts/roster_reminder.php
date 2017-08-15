<?php
/*****************************************************************
This script will send roster reminder emails
set-up a cron-job to run the script ast follows

php /path/to/this/script/roster_reminder.php /path/to/ini/file/roster_reminder_sample.ini

Note: This script will only work with roster views that are made public
Where's the roster id number?  When you view a roster via the /jethro/public directory you'll see the roster id number in the url (eg. &roster_view=1)

Use .ini file to set the following
 - roster coordinator email / group
 - roster view number
 - pre_message to go with roster reminder
 - post_message to go with roster reminder
 - email from address
 - email from name
 - email subject
 - debug (only send to roster coordinator)
 - email method (email_class or php mail())

TWO EMAILS WILL BE SENT
 - one email to the assignees (to: roster coordinator bcc: assignees - content = roster table, roster message, how to tell the roster coordinator if you can't do the task allocated to you etc.
 - second email to the roster coordinator including a note listing those assignees w/o an email (i.e. who will not have received the email update)

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
$ini = parse_ini_file($_SERVER['argv'][1]);
//this is a bit verbose - to aid with fault-finding/testing
$roster_coordinator=$ini['ROSTER_COORDINATOR'];
$roster_id=$ini['ROSTER_ID'];
$pre_message=$ini['PRE_MESSAGE'];
$post_message=$ini['POST_MESSAGE'];
$email_from=$ini['EMAIL_FROM'];
$email_from_name=$ini['EMAIL_FROM_NAME'];
$email_subject=$ini['EMAIL_SUBJECT'];
$list_not_table=$ini['LIST_NOT_TABLE'];
//
$debug=$ini['DEBUG'];
$verbose=$ini['VERBOSE'];
$phpMail=$ini['PHP_MAIL'];
//
//setup the includes etc
//
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
//build the roster table to be included in the email/s
//
//get the roster name
ob_start();
$view->printFieldValue('name');
$roster_name = ob_get_contents();
ob_end_clean();
//we will include this weeks roster either as a list or as a table
//start with a list
if ((int)$list_not_table==1) {
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
//now turn the array into a list of roster assignments 
$roster=$roster_array[0][1].'<br>';
$fields=count($roster_array[1]);
$x=0;
While ($x < $fields) {
	$roster.= '<b>'.$roster_array[1][$x].'</b><br>';
	if ($roster_array[2][$x]=="") {
	$roster.= '<i>nil</i><br><br>';
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
$longstring.='<br>'.$roster;
$longstring.="<br>".$post_message;
$longstring.="<br><br><i>This email was generated by the ".SYSTEM_NAME." Jethro system.</i><br>"; 
$longstring.="</body></html>";
//
//build the email address array / no email address array
//
$emails=array();
$no_emails=array();
$assignees=$view->getAssignees($start_date, $end_date);
foreach($assignees as $row => $innerArray){
    if (!empty($innerArray['email'])){
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
$no_email_address_message="Jethro has just sent a roster reminder email regarding this roster - <b>".$roster_name." </b><br><br> But the following people do not have an email address recorded and so they have not been sent the reminder:<br>".$no_emails_string;
$email_not_sent_subject="warning re: ".$email_subject;
//if DEBUG then echo the email content
if ((int)$debug==1){
echo $longstring."<br>";
}
//
//send using built in email class
//
if ((int)$phpMail==0) {
require_once JETHRO_ROOT.'/include/emailer.class.php'; 
	$message = Emailer::newMessage()
	  ->setSubject($email_subject)
	  ->setFrom(array($email_from => $email_from_name))
	  ->setBody("roster reminder email")
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
// send an email to the roster coordinator if anyone does not have an email address   
if (!empty($no_emails)){
	$message2 = Emailer::newMessage()
	  ->setSubject($email_not_sent_subject)
	  ->setFrom(array($email_from => $email_from_name))
	  ->setBody("warning re roster reminder email")
	  ->addPart($no_email_address_message, 'text/html')
	  ->setTo(explode(',',$roster_coordinator));
	$res = Emailer::send($message2);
	if (!$res) {
		echo "Failed to send roster reminder warning to coordinator (".$roster_name.")\n";
		exit(1);
	} else {
		if (!empty($verbose)) {
			echo "Sent roster reminder warning to coordinator (".$roster_name.")\n";
		}
	}
	}
}
//using php mail()
if ((int)$phpMail==1) {
  $eol = PHP_EOL;
  $uid = md5(uniqid(time()));
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
   if (mail($email_to, $email_subject, "$message", $header)) {
   echo "Mail send roster reminder - ".$roster_name." sent OK <br>";
   } else {
   echo "Mail send roster reminder - ".$roster_name." send ERROR!";
   }
// send an email to the roster coordinator if anyone does not have an email address   
if (!empty($no_emails)){
	$email_to=$roster_coordinator;
   	$header = "From: ".$email_from.$eol;
  	$header .= "MIME-Version: 1.0".$eol;
  	$header .= "Content-Type: multipart/mixed; boundary=\"".$uid."\"";
  	$message = "--".$uid.$eol;
  	$message .= "Content-type:text/html; charset=iso-8859-1".$eol;
  	$message .= "Content-Transfer-Encoding: 8bit".$eol.$eol;
  	$message .= $no_email_address_message.$eol;
  	$message .= "--".$uid."--";
    if (mail($email_to,$email_not_sent_subject,"$message",$header)){
    echo "email sent to coordinator";
    } else {
    echo "email failed to send to coordinator";
    }
  }
  }
?>		
