<?php
require_once '../lessphp/lib/Less/Autoloader.php';

Less_Autoloader::register();

$parser = new Less_Parser();
ob_start();
include './jethro.less.php';
$less_css = ob_get_clean();
//echo $less_css;
$parser->parse($less_css);
echo $parser->getCss();
?>
