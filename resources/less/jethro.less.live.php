<?php
require_once '../lessphp/lib/Less/Autoloader.php';

Less_Autoloader::register();
$parser = new Less_Parser();
ob_start();
include './jethro.less.php';
$less_css = ob_get_clean();
$parser->parse($less_css);

if (defined('TIMEZONE') && constant('TIMEZONE')) {                                                                                                           
	        date_default_timezone_set(constant('TIMEZONE'));  
}
$datetime = new DateTime();
$datetime->add(new DateInterval('P1D'));
$tsstring = gmdate('D, d M Y H:i:s ', $datetime->getTimestamp()) . 'GMT';
header('Content-Type: text/css');                                                                                                                            
header("Expires: " . $tsstring);
echo $parser->getCss();
?>
