<?php

/**
 * Test the 'Bible Reading Downloads' feature, by looking up a Bible passage text
 *  via the API.Bible REST API.
 *
 * Usage:
 *   HTTP_HOST=jethro.mychurch.org php scripts/biblelookup.php "John 3:16-17"
 *   HTTP_HOST=jethro.mychurch.org php scripts/biblelookup.php --translation=CSB "Genesis 1:1"
 */

// Bootstrap Jethro
if (!defined('JETHRO_ROOT')) {
	define('JETHRO_ROOT', dirname(__DIR__));
}
set_include_path(get_include_path().PATH_SEPARATOR.JETHRO_ROOT);
if (!is_readable(JETHRO_ROOT.'/conf.php')) {
	throw new RuntimeException('Jethro configuration file not found. You need to copy conf.php.sample to conf.php and edit it before Jethro can run');
}
require_once JETHRO_ROOT.'/conf.php';
define('DB_MODE', 'private');
require_once JETHRO_ROOT.'/include/init.php';
require_once JETHRO_ROOT.'/include/user_system.class.php';
$GLOBALS['user_system'] = new User_System();
$GLOBALS['user_system']->setCLIScript();
require_once JETHRO_ROOT.'/include/system_controller.class.php';
$GLOBALS['system'] = System_Controller::get();

require_once JETHRO_ROOT.'/include/bibleapi.php';

$args = $_SERVER['argv'] ?? [];
array_shift($args); // Remove script path

if ($args && str_starts_with($args[0], '/')) {
	// Passthrough mode: first arg is the API path, remaining --key=value become query params
	$path = array_shift($args);
	$params = [];
	foreach ($args as $arg) {
		if (preg_match('/^--(.+?)=(.+)$/', $arg, $m)) {
			$params[$m[1]] = $m[2];
		}
	}
	$result = callBibleApi($path, $params);
	echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
	exit(empty($result) ? 1 : 0);
}

$referenceParts = [];

$showBibles = false;
$translation = null;
for ($i = 0, $n = count($args); $i < $n; $i++) {
	$arg = $args[$i];
	if ($arg === '--bibles') {
		$showBibles = true;
	} elseif ($arg === '--translation') {
		$translation = $args[++$i] ?? null;
	} elseif (str_starts_with($arg, '--translation=')) {
		$translation = substr($arg, strlen('--translation='));
	} elseif (!str_starts_with($arg, '--')) {
		$referenceParts[] = $arg;
	}
}

$reference = implode(' ', $referenceParts);

if ($showBibles) {
	$translations = getBibleTranslations();
	foreach ($translations as $id => $info) {
		echo sprintf("%-40s %-10s %s\n", $id, $info['abbreviation'], $info['name']);
	}
	exit(empty($translations) ? 1 : 0);
}

if ($reference === '') {
	echo "Usage: HTTP_HOST=jethro.mychurch.org php scripts/biblelookup.php [--translation=NIV] <reference>\n";
	echo "   or: HTTP_HOST=jethro.mychurch.org php scripts/biblelookup.php /path [--param=value ...]\n\n";
	echo "Examples:\n";
	echo "  HTTP_HOST=jethro.mychurch.org php scripts/biblelookup.php \"John 3:16-17\"\n";
	echo "  HTTP_HOST=jethro.mychurch.org php scripts/biblelookup.php --translation=CSB \"Genesis 1:1\"\n";
	echo "  HTTP_HOST=jethro.mychurch.org php scripts/biblelookup.php /bibles/78a9f6124f344018-01/verses/JHN.3.16\n";
	exit(1);
}

$bibleId = isset($translation) ? resolveBibleId($translation) : APIBIBLE_NIV;
if ($bibleId === null) {
	echo "Error: Translation '$translation' not found.\n";
	echo "Run with --bibles to see available translations.\n";
	exit(1);
}

$result = fetchBiblePassage($reference, $bibleId);

if ($result === null) {
	echo "Error: Could not fetch passage.\n";
	exit(1);
}

[$text, $attribution] = $result;

echo $text . "\n";
echo "\n" . strip_tags($attribution) . "\n";
