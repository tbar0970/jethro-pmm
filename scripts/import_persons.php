<?php
/*
 * JETHRO PMM
 *
 * This file is part of Jethro PMM - https://github.com/tbar0970/jethro-pmm
 *
 * Jethro PMM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jethro PMM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jethro PMM.  If not, see <https://www.gnu.org/licenses/>.
 *
 * import_persons.php - scheduled import of persons from a Google Sheet or CSV file.
 *
 * Fetches a CSV file (for example a Google Sheet tab exported as CSV), validates it with
 * Person_Importer, then applies the plan in one transaction. Intended for cron:
 *
 *   php scripts/import_persons.php \
 *     --google-spreadsheet='https://docs.google.com/spreadsheets/d/<id>/edit?usp=sharing' \
 *     --create-group='Signup sheet import'
 *
 * --google-spreadsheet accepts a Google Sheets share/edit link and fetches its CSV export; the
 * last tab is used unless --google-spreadsheet-tab or a gid in the link says otherwise. --file
 * reads a CSV directly. --google-service-account=<json-key> uses the Sheets API instead of the
 * public CSV export, so the spreadsheet can stay private (share it with the service account as
 * Viewer). Further named source options can be added alongside them.
 *
 * See docs/docs/developer/reference/import.md
 */

if ((php_sapi_name() !== 'cli') && !defined('STDIN')) {
	echo "This script must be run from the command line\n";
	exit(1);
}

$usage = "Usage: php scripts/import_persons.php --google-spreadsheet=<url> --group=<id> [options]\n"
	."       php scripts/import_persons.php --file=<path> --create-group=<name> [options]\n\n"
	."Options:\n"
	."  --google-spreadsheet=<url>   Import a Google Sheet (share/edit or export link)\n"
	."  --google-spreadsheet-tab=<name>  Sheet tab to import (default: the last tab)\n"
	."  --google-service-account=<path>  Read a private sheet with this service-account JSON key\n"
	."  --file=<path>          Read a local CSV file instead\n"
	."  --group=<id>           Add every imported person to this existing person group\n"
	."  --create-group=<name>  Create this group and add every imported person to it\n"
	."  --match-existing       Match and update existing persons by name (default: off)\n"
	."  --match-email          With --match-existing, require the email to match too\n"
	."  --match-mobile         With --match-existing, require the mobile to match too\n"
	."  --no-overwrite         Do not overwrite existing non-blank values when updating\n"
	."  --creator=<staff-id>   Staff account to attribute the import to (default: the lowest staff id)\n"
	."  --config=<path>        Jethro configuration file (default: <root>/conf.php; for tests/staging)\n"
	."  --dry-run              Parse and report only; do not write to the database\n"
	."  --verbose              Show the Admin import preview details (families, persons, groups)\n"
	."  --help                 Show this help\n";

// Hand-rolled parsing rather than getopt(): it gives each source its own named option, and
// options may be written as --name=value or --name value.
$opts = [];
$valued_opts = ['google-spreadsheet', 'google-spreadsheet-tab', 'google-service-account', 'file', 'group', 'create-group', 'creator', 'config'];
$flag_opts = ['match-existing', 'match-email', 'match-mobile', 'no-overwrite', 'dry-run', 'verbose', 'help'];
$args = array_slice($_SERVER['argv'], 1);
for ($j = 0; $j < count($args); $j++) {
	$arg = $args[$j];
	if (substr($arg, 0, 2) !== '--') {
		fwrite(STDERR, "Unexpected argument. Use --google-spreadsheet=<url> or --file=<path>\n\n".$usage);
		exit(2);
	}
	$name = substr($arg, 2);
	$value = NULL;
	if (FALSE !== ($eq = strpos($name, '='))) {
		$value = substr($name, $eq + 1);
		$name = substr($name, 0, $eq);
	}
	if (in_array($name, $valued_opts, TRUE)) {
		if ($value === NULL) {
			$next = ($j + 1 < count($args)) ? $args[$j + 1] : NULL;
			if ($next === NULL || substr($next, 0, 2) === '--') {
				fwrite(STDERR, "Option --$name requires a value\n\n".$usage);
				exit(2);
			}
			$value = $args[++$j];
		}
		if ($value === '') {
			fwrite(STDERR, "Option --$name requires a value\n\n".$usage);
			exit(2);
		}
		$opts[$name] = $value;
	} else if (in_array($name, $flag_opts, TRUE) && $value === NULL) {
		$opts[$name] = TRUE;
	} else {
		fwrite(STDERR, "Unknown option --$name\n\n".$usage);
		exit(2);
	}
}
if (isset($opts['help'])) {
	echo $usage;
	exit(0);
}

$google_spreadsheet = isset($opts['google-spreadsheet']) ? $opts['google-spreadsheet'] : NULL;
$google_service_account = isset($opts['google-service-account']) ? $opts['google-service-account'] : NULL;
$file = isset($opts['file']) ? $opts['file'] : NULL;
if (isset($opts['google-spreadsheet-tab']) && !$google_spreadsheet) {
	fwrite(STDERR, "--google-spreadsheet-tab requires --google-spreadsheet\n");
	exit(2);
}
if ($google_service_account && !$google_spreadsheet) {
	fwrite(STDERR, "--google-service-account requires --google-spreadsheet\n");
	exit(2);
}
if (!$google_spreadsheet && !$file) {
	fwrite(STDERR, "Specify --google-spreadsheet=<url> or --file=<path>\n\n".$usage);
	exit(2);
}
if ($google_spreadsheet && $file) {
	fwrite(STDERR, "Specify either --google-spreadsheet or --file, not both\n");
	exit(2);
}
if (!isset($opts['group']) && !isset($opts['create-group'])) {
	fwrite(STDERR, "Specify --group=<id> or --create-group=<name>\n");
	exit(2);
}
if (isset($opts['group']) && isset($opts['create-group'])) {
	fwrite(STDERR, "Specify either --group or --create-group, not both\n");
	exit(2);
}

define('JETHRO_ROOT', dirname(dirname(__FILE__)));
set_include_path(get_include_path().PATH_SEPARATOR.JETHRO_ROOT);
require_once JETHRO_ROOT.'/include/google_sheets.class.php';
if ($google_service_account) {
	$key_real = realpath($google_service_account);
	$root_real = realpath(JETHRO_ROOT);
	if ($key_real && $root_real && str_starts_with($key_real, $root_real.DIRECTORY_SEPARATOR)) {
		fwrite(STDERR, "Warning: the service-account key is inside the Jethro checkout; keep credentials outside the web root.\n");
	}
}
$config_path = isset($opts['config']) ? $opts['config'] : JETHRO_ROOT.'/conf.php';
if (!is_readable($config_path)) {
	fwrite(STDERR, "Jethro configuration file not found: $config_path\n");
	exit(1);
}
require_once $config_path;
define('DB_MODE', 'private');
require_once JETHRO_ROOT.'/include/init.php';

require_once JETHRO_ROOT.'/include/user_system.class.php';
$GLOBALS['user_system'] = new User_System();
$GLOBALS['user_system']->setCLIScript();

require_once JETHRO_ROOT.'/include/system_controller.class.php';
$GLOBALS['system'] = System_Controller::get();

require_once JETHRO_ROOT.'/include/person_importer.class.php';

$creator_id = isset($opts['creator']) ? (int)$opts['creator'] : 0;
if (!$creator_id) {
	$creator_id = (int)$GLOBALS['db']->queryOne('SELECT MIN(id) FROM staff_member');
	if (!$creator_id) {
		fwrite(STDERR, "No staff account exists to attribute the import to; pass --creator=<id>\n");
		exit(1);
	}
}
$creator = $GLOBALS['db']->queryAll(
	'SELECT sm.*, p.* FROM staff_member sm JOIN _person p ON sm.id = p.id WHERE sm.id = '.(int)$creator_id
);
if (empty($creator)) {
	fwrite(STDERR, "No such staff account: $creator_id\n");
	exit(1);
}
// Run as that account: permission checks (group membership, locks) need a current user,
// and created records are attributed to it. staff_member.id is the person id.
$creator[0]['congregation_restrictions'] = [];
$creator[0]['group_restrictions'] = [];
$_SESSION['user'] = $creator[0];
echo "Attributing the import to staff account #$creator_id (".$creator[0]['username'].", ".$creator[0]['first_name'].' '.$creator[0]['last_name'].")\n";

if (isset($opts['group'])) {
	// Use the same load path as apply(), after the acting account is in session, so a
	// group the import cannot actually see is rejected here rather than silently ignored.
	$GLOBALS['system']->includeDBClass('person_group');
	$group = new Person_Group((int)$opts['group']);
	if (!$group->id) {
		fwrite(STDERR, "No person group with ID ".(int)$opts['group']."\n");
		exit(1);
	}
	unset($group);
}

$fetched = NULL;
if ($google_spreadsheet) {
	$google_spreadsheet_tab = isset($opts['google-spreadsheet-tab']) ? $opts['google-spreadsheet-tab'] : NULL;
	if ($google_service_account) {
		if ($google_spreadsheet_tab !== NULL) {
			echo 'Fetching sheet tab "'.$google_spreadsheet_tab."\" with a service account\n";
		} else {
			echo "Using the spreadsheet's last visible tab (service account)\n";
		}
		try {
			$client = new Google_Sheets_Client($google_service_account);
			$csv = $client->fetchCsv($google_spreadsheet, $google_spreadsheet_tab);
		} catch (\RuntimeException $e) {
			fwrite(STDERR, $e->getMessage()."\n");
			exit(1);
		}
		$fetched = write_csv_temp($csv);
	} else {
		try {
			$resolved = Google_Sheets_Client::csvUrl($google_spreadsheet, $google_spreadsheet_tab);
			$csv_url = $resolved['url'];
			if ($google_spreadsheet_tab !== NULL) {
				echo 'Fetching sheet tab "'.$google_spreadsheet_tab."\" as CSV\n";
			} else if ($resolved['last_tab']) {
				echo "Using the spreadsheet's last tab\n";
			} else if ($csv_url !== $google_spreadsheet) {
				echo "Interpreting the Google Sheets link as its CSV export\n";
			}
			$fetched = write_csv_temp(Google_Sheets_Client::fetchCsvUrl($csv_url));
		} catch (\RuntimeException $e) {
			fwrite(STDERR, $e->getMessage()."\n");
			exit(1);
		}
	}
	$csv_path = $fetched;
	register_shutdown_function(function () use ($fetched) {
		if (is_file($fetched)) unlink($fetched);
	});
} else {
	if (!is_readable($file)) {
		fwrite(STDERR, "Cannot read $file\n");
		exit(1);
	}
	$csv_path = $file;
}

$importer = new Person_Importer([
	'overwrite_existing' => isset($opts['no-overwrite']) ? 0 : 1,
	'match_existing' => isset($opts['match-existing']),
	'match_email' => isset($opts['match-email']),
	'match_mobile_tel' => isset($opts['match-mobile']),
	'groupid' => isset($opts['group']) ? (int)$opts['group'] : 0,
	'new_group_name' => isset($opts['create-group']) ? $opts['create-group'] : '',
	'creator_userid' => $creator_id,
]);

$ok = $importer->parse($csv_path, ',');
foreach ($importer->getMessages() as $message) {
	fwrite($message['type'] === 'error' ? STDERR : STDOUT, $message['text']."\n");
}

$plan = $importer->getPlan();

if (!$ok) {
	foreach ($importer->getRowErrors() as $line => $errors) {
		fwrite(STDERR, "Row $line: ".implode('; ', $errors)."\n");
	}
	fwrite(STDERR, "Import rejected; nothing written\n");
	exit(1);
}

if (!empty($opts['verbose'])) {
	print_import_details($plan);
}

if (isset($opts['dry-run'])) {
	echo "Would import: ".describe_import($plan)."\n";
	echo "Dry run; nothing written\n";
	exit(0);
}

$result = $importer->apply(function ($done, $todo) {
	if ($todo && ($done % 25 === 0)) {
		fwrite(STDERR, sprintf("  %d/%d\n", $done, $todo));
	}
});
if (!$result['ok']) {
	foreach ($result['errors'] as $line => $errors) {
		fwrite(STDERR, "Row $line: ".implode('; ', $errors)."\n");
	}
	fwrite(STDERR, "Import aborted; transaction rolled back\n");
	exit(1);
}

printf("Imported: %s\n", describe_import($plan));
printf("Import complete. Group id: %s\n", $result['group_id'] ?: 'none');
exit(0);

/**
 * Summarise the persons, families and groups an import created or updated.
 *
 * The whole import is one transaction, so after a successful apply() the plan's counts are the
 * rows that were committed.
 *
 * @param array<string,mixed> $plan Plan returned by Person_Importer::getPlan()
 * @return string
 */
function describe_import($plan)
{
	return sprintf(
		'%d new families (%d persons), %d persons added to existing families, %d family updates, %d person updates, %d new groups',
		count($plan['new_families']),
		$plan['total_new_persons'],
		count($plan['new_persons']),
		count($plan['family_updates']),
		count($plan['person_updates']),
		count($plan['new_groups'])
	);
}

/**
 * Print the details the Admin import preview shows: groups, families and persons.
 *
 * @param array<string,mixed> $plan Plan returned by Person_Importer::getPlan()
 * @return void
 */
function print_import_details($plan)
{
	$GLOBALS['system']->includeDBClass('person_group');
	$statuses = Person_Status::getActive();
	$custom_names = [];
	$custom_fields = Person::getCustomFields();
	foreach ($plan['used_custom_fields'] as $id => $key) {
		$custom_names[$key] = array_get($custom_fields, $id, ['name' => $key])['name'];
	}

	if (!empty($plan['groupid'])) {
		$group = new Person_Group((int)$plan['groupid']);
		echo 'Audit group: '.$group->toString().' (id '.(int)$plan['groupid'].")\n";
	} else if (!empty($plan['new_group_name'])) {
		echo 'Audit group: '.$plan['new_group_name']." (to be created)\n";
	}

	if (!empty($plan['new_groups'])) {
		echo "New groups to be created:\n";
		foreach ($plan['new_groups'] as $name) echo "  - $name\n";
	}
	if (!empty($plan['matched_groups'])) {
		echo "Existing groups to have members added:\n";
		foreach ($plan['matched_groups'] as $id) {
			$group = new Person_Group((int)$id);
			echo '  - '.$group->toString()."\n";
		}
	}
	if (!empty($plan['new_families'])) {
		echo "New families to be created:\n";
		foreach ($plan['new_families'] as $family) {
			echo '  '.format_family($family)."\n";
			foreach ($family['members'] as $member) {
				echo '    - '.format_person($member, $statuses, $custom_names)."\n";
			}
		}
	}
	if (!empty($plan['new_persons'])) {
		echo "New persons to be added to existing families:\n";
		foreach ($plan['new_persons'] as $person) {
			echo '  family #'.(int)$person['familyid'].': '.format_person($person, $statuses, $custom_names)."\n";
		}
	}
	if (!empty($plan['family_updates'])) {
		echo "Existing families to be updated:\n";
		foreach ($plan['family_updates'] as $id => $family) {
			echo '  #'.(int)$id.' '.format_family($family)."\n";
		}
	}
	if (!empty($plan['person_updates'])) {
		echo "Existing persons to be updated:\n";
		foreach ($plan['person_updates'] as $id => $person) {
			echo '  #'.(int)$id.' '.format_person($person, $statuses, $custom_names)."\n";
		}
	}
}

/**
 * @param array<string,mixed> $family
 * @return string
 */
function format_family($family)
{
	$bits = [array_get($family, 'family_name', '')];
	if (!empty($family['home_tel'])) $bits[] = 'tel '.$family['home_tel'];
	$address = array_filter([
		array_get($family, 'address_street', ''),
		array_get($family, 'address_suburb', ''),
		array_get($family, 'address_state', ''),
		array_get($family, 'address_postcode', ''),
	], 'strlen');
	if ($address) $bits[] = implode(' ', $address);
	return implode(' | ', $bits);
}

/**
 * @param array<string,mixed> $person
 * @param array<int,array<string,mixed>> $statuses Person_Status::getActive()
 * @param array<string,string> $custom_names Custom-field header key => label
 * @return string
 */
function format_person($person, $statuses, $custom_names)
{
	$bits = [trim(array_get($person, 'first_name', '').' '.array_get($person, 'last_name', ''))];
	if (!empty($person['age_bracket'])) $bits[] = $person['age_bracket'];
	$status = array_get($person, 'status', '');
	if (is_numeric($status) && isset($statuses[(int)$status])) {
		$status = $statuses[(int)$status]['label'];
	}
	if (strlen((string)$status)) $bits[] = $status;
	if (!empty($person['congregation'])) $bits[] = $person['congregation'];
	if (!empty($person['email'])) $bits[] = $person['email'];
	if (!empty($person['mobile_tel'])) $bits[] = $person['mobile_tel'];
	foreach ($custom_names as $key => $label) {
		if (!empty($person[$key])) $bits[] = $label.': '.$person[$key];
	}
	if (!empty($person['_note'])) $bits[] = 'note: '.$person['_note'];
	if (!empty($person['_groups'])) $bits[] = 'groups: '.implode(', ', $person['_groups']);
	return implode(' | ', $bits);
}

/**
 * Write CSV text to a temporary file for Person_Importer::parse().
 *
 * @param string $csv
 * @return string Path to the temporary file
 */
function write_csv_temp($csv)
{
	$tmp = tempnam(sys_get_temp_dir(), 'jethroimport');
	if (($tmp === FALSE) || (file_put_contents($tmp, $csv) === FALSE)) {
		fwrite(STDERR, "Could not write a temporary CSV file\n");
		exit(1);
	}
	return $tmp;
}
