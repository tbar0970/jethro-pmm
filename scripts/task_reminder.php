<?php
/**
 * This script can be used to send emails to people who have recently
 * been assigned a note.  It should be configured to run every 5 minutes by cron.
 */

 if ((php_sapi_name() !== 'cli') && !defined('STDIN')) {
	echo "This script must be run from the command line";
	exit;
}

$VERBOSE = in_array('--verbose', $_SERVER['argv']);
$DRYRUN = in_array('--dry-run', $_SERVER['argv']);
$minutes = 5;

define('JETHRO_ROOT', dirname(dirname(__FILE__)));
set_include_path(get_include_path().PATH_SEPARATOR.JETHRO_ROOT);
if (!is_readable(JETHRO_ROOT.'/conf.php')) {
	trigger_error('Jethro configuration file not found.  You need to copy conf.php.sample to conf.php and edit it before Jethro can run', E_USER_ERROR);
	exit();
}
require_once JETHRO_ROOT.'/conf.php';
define('DB_MODE', 'PRIVATE');
require_once JETHRO_ROOT.'/include/init.php';

if (ifdef('TASK_NOTIFICATION_ENABLED', FALSE) == FALSE) {
	if ($VERBOSE) echo "Task notification is disabled in conf.php - exiting \n";
	exit;
}

require_once JETHRO_ROOT.'/include/user_system.class.php';
require_once JETHRO_ROOT.'/include/system_controller.class.php';
$GLOBALS['user_system'] = new User_System();
$GLOBALS['user_system']->setCLIScript();
$GLOBALS['system'] = System_Controller::get();

if ($DRYRUN) $VERBOSE = TRUE;

$introText = _("Hi %s,")."\n\n";

$newNotesPluralText = _("%d new notes were recently assigned to you for action").".\n\n";
$newNotesSingularText = _("A new note was recently assigned to you for action").".\n\n";
$totalNotesText = _("There are %d notes assigned to you in total").".\n\n";

$outroText = _("Please log in at %s to view your notes").".\n\n"
		._("--\nThis email was sent automatically by the %s Jethro system");

if (!defined('TASK_NOTIFICATION_FROM_ADDRESS')) {
	trigger_error("You must set TASK_NOTIFICATION_FROM_ADDRESS setting to use task notificatione emails");
	exit;
}

$reminders = Abstract_Note::getNotifications($minutes);
$fromText = ifdef('TASK_NOTIFICATION_FROM_NAME', SYSTEM_NAME.' Jethro');
$subject = ifdef('TASK_NOTIFICATION_SUBJECT', 'New notes assigned to you');

require_once JETHRO_ROOT.'/include/emailer.class.php';

foreach ($reminders as $reminder) {
	if ($reminder['email'] == '') continue;
	$content = sprintf($introText, $reminder['first_name']);
	if ($reminder['new_notes'] == 1) {
		$content .= $newNotesSingularText;
	} else {
		$content .= sprintf($newNotesPluralText, $reminder['new_notes']);
	}
	if ($reminder['total_notes'] > $reminder['new_notes']) {
		$content .= sprintf($totalNotesText, $reminder['total_notes']);
	}
	$content .= sprintf($outroText,
						BASE_URL,
						SYSTEM_NAME);
	
	$html = nl2br($content);
	$message = Emailer::newMessage()
	  ->setSubject($subject)
	  ->setFrom(array(TASK_NOTIFICATION_FROM_ADDRESS => $fromText))
	  ->setTo(array($reminder['email'] => $reminder['first_name'].' '.$reminder['last_name']))
	  ->setBody($content)
	  ->addPart($html, 'text/html');

	if ($DRYRUN) {
		$res = TRUE;
	} else {
		$res = Emailer::send($message);
	}
	
	if (!$res) {
		echo "Failed to send to ".$reminder['email'];
	} else if ($VERBOSE) {
		echo "Sent reminder to ".$reminder['first_name'].' '.$reminder['last_name']."\n";
	}
}
if ($VERBOSE) { echo "Done \n"; }
