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

/* Switches */
.switchForm {
	text-align: right;
}

/* Login Page */
.form-signin {
  max-width: 25rem;
  padding: 4rem 1rem 1rem;
}

/* Drawer */
.bmd-layout-drawer {
	color: @jethroLightText;
	background-color: @jethroDarkish;
}

.bmd-layout-drawer > header {
	background-color: @jethroDarkest;
}
.bmd-layout-drawer > header .account {
    display: flex;
    position: relative;
    flex-direction: row;
    align-items: center;
    width: 100%;
}

.bmd-layout-drawer > header .account .dropdown {
	margin-left: auto;
}

.bmd-layout-drawer > header .account .dropdown .dropdown-menu {
	font-size: .875rem;
}
.bmd-layout-drawer > header .account .dropdown .dropdown-menu .dropdown-item {
	flex-wrap: nowrap;
}

/* Content */
.bmd-layout-content {
	padding-top: .5rem;
	background-color: #f5f5f5;
}
