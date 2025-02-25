<?php
/**
 * This script can be used to send emails and SMS messages to people who have a custom date value
 * X days from now, for example to remind them to renew a certification.
 * 
 * It can also send a summary of the reminders sent, to people in the same congregation
 * with a certain person status.
 *
 * It is called with an ini file as first argument
 * eg: php date_reminder.php my-config-file.ini
 *
 * @see date_reminder_sample.ini for config file format.
 */

 if ((php_sapi_name() !== 'cli') && !defined('STDIN')) {
	echo "This script must be run from the command line";
	exit;
 }

if (empty($_SERVER['argv'][1]) || !is_readable($_SERVER['argv'][1])) {
	echo "You must specify an ini file as the first argument \n";
	echo "Eg:  php date_reminder.php my-config-file.ini \n";
	exit;
}
ini_set('display_errors', 1);
$ini = parse_ini_file($_SERVER['argv'][1]);
define('JETHRO_ROOT', dirname(dirname(__FILE__)));
set_include_path(get_include_path().PATH_SEPARATOR.JETHRO_ROOT);
if (!is_readable(JETHRO_ROOT.'/conf.php')) {
	trigger_error('Jethro configuration file not found.  You need to copy conf.php.sample to conf.php and edit it before Jethro can run', E_USER_ERROR);
	exit();
}
require_once JETHRO_ROOT.'/conf.php';
define('DB_MODE', 'private');
require_once JETHRO_ROOT.'/include/init.php';
require_once JETHRO_ROOT.'/include/user_system.class.php';
require_once JETHRO_ROOT.'/include/system_controller.class.php';
$GLOBALS['user_system'] = new User_System();
$GLOBALS['user_system']->setCLIScript();
$GLOBALS['system'] = System_Controller::get();
//error_reporting(E_ALL);

$SQL = 'SELECT p.*, cfv.value_date AS expirydate ';
if (!empty($ini['SUMMARY_RECIPIENT_STATUS'])) {
	$SQL .= ', GROUP_CONCAT(supervisor.email SEPARATOR ";") as supervisor, GROUP_CONCAT(CONCAT(supervisor.first_name, " ", supervisor.last_name) SEPARATOR ", ") as supervisor_name ';
} else if (!empty($ini['SUMMARY_RECIPIENT_EMAIL'])) {
	$SQL .= ', '.$GLOBALS['db']->quote($ini['SUMMARY_RECIPIENT_EMAIL']).' as supervisor, '.$GLOBALS['db']->quote($ini['SUMMARY_RECIPIENT_EMAIL']).' as supervisor_name ';
}
$SQL .= '
		FROM _person p
		JOIN custom_field_value cfv ON cfv.personid = p.id AND cfv.fieldid = '.(int)$ini['CUSTOM_FIELD_ID'];
if (!empty($ini['SUMMARY_RECIPIENT_STATUS'])) {
	$map = array_flip(Person::getStatusOptions());
	if (!isset($map[$ini['SUMMARY_RECIPIENT_STATUS']])) {
		trigger_error($ini['SUMMARY_RECIPIENT_STATUS'].' is not a valid status in this system', E_USER_ERROR);
	}
	$SQL .= '
			LEFT JOIN _person supervisor ON (
				LENGTH(supervisor.email) > 0
				AND supervisor.congregationid = p.congregationid
				AND supervisor.status = '.$GLOBALS['db']->quote((string)$map[$ini['SUMMARY_RECIPIENT_STATUS']]).'
			)';
}
$SQL .= '
		WHERE cfv.value_date  = CURDATE() + INTERVAL '.(int)$ini['REMINDER_OFFSET'].' DAY
		AND p.status NOT IN (SELECT id FROM person_status WHERE is_archived)
		GROUP BY p.id';
$res = $GLOBALS['db']->queryAll($SQL);

if (empty($res) && !empty($ini['VERBOSE'])) {
	echo "No persons found with custom field ".$ini['CUSTOM_FIELD_ID'].' '.$ini['REMINDER_OFFSET']." days from now \n";
}

if (!empty($ini['SMS_MESSAGE'])) {
	
	if (!empty($ini['SMS_FROM'])) {
		define('OVERRIDE_USER_MOBILE', $ini['SMS_FROM']);
	}
	require_once JETHRO_ROOT.'/include/sms_sender.class.php';
	if (!ifdef('SMS_HTTP_URL')) {
		trigger_error("You have specified an SMS message in ".$_SERVER['argv'][1].' but this Jethro system does not have an SMS gateway configured. No SMS messages will be sent.');
		$ini['SMS_MESSAGE'] = '';
	}
}
require_once JETHRO_ROOT.'/include/emailer.class.php';

// Send individual reminders and collate summary info
$summaries = Array();
$supervisor_names = Array();
foreach ($res as $person) {
	if (empty($person['first_name'])) continue; // no matches = empty row
	$sentSomething = send_reminder($person);
	if (!empty($person['supervisor'])) {
		$summaryEntry = $person['first_name'].' '.$person['last_name'];
		$summaryEntry .= ' ('.format_date($person['expirydate']).') ';
		if (!$sentSomething) {
			$summaryEntry .= " (NO CONTACT DETAILS)";
		}
		$summaries[$person['supervisor']][$person['id']] = $summaryEntry;
		$supervisor_names[$person['supervisor']] = $person['supervisor_name'];
	}
	
}

// Send summaries
foreach ($summaries as $supervisors => $remindees) {
	$content = str_replace('%REMINDEE_NAMES%', implode("\n", $remindees), $ini['SUMMARY_BODY']);
	$content = str_replace('%SUPERVISOR_NAMES%', $supervisor_names[$supervisors], $content);
	$html = nl2br($content);

	$message = Emailer::newMessage()
	  ->setSubject($ini['SUMMARY_SUBJECT'])
	  ->setFrom(array($ini['FROM_ADDRESS'] => $ini['FROM_NAME']))
	  ->setBody($content)
	  ->addPart($html, 'text/html');
	if (!empty($ini['OVERRIDE_RECIPIENT'])) {
		$message->setTo($ini['OVERRIDE_RECIPIENT']);
	} else {
		$message->setTo(explode(';', $supervisors));
	}
	$res = Emailer::send($message);
	if (!$res) {
		echo "Failed to send summary email to $supervisors \n";
	} else if (!empty($ini['VERBOSE'])) {
		echo "Sent summary to ".$supervisors."\n";
	}
	
}


function send_reminder($person)
{
	global $ini;
	
	$sentSomething = FALSE;
	if (!empty($ini['EMAIL_BODY'])) {
		if (strlen($person['email'])) {
			$toEmail = $person['email'];
			if (!empty($ini['OVERRIDE_RECIPIENT'])) $toEmail = $ini['OVERRIDE_RECIPIENT'];

			$content = replace_keywords($ini['EMAIL_BODY'], $person);
			$html = nl2br($content);

			$message = Emailer::newMessage()
			  ->setSubject(replace_keywords($ini['SUBJECT'], $person))
			  ->setFrom(array($ini['FROM_ADDRESS'] => $ini['FROM_NAME']))
			  ->setTo(array($toEmail => $person['first_name'].' '.$person['last_name']))
			  ->setBody($content)
			  ->addPart($html, 'text/html');

			$res = Emailer::send($message);
			if (!$res) {
				echo "Failed to send email to $toEmail \n";
			} else if (!empty($ini['VERBOSE'])) {
				echo "Sent email reminder to ".$person['first_name'].' '.$person['last_name'].' '.$toEmail;
				echo "\n";
			}
			$sentSomething = TRUE;
		} else {
			if (!empty($ini['VERBOSE'])) echo $person['first_name'].' '.$person['last_name']." has no email address and will not be sent an email \n";
		}
	}
	if (!empty($ini['SMS_MESSAGE'])) {

		if (strlen($person['mobile_tel'])) {
			$toNumber = $person['mobile_tel'];
			if (!empty($ini['OVERRIDE_RECIPIENT_SMS'])) $toNumber = $person['mobile_tel'] = $ini['OVERRIDE_RECIPIENT_SMS'];
			$message = replace_keywords($ini['SMS_MESSAGE'], $person);
			$res = SMS_Sender::sendMessage($message, Array($person), FALSE);
			if (!$res['executed'] || (count($res['successes']) != 1)) {
				echo "Failed to send SMS to ".$toNumber."\n";
			} else {
				$sentSomething = TRUE;
				if (!empty($ini['VERBOSE'])) {
					echo "Sent SMS reminder to ".$person['first_name'].' '.$person['last_name'].' '.$toNumber."\n";
				}
			}
		} else {
			if (!empty($ini['VERBOSE'])) echo $person['first_name'].' '.$person['last_name']." has no mobile number and will not be sent an SMS \n";
		}
	}
	
	if (!empty($ini['VERBOSE']) && !$sentSomething) {
		echo $person['first_name'].' '.$person['last_name']." was not sent any notification \n";
	}
	
	return $sentSomething;
}

function replace_keywords($content, $person)
{
	static $dummy = NULL;
	if (!$dummy) $dummy = new Person();
	$dummy->populate($person['id'], $person);
	foreach($person as $k => $v) {
		$keyword = '%'.strtoupper($k).'%';
		if (FALSE !== strpos($content, $keyword)) {
			if ($k == 'expirydate') {
				$replacement = format_date($v);
			} else {
				$replacement = $dummy->getFormattedValue($k);
			}
			$content = str_replace($keyword, $replacement, $content);
		}
	}
	return $content;
}
