<?php
/*****************************************************************
This script will send the results of a report in an email and also attached as a .csv file. 

usage:  php /path/to/this-script-and-path.php /pathe/to/variables-in-a-file.ini

Possible application could be:
- email a list of people who have missed church for three sundays to the minister
- email a list of newcomers to welcome coordinator
- email upcoming birthdays to the Sunday School coordinator
- generate a regular report (e.g. monthly) of childsafe training details for childsafe administrator

The recipient/s of the email need not be giving user access to Jethro. 

You can adjust the format of the report in Jethro without needing to change this script or the cron job that calls it. 

With a bit of work this script could be modified to send a report to a 'group' and/or an over-ride email or emails. 
Or you could work a bit harder and use one ini file to send multiple different reports. 

******************************************************************/
//
//read the ini file
//
if (empty($_SERVER['argv'][1]) || !is_readable($_SERVER['argv'][1])) {
	echo "You must specify an ini file as the first argument \n";
	echo "Eg:  php date_reminder.php my-config-file.ini \n";
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
$email_html="<html><head></head><body>This is the result of a report generatated from the ".SYSTEM_NAME." Jethro system. <br><br> <i>(If the rest of this email is blank (and the attached .csv file empty) then the report returned no results.</i><br><br><h2>Report name: ".$reportname."</h2>";
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
$email_html.="</table></body></html>";
$email_subject="Jethro Report: ".$reportname;
$file_name=$reportname.".csv";
//
// send the email with .cvs attachment
//
require_once JETHRO_ROOT.'/include/emailer.class.php';
	$message = Emailer::newMessage()
	  ->setSubject($email_subject)
	  ->setFrom(array($ini['EMAIL_FROM'] => $ini['EMAIL_FROM_NAME']))
	  ->setBody("See CSV data attached")
	  ->addPart($email_html, 'text/html')
	  ->setTo($ini['EMAIL_TO']);
	$attachment = new Swift_Attachment($csv_string, $file_name, 'text/csv');
	$message->attach($attachment);
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