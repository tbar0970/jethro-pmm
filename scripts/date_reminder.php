<?php
/**
 * This script can be used to send emails to people who have a custom date value
 * X days from now, for example to remind them to renew a certification.
 *
 * It is called with an ini file as first argument
 * eg: php date_reminder.php my-config-file.ini
 * 
 * @see date_reminder_sample.ini for config file format.
 */

if (empty($_SERVER['argv'][1]) || !is_readable($_SERVER['argv'][1])) {
	echo "You must specify an ini file as the first argument \n";
	echo "Eg:  php date_reminder.php my-config-file.ini \n";
}
$ini = parse_ini_file($_SERVER['argv'][1]);

define('JETHRO_ROOT', dirname(dirname(__FILE__)));
set_include_path(get_include_path().PATH_SEPARATOR.JETHRO_ROOT);
if (!is_readable(JETHRO_ROOT.'/conf.php')) {
	trigger_error('Jethro configuration file not found.  You need to copy conf.php.sample to conf.php and edit it before Jethro can run', E_USER_ERROR);
	exit();
}
require_once JETHRO_ROOT.'/conf.php';
if (!defined('DSN')) define('DSN', constant('PRIVATE_DSN'));
require_once JETHRO_ROOT.'/include/init.php';
require_once JETHRO_ROOT.'/include/user_system.class.php';
require_once JETHRO_ROOT.'/include/emailer.class.php';
require_once JETHRO_ROOT.'/include/system_controller.class.php';
$GLOBALS['user_system'] = new User_System();
$GLOBALS['user_system']->setPublic();
$GLOBALS['system'] = System_Controller::get();

$SQL = 'SELECT p.*, cfv.value_date AS expirydate
		FROM _person p
		JOIN custom_field_value cfv ON cfv.personid = p.id AND cfv.fieldid = '.(int)$ini['CUSTOM_FIELD_ID'].'
		WHERE cfv.value_date  = CURDATE() + INTERVAL '.(int)$ini['REMINDER_OFFSET'].' DAY';
$res = $GLOBALS['db']->queryAll($SQL);
check_db_result($res);
foreach ($res as $row) {
	send_reminder($row);
}


function send_reminder($person)
{
	global $ini;
	$toEmail = $person['email'];
	if (!empty($ini['OVERRIDE_RECIPIENT'])) $toEmail = $ini['OVERRIDE_RECIPIENT'];
	
	if (!strlen($person['email'])) {
		if (!empty($ini['VERBOSE'])) echo $person['first_name'].' '.$person['last_name']." has no email address - skipping \n";
		return;
	}
	
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
		echo "Failed to send to $toEmail \n";
	} else if (!empty($ini['VERBOSE'])) {
		echo "Sent reminder to ".$person['first_name'].' '.$person['last_name']."\n";
	}
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

