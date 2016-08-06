<?php
if (file_exists(dirname(__FILE__).'/version.txt')) {
	define('JETHRO_VERSION', trim(file_get_contents(dirname(__FILE__).'/version.txt')));
} else {
	define('JETHRO_VERSION', 'DEV');
}

$path_sep = defined('PATH_SEPARATOR') ? PATH_SEPARATOR : ((FALSE === strpos($_ENV['OS'], 'Win')) ? ';' : ':');
set_include_path(ini_get('include_path').$path_sep.JETHRO_ROOT.$path_sep.JETHRO_ROOT.'/include/'.$path_sep.JETHRO_ROOT.'/db_objects/');

function __autoload($class_name) {
	 include_once strtolower($class_name) . '.class.php';
}

// set error level such that we cope with PHP versions before and after 5.3 when E_DEPRECATED was introduced.
$error_level = defined('E_DEPRECATED') ? (E_ALL & ~constant('E_DEPRECATED') & ~constant('E_STRICT')) : E_ALL;
error_reporting($error_level);
@ini_set('display_errors', 1);

session_set_cookie_params(NULL, parse_url(BASE_URL, PHP_URL_PATH));
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
require_once JETHRO_ROOT .'/include/jethrodb.php';
$GLOBALS['db'] =& new JethroDB(DSN, DB_USERNAME,DB_PASSWORD);

if (defined('TIMEZONE') && constant('TIMEZONE')) {
	date_default_timezone_set(constant('TIMEZONE'));
	$res = $GLOBALS['db']->query('SET time_zone = "'.date('P').'"');
	$res->closeCursor();
}

@ini_set('default_charset', 'UTF-8');
