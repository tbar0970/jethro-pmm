/* Define our colours (may be overriden in CUSTOM_LESS_VARS for conf.php below) */

@jethroDarkest: #858A9B;
@jethroDarkish: #A6ACC2;
@jethroGrayish: #E8E8FF;
@jethroLight: #FFFFE0;
@jethroLightest: #FFFFF0;
@jethroLightText: #fff19b;
@jethroDarkText: #333333;
@jethroLinkColor: #647196;
@grayMid: #CCC;

<?php
/* Load any custom vars from conf.php */
$confFile = dirname(dirname(dirname(__FILE__))).'/conf.php';
if (is_readable($confFile)) {
	require_once $confFile;
	if (defined('CUSTOM_LESS_VARS')) echo CUSTOM_LESS_VARS;
}
?>

/* Login Page */
.form-signin {
  max-width: 25rem;
  padding: 4rem 1rem 1rem;
}
