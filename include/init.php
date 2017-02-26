<?php
if (file_exists(dirname(__FILE__).'/version.txt')) {
	define('JETHRO_VERSION', trim(file_get_contents(dirname(__FILE__).'/version.txt')));
} else {
	define('JETHRO_VERSION', 'DEV');
}

$path_sep = defined('PATH_SEPARATOR') ? PATH_SEPARATOR : ((FALSE === strpos($_ENV['OS'], 'Win')) ? ';' : ':');
set_include_path(ini_get('include_path').$path_sep.JETHRO_ROOT.$path_sep.JETHRO_ROOT.'/include/'.$path_sep.JETHRO_ROOT.'/db_objects/');

spl_autoload_register(function ($class_name) {
	 @include_once strtolower($class_name) . '.class.php';
});

// set error level such that we cope with PHP versions before and after 5.3 when E_DEPRECATED was introduced.
$error_level = defined('E_DEPRECATED') ? (E_ALL & ~constant('E_DEPRECATED') & ~constant('E_STRICT')) : E_ALL;
error_reporting($error_level);
@ini_set('display_errors', 1);

// If max length is set, set the cookie timeout - this will allow sessions to outlast browser invocations
$expiryTime = defined('SESSION_MAXLENGTH_MINS') ? SESSION_MAXLENGTH_MINS * 60 : NULL;
session_set_cookie_params($expiryTime, parse_url(BASE_URL, PHP_URL_PATH));

if (session_id() == '') {
	session_name('JethroSession');
	session_start();
}
require_once JETHRO_ROOT.'/include/general.php';
strip_all_slashes();

if (php_sapi_name() != 'cli') {
	// Make sure we're at the correct URL
	$do_redirect = FALSE;
	if (REQUIRE_HTTPS && !defined('IS_PUBLIC') && empty($_SERVER['HTTPS'])) {
		$do_redirect = TRUE;
	}
	if (strpos(array_get($_SERVER, 'HTTP_HOST', array_get($_SERVER, 'SERVER_NAME', '')).$_SERVER['REQUEST_URI'], str_replace(Array('http://', 'https://'), '', BASE_URL)) !== 0) {
		$do_redirect = TRUE;
	}
	if ($do_redirect) {
		header('Location: '.build_url(Array()));
		exit();
	}
}

// Set up the DB
if (!@include_once('MDB2.php')) {
	trigger_error('MDB2 Library not found on the server.  See the readme file for how to work around this');
	exit();
}
$GLOBALS['db'] =& MDB2::factory(DSN);
if (MDB2::isError($GLOBALS['db']) || MDB2::isError($GLOBALS['db']->getConnection())) {
	trigger_error('Could not connect to database - please check for mistakes in your DSN in conf.php, and check in MySQL that the database exists and the specified user has been granted access.', E_USER_ERROR);
	exit();
}

$GLOBALS['db']->setOption('portability', $GLOBALS['db']->getOption('portability') & !MDB2_PORTABILITY_EMPTY_TO_NULL);
$GLOBALS['db']->setFetchmode(MDB2_FETCHMODE_ASSOC);


if (defined('TIMEZONE') && constant('TIMEZONE')) {
	date_default_timezone_set(constant('TIMEZONE'));
	$GLOBALS['db']->query('SET time_zone = "'.date('P').'"');
}

//SET MySQL session variables to account for strict mode
if (defined('STRICT_MODE_FIX') && STRICT_MODE_FIX) {
	$GLOBALS['db']->query('SET SESSION sql_mode="NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION"');
}

@ini_set('default_charset', 'UTF-8');

Config_Manager::init();
