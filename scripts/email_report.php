<?php
/*****************************************************************
This script will send the results of a report in an email and also attached as a .csv file. 
usage:  php /path/to/this-script.php /path/to/variables-in-a-file.ini
Possible application could be:
- email a list of people who have missed church for three sundays to the minister or the pastoral care group
- email a list of newcomers to welcome coordinator or the welcomers group
- email upcoming birthdays to the Sunday School coordinator or sunday school teachers group
- generate a regular report (e.g. monthly) of childsafe training details for childsafe administrator
The recipient/s of the email need not be given user access to Jethro. You can send to a specific Jethro group, or to one or more emails entered in the ini file
You can adjust the format of the report, or the membership of the recipient group in Jethro without needing to change this script or the cron job that calls it. 
With a bit of work this script could be modified to send multiple different reports. 
TODO - there may be issues with formatting if a report is grouped into multiple tables?
******************************************************************/
//
//read the ini file
//
if (empty($_SERVER['argv'][1]) || !is_readable($_SERVER['argv'][1])) {
	echo "You must specify an ini file as the first argument \n";
	echo "Eg:  php email_report.php email_report_sample.ini \n";
	exit;
}
ini_set('display_errors', 1);
$ini = parse_ini_file($_SERVER['argv'][1]);
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
if (!defined('DSN')) define('DSN', constant('PRIVATE_DSN'));
require_once JETHRO_ROOT.'/include/init.php';
require_once JETHRO_ROOT.'/include/user_system.class.php';
$GLOBALS['user_system'] = new User_System();
$GLOBALS['user_system']->setPublic();
require_once JETHRO_ROOT.'/include/system_controller.class.php';
$GLOBALS['system'] = System_Controller::get();
//
// Check that we have a report
//
if (!(int)$ini['REPORT_ID']) {
	echo "No Report ID found - correct your config within ".$_SERVER['argv'][1];
	exit(1);
}
//
// Use the report to generate the subject and content of the email using the output buffer, then clear the buffer
//
$report = $GLOBALS['system']->getDBObject('person_query', (int)$ini['REPORT_ID']);
$reportname = $report->getValue('name');
if (empty($reportname)) $reportname = 'Jethro-Report-'.date('Y-m-d_H:i');
ob_start();
$report->printResults('csv');
$csv_string = ob_get_contents();
ob_end_clean();
//
// turn csv into html table for email content
//
$csv_array = preg_split("/\\r\\n|\\r|\\n/", $csv_string);
$rows=(count($csv_array)-1);
$x=0; 
$email_html="<html><head></head><body>This is the result of a report generatated from the ".SYSTEM_NAME." Jethro system. <br><br>".$ini['MESSAGE']."<br><h2>Report name: ".$reportname."</h2>";
  $email_html.="<table border='1' cellpadding='5' cellspacing='1'>";
// TODO: this doesn't handle values containing commas or newlines properly
while($x < $rows) {
    $line = explode(',',$csv_array[$x]);
	$email_html.="<tr>";
     	if ($x==0) {
     	foreach ($line as $cell) {$email_html.="<th>".(str_replace('"', "", $cell))."</th>";}
	} else {
     	foreach ($line as $cell) {$email_html.="<td>".(str_replace('"', "", $cell))."</td>";}
	}
 	$email_html.="</tr>";	
  $x++;
} 
$email_html.="</table><br><br><i>If there is no table above then the report returned no results.</i><br></body></html>";
$email_subject="Jethro Report: ".$reportname;
$file_name=$reportname.".csv";
// 
// compile the list of recipients for the email
//
if ((int)$ini['GROUP_ID']!=0) {
  $group = $GLOBALS['system']->getDBObject('person_group', (int)$ini['GROUP_ID']);
  $members = $group->getMembers();
  $recipients_array = Array();
  foreach ($members as $m) {
      $recipients[] = $m['email'];
  }
  $recipients_string = (implode(',', $recipients));
}else{
$recipients_string = $ini['EMAIL_TO'];	
}
//
// send the email with .cvs attachment
//
if ((int)$ini['PHP_MAIL']==0) {
require_once JETHRO_ROOT.'/include/emailer.class.php'; 
	$message = Emailer::newMessage()
	  ->setSubject($email_subject)
	  ->setFrom(array($ini['EMAIL_FROM'] => $ini['EMAIL_FROM_NAME']))
	  ->setBody("See CSV data attached")
	  ->addPart($email_html, 'text/html')
	  ->setTo(explode(',', $recipients_string));
	if ((int)$ini['CSV']==1) {
	  $attachment = new Swift_Attachment($csv_string, $file_name, 'text/csv');
	  $message->attach($attachment);  
	}	
	$res = Emailer::send($message);
	if (!$res) {
		echo "Failed to send report (".$reportname.") to ".$ini['EMAIL_TO']."\n";
		exit(1);
	} else {
		if (!empty($ini['VERBOSE'])) {
			echo "Sent report (".$reportname.") to ".$ini['EMAIL_TO']."\n";
		}
		exit(0);
	}
}
// send the email with attachment uaing php mail()
if ((int)$ini['PHP_MAIL']==1) {
  $eol = PHP_EOL;
  $content = chunk_split(base64_encode($csv_string));
  $uid = md5(uniqid(time()));
  $email_from=$ini['EMAIL_FROM'];
  $email_to=$recipients_string;
  $header = "From: ".$email_from.$eol;
  $header .= "MIME-Version: 1.0".$eol;
  $header .= "Content-Type: multipart/mixed; boundary=\"".$uid."\"";
  $message = "--".$uid.$eol;
  $message .= "Content-type:text/html; charset=iso-8859-1".$eol;
  $message .= "Content-Transfer-Encoding: 8bit".$eol.$eol;
  $message .= $email_html.$eol;
  if ((int)$ini['CSV']==1) {
	$message .= "--".$uid.$eol;
	$message .= "Content-Type: text/csv; name=\"".$file_name."\"".$eol;
	$message .= "Content-Transfer-Encoding: base64".$eol;
	$message .= "Content-Disposition: attachment; filename=\"".$file_name."\"".$eol.$eol;
	$message .= $content.$eol;
	}
  $message .= "--".$uid."--";
   if (mail($email_to, $email_subject, "$message", $header)) {
   echo $reportname." Mail send ... OK";
   } else {
   echo $reportname." Mail send ... ERROR!";
   }
  }
