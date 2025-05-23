<?php
/*****************************************************************
 * This script will report if any Persons have been incorrectly had their Age Bracket set to 'Adult',
 * as a result of a Jethro 2.35.1 bug https://github.com/tbar0970/jethro-pmm/issues/1086
 * If affected persons are found, the script points people to a new Jethro page which will fix the problem.
 * This script makes no changes directly, as user input is required.
 ******************************************************************/
if ((php_sapi_name() !== 'cli') && !defined('STDIN')) {
	echo "This script must be run from the command line";
	exit;
}

const SINGLE_PERSON = "single person affected";
const MULTIPLE_PERSONS = "multiple persons affected";
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('JETHRO_ROOT', dirname(dirname(dirname(__FILE__))));
set_include_path(get_include_path().PATH_SEPARATOR.JETHRO_ROOT);
require_once JETHRO_ROOT.'/conf.php';
define('DB_MODE', 'private');
require_once JETHRO_ROOT.'/include/init.php';
require_once JETHRO_ROOT.'/upgrades/upgradefixes/2.5.1_fix_agebrackets/AgeBracketChangesFixer.php';
require_once JETHRO_ROOT.'/include/user_system.class.php';

$GLOBALS['user_system'] = new User_System();
$GLOBALS['user_system']->setCLIScript();

require_once JETHRO_ROOT.'/include/system_controller.class.php';
$GLOBALS['system'] = System_Controller::get();

$badchangegroups = AgeBracketChangesFixer::getBadChangeGroups();

// We're interested if any of our BadChangeGroups affect >1 user. If so that's a very likely indication of problems.
// Partition our BadChangeGroups into $groupedchanges[1] (single-person affected) and $groupedchanges['n'] (multiple persons affected).
$groupedchanges = array_reduce($badchangegroups, function ($carry, $cg) {
	if ($cg->isBulkEdit()) {
		$carry[MULTIPLE_PERSONS][] = $cg;
	} else {
		$carry[SINGLE_PERSON][] = $cg;
	}
	return $carry;
}, []);

if (!empty($groupedchanges)) {
	if (array_key_exists(MULTIPLE_PERSONS, $groupedchanges)) {
		// Bulk edit definitely caused problems. Report affected persons by name.
		$allaffected = array_map(fn($gc) => $gc->getAffectedPersons(), $groupedchanges[MULTIPLE_PERSONS]);
		$allaffected = array_unique(array_merge(...$allaffected));
		print("Warning: ".count($allaffected)." people have been incorrectly turned into Adults by bug https://github.com/tbar0970/jethro-pmm/issues/1086:".PHP_EOL);
	} elseif (array_key_exists(SINGLE_PERSON, $groupedchanges)) {
		$allaffected = array_map(fn($gc) => $gc->getAffectedPersons(), $groupedchanges[SINGLE_PERSON]);
		$allaffected = array_unique(array_merge(...$allaffected));
		print("Warning: ".count($allaffected)." people MAY have been incorrectly turned into Adults by bug https://github.com/tbar0970/jethro-pmm/issues/1086:".PHP_EOL);
	}
	foreach ($allaffected as $affected) {
		print($affected.PHP_EOL);
	}
	print("Please log in to Jethro to fix this - ".build_url(Array('view' => '_fix_age_brackets')).PHP_EOL);
}
//AgeBracketChangesFixer::printBadChanges($badchanges);
//AgeBracketChangesFixer::fix($badChanges);
exit(0);
