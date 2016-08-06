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
// Check for old style DSN - and try to work - but this is messy and horrible to use
if (defined('PRIVATE_DSN')) {
		preg_match('|([a-z]+)://([^:]*)(:(.*))?@([A-Za-z0-9\.-]*)(/([0-9a-zA-Z_/\.]*))|',
     PRIVATE_DSN,$matches);
		 define('DB_TYPE', $matches[1]);
		 define('DB_HOST', $matches[5]);
		 define('DB_DATABASE', $matches[7]);
		 define('DB_PRIVATE_USERNAME', $matches[2]);
		 define('DB_PRIVATE_PASSWORD', $matches[4]);
}
if (!defined('DSN')) {
		define('DSN', DB_TYPE . ':host=' . DB_HOST . (!empty(DB_PORT)? (';port=' . DB_PORT):'') . ';dbname=' . DB_DATABASE . ';charset=utf8');
}
if (!defined('DB_USERNAME')) define('DB_USERNAME', DB_PRIVATE_USERNAME);
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', DB_PRIVATE_PASSWORD);
require_once JETHRO_ROOT.'/include/init.php';
require_once JETHRO_ROOT.'/include/user_system.class.php';
require_once JETHRO_ROOT.'/include/emailer.class.php';
require_once JETHRO_ROOT.'/include/system_controller.class.php';
$GLOBALS['user_system'] = new User_System();
$GLOBALS['user_system']->setPublic();
$GLOBALS['system'] = System_Controller::get();

$SQL = 'SELECT p.*, cfv.value_date AS expirydate ';
if ($ini['CC_STATUS']) {
	$SQL .= ', GROUP_CONCAT(cc.email SEPARATOR ";") as cc, GROUP_CONCAT(CONCAT(cc.first_name, " ", cc.last_name) SEPARATOR ";") as cc_name ';
}
$SQL .= '
		FROM _person p
		JOIN custom_field_value cfv ON cfv.personid = p.id AND cfv.fieldid = '.(int)$ini['CUSTOM_FIELD_ID'];
if ($ini['CC_STATUS']) {
	$map = array_flip(Person::getStatusOptions());
	$SQL .= '
			LEFT JOIN _person cc ON (
				LENGTH(cc.email) > 0
				AND cc.congregationid = p.congregationid
				AND cc.status = '.$GLOBALS['db']->quote($map[$ini['CC_STATUS']]).'
			)';
}
$SQL .= '
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

	if (!empty($person['cc'])) {
		$cc_names = explode(';', $person['cc_name']);
		foreach (explode(';', $person['cc']) as $i => $cc) {
			if (!empty($ini['OVERRIDE_RECIPIENT'])) {
				$message->addCC($ini['OVERRIDE_RECIPIENT'], $cc_names[$i]);
			} else {
				$message->addCC($cc, $cc_names[$i]);
			}
		}
	}

	$res = Emailer::send($message);
	if (!$res) {
		echo "Failed to send to $toEmail \n";
	} else if (!empty($ini['VERBOSE'])) {
		echo "Sent reminder to ".$person['first_name'].' '.$person['last_name'];
		if (!empty($person['cc'])) echo " CC to ".$person['cc'];
		echo "\n";
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
