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

if ((php_sapi_name() !== 'cli') && !defined('STDIN')) {
	echo "This script must be run from the command line";
	exit;
}

//read the ini file
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
define('DB_MODE', 'private');
require_once JETHRO_ROOT.'/include/init.php';
require_once JETHRO_ROOT.'/include/user_system.class.php';
$GLOBALS['user_system'] = new User_System();
$GLOBALS['user_system']->setCLIScript();
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
$email_html="<html><head>
<style>html body { font-family: sans-serif;} table {
	color:#333333;
	border-width: 1px;
	border-color: #666666;
	border-collapse: collapse;
}
table th {
	border-width: 1px;
	padding: 8px;
	border-style: solid;
	border-color: #666666;
	background-color: #dedede;
}
table.gridtable td {
	border-width: 1px;
	padding: 8px;
	border-style: solid;
	border-color: #666666;
	background-color: #ffffff;
} </style></head><body>This is the result of a report generated from the ".SYSTEM_NAME." Jethro system. <br><br>".$ini['MESSAGE']."<br><h2>Report name: ".$reportname."</h2>";
//first work-over $csv_string to deal with the issue of new-lines within a field by replacing those new lines with <br>
$csv_string  = preg_replace('#\\n(?=[^"]*"[^"]*(?:"[^"]*"[^"]*)*$)#' , '<br>', $csv_string);
//now separate out the table rows
$table_rows = explode(PHP_EOL, $csv_string);
//covert each row into an array
$table_array = array();
foreach ($table_rows as $line) {
    $table_array[] = str_getcsv($line);
}
    $rows = (count($table_array)-1);
    $columns = count($table_array[0]);
    $y = 0;
    $table_header = '';
    $table_body = '';
    $table_html = '';
// table headings - as a string AND test if the report results are grouped
	$table_header .= '<tr>';
	while ($y < $columns) {
		if ($table_array[0][$y] == 'GROUPING') {$grouping_column = $y;}
		else {$table_header .= '<th>'.$table_array[0][$y].'</th>';}
		$y++;}
		$table_header .= '</tr>';
// table body (if the report results are to be grouped)
	if (isset($grouping_column)) {
	$x=1;
	$y = 0;
	$table_body = '';
	$group_name = $table_array[$x][$grouping_column];
	while ($x < $rows) {
		if ($group_name == $table_array[$x][$grouping_column]){
			$table_body .= '<tr>';
			$y = 0;
			while ($y < $columns) {
				if ($y != $grouping_column){
					if ($table_array[$x][$y] == '') {
						$table_body .= '<td> - </td>';
					} else {
						$table_body .= '<td>'.$table_array[$x][$y].'</td>';
					}
				}
				$y++;
			}
			$table_body .= '</tr>';
			$group_name = $table_array[$x][$grouping_column];
		} else {
			$table_html .= '<h3>'.$group_name.'</h3><table border=1 cellpadding=4 cellspacing=3 >'.$table_header.$table_body.'</table>';
			$table_body = '<tr>';
			$y = 0;
			while ($y < $columns) {
				if ($y != $grouping_column){
					if ($table_array[$x][$y] == '') {
						$table_body .= '<td> - </td>';
					} else {
						$table_body .= '<td>'.$table_array[$x][$y].'</td>';
					}
				}
				$y++;
			}
			$table_body .= '</tr>';
			$group_name = $table_array[$x][$grouping_column];
			}
		$x++;
		}
			$table_html .= '<h3>'.$group_name.'</h3><table border=1 cellpadding=4 cellspacing=3 >'.$table_header.$table_body.'</table>';
	} else {
// table body (if the report results are ungrouped)
	$x=1;
	$y=0;
	while ($x < $rows){
		$table_body .= '<tr>';
		while ($y < $columns) {
			if ($table_array[$x][$y] == '') {
				$table_body .= '<td> - </td>';
			} else {
				$table_body .= '<td>'.$table_array[$x][$y].'</td>';
			}
			$y++;
		}
		$table_body .= '</tr>';
		$y = 0;
		$x++;
 		}
	$table_html = '<table border=1 cellpadding=4 cellspacing=3 >'.$table_header.$table_body.'</table>';
	}
$email_html.=$table_html;
$email_html.="<br><br><i>If there is no table above then the report returned no results.</i><br></body></html>";
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
