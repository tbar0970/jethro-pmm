<?php
if (file_exists(dirname(__FILE__).'/version.txt')) {
	define('JETHRO_VERSION', trim(file_get_contents(dirname(__FILE__).'/version.txt')));
} else {
	define('JETHRO_VERSION', 'DEV');
}

$path_sep = defined('PATH_SEPARATOR') ? PATH_SEPARATOR : ((FALSE === strpos($_ENV['OS'], 'Win')) ? ';' : ':');
set_include_path(ini_get('include_path').$path_sep.JETHRO_ROOT.$path_sep.JETHRO_ROOT.'/include/'.$path_sep.JETHRO_ROOT.'/db_objects/');

spl_autoload_register(function ($class_name) {
	// If this autoloader fails, we want it to quietly continue on to other autoloaders
	// (eg the Composer one). But the @(shutup) operator does not supress include-once 
	// errors in PHP8+.  And file_exists does not take include_path into account.
	// So this ugly approach to supressing 'file not found' errors is necessary.
	$old_er = error_reporting();
	error_reporting(0);
	@include_once strtolower($class_name) . '.class.php';
	error_reporting($old_er);
});

// set error level such that we cope with PHP versions before and after 5.3 when E_DEPRECATED was introduced.
$error_level = defined('E_DEPRECATED') ? (E_ALL & ~constant('E_DEPRECATED') /*& ~constant('E_STRICT')*/) : E_ALL;
error_reporting($error_level);
@ini_set('display_errors', 1);

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
require_once JETHRO_ROOT .'/include/jethrodb.php';
JethroDB::init(ifdef('DB_MODE', 'PRIVATE'));

// Apply Mysql mode if applicable
if ($sqlMode = ifdef('SQL_MODE')) {
	$GLOBALS['db']->query('SET SESSION sql_mode="'.$sqlMode.'"');
}

@ini_set('default_charset', 'UTF-8');

Config_Manager::init();

// The session we're about to create should not be GC'ed by PHP until at least SESSION_TIMEOUT_MINS have elapsed.
// The default 'session.gc_maxlifetime' is 24 minutes, so we need to override it.
if (defined('SESSION_TIMEOUT_MINS')) {
	@ini_set('session.gc_maxlifetime', SESSION_TIMEOUT_MINS*60);
}

// If max length is set, set the cookie timeout - this will allow sessions to outlast browser invocations
$expiryTime = defined('SESSION_MAXLENGTH_MINS') ? SESSION_MAXLENGTH_MINS * 60 : NULL;
session_set_cookie_params($expiryTime, parse_url(BASE_URL, PHP_URL_PATH));
if (session_id() == '') {
	session_name('JethroSess');
	session_start();
	upgrade_session_cookie();
}

if (defined('TIMEZONE') && constant('TIMEZONE')) {
	date_default_timezone_set(constant('TIMEZONE'));
	$res = $GLOBALS['db']->query('SET time_zone = "'.date('P').'"');
}

@ini_set('default_charset', 'UTF-8');
