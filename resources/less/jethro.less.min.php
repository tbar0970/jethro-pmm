<?php
$productionrun = true;
require_once '../minify/src/Minify.php';
require_once '../minify/src/CSS.php';
require_once '../minify/src/JS.php';
require_once '../minify/src/Exception.php';
require_once '../path-converter/src/Converter.php';
require_once '../lessphp/lib/Less/Autoloader.php';

Less_Autoloader::register();

$parser = new Less_Parser();
ob_start();
include './jethro.less.php';
$less_css = ob_get_clean();
//echo $less_css;
$parser->parse($less_css);
$css = $parser->getCss();
use MatthiasMullie\Minify;
$minifier = new Minify\CSS($css);
echo $minifier->minify();
?>
