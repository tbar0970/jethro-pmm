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

@tableBorderColour: rgba(0,0,0,.06);

@borderRadius: 4px;

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

/* Buttons */
.btn i.material-icons {
	font-size: inherit;
	padding-left: 0;
	padding-right: .3rem;
	margin-left: -.3rem;
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

/* Responsive Tables */
@media screen and (max-width: 768px) {
	.table-responsive-vertical thead, .table-responsive-vertical tfoot {display:none;}
	.table-responsive-vertical tbody tr {
		border-bottom: 1px solid @tableBorderColour;
	}
	.table-responsive-vertical tbody tr td {
		padding: .3rem;
    display: block;
    vertical-align: middle;
    text-align: right;
    border: 0;
		clear: both;
	}
	.table-responsive-vertical tbody tr td[data-title]:before {
	            content: attr(data-title);
	            float: left;
	            font-size: inherit;
	            font-weight: lighter;
	            color: grey;
	}
}
