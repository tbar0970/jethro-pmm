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

@import "bootstrap.less";
@import "responsive.less";
@import "../css/jquery-ui.min.css";

/* Fix for less v2 and bootstrap 2 - see https://stackoverflow.com/questions/26628309/less-v2-does-not-compile-twitters-bootstrap-2-x */
#grid {
    .core  {
        .span(@gridColumns) {
            width: (@gridColumnWidth * @gridColumns) + (@gridGutterWidth * (@gridColumns - 1));
        }
    }
};

<?php
/* Load any custom vars from conf.php */
$confFile = dirname(dirname(dirname(__FILE__))).'/conf.php';
if (is_readable($confFile)) {
	require_once $confFile;
	if (defined('CUSTOM_LESS_VARS')) echo CUSTOM_LESS_VARS;
}
?>

/* Modify bootstrap vars */

@bodyBackground: @jethroLight;
@linkColor: @jethroLinkColor;
@tableBackgroundAccent: lighten(@jethroGrayish, 3);
@tableBackgroundHover: @jethroGrayish;
@navbarBackground: @jethroDarkest;
@navbarBackgroundHighlight: @jethroGrayish;
@navbarLinkColor: @jethroLightText;
@navbarLinkColorHover: @jethroDarkText;
@navbarLinkColorActive: @jethroDarkText;
@navbarLinkBackgroundHover: @jethroGrayish;
@navbarLinkBackgroundActive: @jethroGrayish;
@navbarCollapseWidth: 900px;
@dropdownBackground: @jethroDarkest;
@dropdownLinkColor: @jethroLightText;
@dropdownLinkColorHover: @jethroDarkText;
@dropdownLinkBackgroundHover: @jethroGrayish;



/*** GRADIENT ****/

#login-header,
.details-box h3,
#jethro-nav-background,
.table thead th, .table tfoot th,
.accordion-heading
{
	background-color: @jethroDarkish;
	background-image: -moz-linear-gradient(top, @jethroDarkish, @jethroDarkest);
	background-image: -webkit-gradient(linear, 0 0, 0 100%, from(@jethroDarkish), to(@jethroDarkest));
	background-image: -webkit-linear-gradient(top, @jethroDarkish, @jethroDarkest);
	background-image: -o-linear-gradient(top, @jethroDarkish, @jethroDarkest);
	background-image: linear-gradient(to bottom, @jethroDarkish, @jethroDarkest);
	background-repeat: repeat-x;
	filter: progid:DXImageTransform.Microsoft.gradient(startColorstr='#ffA6ACC2', endColorstr='#ff858A9B', GradientType=0);
}
.table thead th, .table tfoot th, .table thead th *, .table tfoot th * {
	/* use a colour that will contrast with the background */
	color: contrast(@jethroDarkish, @jethroDarkText, @jethroLightText) !important;
	font-weight: normal !important; /* bold makes the contrast too stark */
}

/* the styling used for focused textboxes, also applied to the "active" note when viewing a person */
.highlight {
	-webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 8px rgba(256, 0, 0, 0.9);
	-moz-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 8px rgba(256, 0, 0, 0.9);
	box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 8px rgba(256, 0, 0, 0.9);
}
.hovered, .hovered td, tr#selected td {
	background: #d3d6e1 !important;
}


/******************** LOGIN *******************/
#login-box {
	text-align: left;
	width: 30em;
	/*border: 1px solid;*/
	position: absolute;
	top: 20%;
	left: 50%;
	margin-left: -15em;
	padding: 0 0;
}
#login-header {
	z-index: -100;
	height: 24px;
	border: 1px solid transparent;
	padding: 10px 20px;
}
#login-body {
	padding: 20px;
}
#login-box h1 {
	margin: 0px;
	font-size: 20px;
	line-height: 30px;
	background: url(../img/jethro-white.png);
	background-repeat: no-repeat;
	background-position: left center;
	padding-left: 116px;

	height: 24px;
	white-space: nowrap;
	color: @grayLighter;
	font-weight: normal;
	text-align: right;
}
#login-box h1 span {
	display: none;
}
#login-box h3 {
	margin-bottom: 20px;
	margin-top: 0px;
}
#login-box hr {
	border-color: @jethroDarkText;
	margin: 2ex 0;
}
.login-box-label {
	width: 6em;
	text-align: left;
}
.login-box .controls {
	margin-left: 6em;
}
.a2hs-prompt {
	display: none; /* jethro.js will show it as appropriate */
	text-align: center;
	background: @jethroDarkest;
	color: white;
	margin: 20px;
	padding: 10px;
	border-radius: 5px;
	font-size: 85%;
	position: sticky; /* https://css-tricks.com/a-clever-sticky-footer-technique/ */
	top: 100vh;
}
@media (max-width: 640px) {
	body#login {
		overflow: hidden;
	}
	#login-box {
		position: static;
		border: 0px;
		height: 100%;
		width: 100%;
		margin: 0px;
		padding-top: 0;
		padding-bottom: 0;
	}
	#login-box h1 {
		font-size: 0.01em;
		text-indent: -9999px;
		text-align: left
	}
}
@media (max-height: 320px) {
	#login-box h3 {
		display: none;
	}
}


/********** OVERALL PAGE ************/

html, body {
	height: 100%;
}
body {
	text-align: center;
	margin: 0px;
	padding: 0px;
}
#jethro-nav, #jethro-nav-background {
	height: 80px;
	width: 100%;
}
#jethro-nav-background {
	-webkit-box-shadow: 1px 2px 6px rgba(0,0,0,.2);
	-moz-box-shadow: 1px 1px 6px rgba(0,0,0,.2);
	box-shadow: 1px 1px 6px rgba(0,0,0,.2);
}
#body {
	margin: 0px 10px;
	max-width: 100%;
	overflow: visible;
	z-index: 50;
	text-align: left;
}
#jethro-overall-width {
	display: inline-block;
	width: auto;
	text-align: left;
	max-width: 100%;
	overflow: visible;
}
@media (min-width: 1025px) {
	#jethro-overall-width {
		min-width: 1025px;
	}
}
@media (max-width: 1025px) {
	#jethro-overall-width {
		min-width: 100%;
	}
	#jethro-nav-toprow {
		padding-left: 10px !important;
	}
}
@media (max-width: 640px) {
	#body { margin: 0px 7px }
}
#jethro-nav-background {
	position: absolute;
	z-index: -100;
}

/*************** TOP NAV ****************/


#jethro-nav-toprow {
	height: 40px;
	padding-left: 10px;
}
#jethro-nav-toprow h1 {
	color: @grayLighter;
	font-size: 20px;
	line-height: 46px;
	font-weight: normal;
	text-shadow: 0 1px 0 @grayDark;
	display: table-cell;
	vertical-align: middle;
	height: 40px;
	position: absolute;
	margin-top: 0px;
	z-index: 888;
}
#jethro-public #jethro-nav-toprow h1 {
	line-height: 40px;
}
/* mobile back button */
#jethro-nav-toprow .icon-chevron-left {
	float: left;
	margin-top: 12px;
	margin-right: 3px;
}
#jethro-nav-toprow .brand {
	background: url(../img/jethro-white.png);
	background-repeat: no-repeat;
	background-position: 50% 50%;
	font-size: 0.01em;
	height: 40px;
	padding: 0;
	text-indent: -9999px;
	width: 106px;
	float: left;
	display: block;
	margin-right: 5px;
}
#jethro-nav .btn-navbar {
	display: none;
}
.user-detail > div {
	height: 40px;
	display: table-cell;
	vertical-align: middle;
	left: auto;
	right: 0;
	color: black;
	text-align: left;
}
/* members area only */
a#user-menu {
	color: @jethroLightText;
}
#user-detail-in-nav {
	display: none;
}
#user-detail-in-nav a.log-out {
	padding: 0px;
}
@media (max-width: 900px) {
	/* When there is not enough width for the full nav */
	#jethro-nav .btn-navbar {
		display: inline-block;
		height: 15px; /* makes it match the search box */
		margin-left: 0px;
	}
	.user-detail {
		display: none;
	}
	#user-detail-in-nav {
		display: inherit;
	}
	#jethro-nav, #jethro-nav-background {
		height: 40px;
	}
	#jethro-nav * {
		z-index: 9999;
	}
}

@media (max-width:480px) {
	/* hide the church name when there is no room */
	#jethro-nav h1 span {
		display: none;
	}
	/* but not in the public site */
	#jethro-public #jethro-nav h1 span {
		display: inline;
	}
	#jethro-nav-toprow .brand {
		top: 7px;
		z-index: 888;
		margin: 0px;
	}
}

.navbar .btn-navbar {
	margin-top: 5px;
}

@media (min-width: 900px) {
	/* dropdown styling adjustments */
	ul.nav {
		width: 100%;
	}
	ul.nav ul.dropdown-menu {
		padding-top: 0px;
	}
	ul.nav ul.dropdown-menu a {
		padding: 8px 10px 8px 15px;  /* reduce bootstrap padding so it lines up with the top nav item */
	}

	/* make menus open on hover */
	.navbar .nav li.dropdown:hover > ul.dropdown-menu{
		display: block;
	}
	/* make left border of dropdown align better with parent item */
	.navbar .nav li ul {
		margin-left: -1px;
	}
	/* make the top-level nav item highlight when I hover one of its subnav options */
	.navbar .nav li.dropdown:hover, .navbar .nav li.active > a {
		color: @navbarLinkColorActive;
		text-decoration: none;
		background-color: @navbarLinkBackgroundActive;
		.box-shadow(inset 0 3px 8px rgba(0,0,0,.125));
	}
	.navbar .nav li.dropdown:hover > a {
		color: @jethroDarkText !important;
	}
	.navbar .nav li.dropdown:hover .caret {
		border-bottom-color: @jethroDarkText !important;
		border-top-color: @jethroDarkText !important;
	}
}
@media (max-width: 900px) {
	/* Collapsed nav */

	.nav-collapse .dropdown-menu li a {
		/* not sure why bootstrap fails to give this to the first submenu item */
		margin-bottom: 2px;
	}
	.nav-collapse .nav > li > a,
	.nav-collapse .dropdown-menu a {
		background-color: @jethroDarkish;
		font-weight: normal !important;		
	}

}

/* current submenu  item */
.dropdown-menu > .active > a, .dropdown-menu > .active > a:hover, .dropdown-menu > .active > a:focus,
.navbar .nav li.dropdown a:hover,
.user-header
{
		color: @navbarLinkColorActive;
		text-decoration: none;
		background: @navbarLinkBackgroundActive;
		.box-shadow(inset 0 3px 8px rgba(0,0,0,.125));
}


.nav-collapse ul.nav {
	background-color: @jethroDarkest !important;
}
.nav-collapse .nav > li > a,
.nav-collapse .dropdown-menu a {
	text-shadow:none !important;
}

.navbar .nav  a:hover {
	text-shadow: none !important;
}

.navbar .nav > li > .dropdown-menu:before,
.navbar .nav > li > .dropdown-menu:after {
	display: none;
}
.navbar .nav .dropdown-menu {
	border-radius: 0 0 6px 6px;
	margin-top: 0px;
	border-top: 0px;
}

.user-detail {
	float: right;
	white-space: nowrap;
}
.user-detail .dropdown-menu {
	min-width: 90px !important;
	margin-top: 8px;
	border-radius: 5px 0 5px 5px;
	padding-top: 0px;
	border-top-width: 0px;
}
#body .dropdown-menu li a {
	text-decoration: none !important;
	color: @jethroLightText !important;
}
.user-detail li a:hover .btn-link, .user-detail li a:hover, #body .dropdown-menu li a:hover, .user-header a  {
	color: @grayDark !important;
}
li.user-header {
	margin-bottom: 4px;
}
.user-detail .restrictions {
	color: #eee;
	background-color: @grayMid;
	font-weight: normal;
	margin-top: -4px;
	margin-bottom: 4px;
	padding: 0px 20px;
	font-size: 80%;
}
.user-detail small {
	font-size: 80%;
	color: #999;
	padding-left: 1px;
}
form.global-search {
	margin: 0px 5px 0px 0px;
	line-height: 38px;
}
form.global-search span.input-append {
	margin: 0px;
}
form.global-search input[type=text] {
	width: 8ex;
	transition: width 0.3s ease-in-out;	
}
form.global-search input[type=text]:focus {
	z-index: 999;
	width: 25ex;
}
	

/**************** HEADINGS ********************/
h1 {
	margin: 15px 0 10px 0;
}
h1, h2 {
  font-size: 20px;
  line-height: 24px;
}
h1 *, h2 * {
	line-height: 24px;
}
h3 {
  font-size: 17.5px;
  line-height: 1.4;
  font-weight: normal;
  border-bottom: 1px solid;
  margin-top: 20px;
}
h4 {
  font-size: 14px;
  margin-bottom: 6px;
  margin-top: 14px;
}
h4 strong {
	text-decoration: underline; /* ?? */
}
h5,h6 {
  font-size: 14px;
  margin-bottom: 0px;
}

#body h4:first-child, #body h5:first-child {
	margin-top: 0;
}

@media (max-width: 480px) {
	h1, h1 {
		font-size: 20px;
	}
}

/*********** GENERAL ********************/

#body .cke_bottom .cke_toolbar {
	float: right !important;
}
hr, table.object-summary tr.divider-before > td, table.object-summary tr.divider-before > th {
	border-top: 1px solid @tableBorder !important;
}
.table {
	border-bottom: 1px solid @tableBorder;
}
abbr[title] {
	border-bottom:0px !important;
}
input[type=image] {
	height: auto !important;
	padding: 0px !important;
}
p.text, .text * {
	max-width: 800px;
}
@media (max-width: 800px) {
	p {
		max-width: 100%;
	}
}
.well p:last-child {
	margin-bottom: 0;
}
#jethro-public p, #jethro-public #body li { 
	max-width: 45em; 
	margin-top: 0.75ex; 
	margin-bottom: 0.75ex; 
	line-height: 1.4
}
ul {
	margin-bottom: 2px;
}
.modal {
	z-index: 9999;
}
@media (min-width: 500px) {
	.modal-wide {
		width: 70% !important;
		margin-left: -35%;
	}
}

.modal-backdrop {
	z-index: 8888;
	opacity: 0.4;
}
#body a:not(.label), .modal a, .clickable, button.btn-link, input.btn-link {
	text-decoration: underline;
	color: @linkColor;
}
#body a.btn, .modal a.btn {
	color: @jethroDarkText;
	text-decoration: none;
}
#body a.btn-primary, .modal a.btn-primary, #body a.btn-danger, .modal a.btn-danger {
	color: white;
}
#body .pagination a {
	text-decoration: none;
}
#body .pagination {
	margin-top: 5px;
	margin-bottom: 5px;
}
form.min {
	display: inline;
	margin: 0px;
	padding: 0px;
}
#body form.min button.btn-lnk, #body form.min label {
	display: inline !important;
	margin: 0px !important;
}
#body a [class^="icon-"], #body a [class*=" icon-"],
#body button [class^="icon-"], #body button [class*=" icon-"],
#body .clickable [class^="icon-"], #body .clickable [class*=" icon-"] {
	padding-right: 3px;
}
.icon-email {
	color: black;
	font-weight: bold;
}
.icon-copy {
	background: url(../img/copy-icon.svg) no-repeat !important;
	background-size: cover;
	height: 14px;
	width: 14px;
}
.icon-rss {
	background: url(../img/rss-icon.svg) no-repeat !important;
	background-size: cover;
	height: 14px;
	width: 14px;
}
#body .btn-mini [class*="icon-"] {
	padding-right: 0px !important;
}
.icon-phone {
	background: url(../img/phone.png) no-repeat !important;
}
.modal form {
	margin: 0px;
}
.well {
	background-color: @jethroGrayish;
}
#body .table-condensed select, #body .table-condensed input[type=text],
#body .table select,
#body label select, #body label input[type=text] {
	margin: 0px;
}
#body .valign-middle, #body table.valign-middle td, #body table.valign-middle th {
	vertical-align: middle;
}
#body .valign-top, #body table.valign-top td {
	vertical-align: top !important;
}

tbody th {
	text-align: left;
}
#body thead th, #body tfoot th {
	text-align: left;
}
#body td {
	vertical-align: top;
}
th label {
	font-weight: bold;
	margin: 0;
}
th label input[type="checkbox"] {
	margin: 0;
}
input[type="radio"] {
	margin-top: 1px !important;
}
#body table.table-auto-width {
	width: auto !important;
}
#body table.table-full-width {
	width: 100%;
}
#body table.table-min-width {
	width: auto !important;
	min-width: 50ex;
}
#body .table-no-borders td, #body .table-no-borders th {
	border-width: 0px !important;
}
tr:first-child > td > .move-row-up,
tr:last-child > td > .move-row-down {
  visibility: hidden;
}
/* nested tables - see list of family members within single person view */
.table td table {
	width: 100%;
	background: transparent !important;
}
.table td table tr:first-child td {
	border-top: 0px;
}
#body table.no-borders, #body table.no-borders td, #body table.no-borders th {
	border-width: 0px !important;
}
#body .no-padding td, #body .no-padding th {
	padding: 0px !important;
}
/* WIDTH OF "NARROW" COLS IS IMPLEMENTED BY JETHRO.JS IN A SPECIAL WAY*/
td.narrow, th.narrow, .object-summary th {
	white-space: nowrap;
}
/* this is used when the problem that the .narrow JS deals with is not applicable */
td.narrow-gentle {
	width: 1%;
	white-space: nowrap;
}
.fill-me * {
	width: 100%;
	margin-right: 0;
}

.nowrap {
	white-space: nowrap;
}
.inline {
	display: inline;
}
.clickable, table.clickable-rows td, table.clickable-rows th, img.icon {
	cursor: pointer;
}
.insert-row-below {
	color: #8bef26;
	font-size: 18px;
	text-decoration: none;
	font-weight: bold;
	position: relative;
	top: 3ex;
	cursor: pointer;
}
.insert-row-below:before {
	content: "+";
}
.cursor-move {
	cursor: move;
}
tr:last-child .insert-row-below {
	display: none;
}
.smallprint, .help-inline, .help-block, .smallprint code {
	font-size: 12px;
	line-height: 130%;
	margin-left: 0;
	margin-top: 1px;
	padding: 0;
	font-style: italic;
	color: @gray;
}

#body .soft, .soft { /* low-key links */
	font-size: 85%;
	padding-top: 1px;
	color: #aaa !important;
}
#body .table thead th .soft, #body .table tfoot th .soft {
	color: #ccc !important;
}
#body a.pull-right {
	padding-right: 5px;
}
.custom-field-tooltip {
	background: @jethroGrayish !important;
	border-radius: 4px;
	padding: 3px 6px;
	display: none;
	margin-top: 2px;
	width: 300px;
	font-style: italic;
}
#body table.custom-field-tooltip td {
	background: @grayLighter !important;
	border: 1px solid !important;
}
table.custom-field-tooltip {
	margin: 0px;
	border: 1px solid;
}
.compact-2col {
	max-width: 800px;
}
.compact-2col label, .compact-2col div  {
	float: left;
	width: 47%;
	margin-right: 2.5%;
	-webkit-box-sizing: border-box;
	-moz-box-sizing: border-box;
	box-sizing: border-box;
}
.compact-2col>div>input:first-child {
	width: 92%;
}
.compact-2col>div>select {
	width: 100%;
}
.compact-2col label {
	margin-top: 8px;
	margin-bottom: 0px;
}
.compact-2col label.fullwidth, .compact-2col div.fullwidth {
	width: 100% !important;
}
/* in the add-family page */
.family-member-box {
	border: 1px solid @jethroDarkest;;
	border-radius: 5px;
	padding: 0px 5px 5px 5px;
	overflow: auto;
	height: auto;
	background: @jethroLightest;
	margin-bottom: 10px;
}
.align-right {
	text-align: right !important;
}
.width-really-auto {
	display: table-cell;
}
#body .align-centre, #body .center {
	text-align: center;
}
.right {
	text-align: right !important;
}
select, input, textarea,div.editor {
	width: auto;
	max-width: 97%;
}
.full-width-input {
	width: 99.5%;
	-webkit-box-sizing: border-box;
	-moz-box-sizing: border-box;
	box-sizing: border-box;
}
table tbody th {
	vertical-align: top;
	padding-right: 1em;
}

table.object-summary > tbody > tr > th {
	text-align: right;
}

table.object-summary td, table.object-summary th {
	padding: 4px;
	border-width: 0px;
}
.object-summary h4 {
	color: @grayLight;
	margin: 0px;
	margin-top: 2px;
	border-width: 0px !important;
}

/* when an object summary table has a table in its data cell, make it line up */
table.object-summary>tbody>tr>td>table>tbody>tr:first-child td {
	padding-top: 0px !important;
}
#body tr.archived td, #body tr.archived a, #body tr.archived .btn-link, #body tr.archived input {
	color: @grayLight;
}
#body tr.archived:hover td, #body tr.archived:hover td a, #body tr.archived:hover .btn-link {
	color: inherit !important;
}
#body .alert {
	margin-top: 10px;
}
.form-horizontal .control-group {
	margin-bottom: 8px !important;
}
.form-inline {
	line-height: 32px;
}
.controls-text {
	padding-top: 5px; /* to vertically align with control label */
}
.control-group h4 {
	width: 160px;
	text-align: right;
	margin-top: 0px;
}
.control-group .alert {
	margin: 0px !important;
}

.day-box {
	width: 4.5ex !important;
}
.year-box {
	width: 6.5ex !important;
}

input[type=number]::-webkit-outer-spin-button, 
input[type=number]::-webkit-inner-spin-button {
    -webkit-appearance: inner-spin-button !important;
	opacity: 1;	
}

@media (min-width: 1px) {
	/* support for media queries roughly equivalent for support for such things as the placeholder attr */
	.msie-only {
		display: none;
	}
}
input.btn {
	height: 30px !important; /* fixing https://github.com/twitter/bootstrap/issues/2985 */
}
input.btn-link, button.btn-link {
	padding: 0px;
}
.indent-left {
	margin-left: 40px;
}
.margin-below {
	margin-bottom: 10px;
}
@media (max-width: 480px) {
	.indent-left {
		margin-left: 15px;
	}
	.fullwidth-phone {
		width: 99%;
		float: none !important;
		margin-left: 0px !important;
	}
	.fullwidth-phone .input-prepend, .fullwidth-phone .input-append {
		width: 99%;
		box-sizing: border-box;
		display: inline-grid !important;
	}
	.fullwidth-phone .input-append {
		grid-template-columns: 1fr min-content;
	}
}
.input-prepend .add-on {
	margin-left: 1px; /* work around a bug where the left margin gets cut off on homepage */
}

.input-prepend.fullwidth, .input-append.fullwidth {
	width: 99%;
	box-sizing: border-box;
	display: inline-grid !important;
}
.input-append *, .input-prepend * {
	grid-row: 1;
}
.input-append.fullwidth {
	grid-template-columns: 1fr min-content;
}
.input-prepend.fullwidth {
	grid-template-columns: min-content 1fr;
}

.input-append input {
	min-width: 10px !important;
}

.submit-in-progress {
	background-image: url(../img/loading-spinner.gif) !important;
	background-size: 20px !important;
	background-repeat: no-repeat;
	background-repeat: none;
	background-position: right !important;
	padding-right: 30px;
}

#body h1 small, #body h2 small {
	font-size: 14px;
}

.dropdown-menu .divider {
	margin: 3px 0px;
	border-bottom: 0px;
}

/************* REPORTS **************/
table.query-results {
	margin-bottom: 5px; /*  so the X persons listed text can snuggle underneath */
}
table.query-results tfoot * {
	border-bottom: 0px !important;
}
.report-summary, .report-summary * {
	color: @grayLight !important;
}

.date-range-picker .dropdown-menu {
	background: white; 
	padding: 10px; 
	min-width: 500px;
	margin-top: 8px;
}
.date-range-picker td {
	border-width: 0px !important;
}
.date-range-picker .relative input[type=number] {
	width: 4.5ex !important;
}




/************** USER ACCOUNT ****************/
.bitmask-boxes div {
	padding-right: 20px;
	display: inline-block;
}
.bitmask-boxes {
	overflow: auto;
}
.datefield-rule-period {
	margin-top: 7px;
}

/************* VIEW GROUP **************/

.group-members-links {
	overflow: auto; /* clearfix */
	margin-bottom: 1ex;
}
.group-details-links a,
.group-members-links div {
	float: right;
	margin-left: 2em;
}
.group-members-links .archived-link {
	float: left;
	margin-left: 0px;
}
@media (max-width: 640px) {
	/* drop the show-archived and edit-statuses links */
	.group-members-links .archived-link, .group-members-links .edit-status-link {
		display: none;
	}
}

/************* VIEW PERSON ************/

.view-person h4 {
	border-bottom: 1px solid #ddd;
}
.person-details {
	float: left;
	margin-right: 15px;
}
img.person-photo {
	width: 200px;
	float: left;
	border-radius: 5px;
	border: 1px solid @jethroDarkest;
}
.details-box {
	box-sizing: border-box;
	-webkit-box-sizing: border-box;
	-moz-box-sizing: border-box;
	border: 1px solid @jethroDarkest;
	padding: 15px 10px 10px 10px;
	position: relative;
	background: @jethroLight;
	border-radius: 5px;
	margin-bottom: 15px;
}
.view-person .details-box {
	width: 500px;
}
@media(min-width:1100px) {
	.person-details {
		width: 860px;
		margin-right: 0px;
	}
	/* person details 380 + family details 380 + photo 200px wide */
	.view-person .details-box {
		width: 415px;
		overflow: auto;
		float: left;
		margin-right: 15px;
	}
	.view-person img.person-photo {
		float: right;
	}
}

@media(max-width:600px) {
	.person-details, .person-photo, .view-person .details-box {
		float: none;
		width: 100% !important;
		margin-left: 0px;
	}
	.details-box, img.person-photo {
		margin-bottom: 7px !important;
	}
}

.details-box table {
	width: 100%;
}

.details-box h3 {
	margin: -15px -10px 10px -10px;
	padding-left: 15px;
	white-space: nowrap;
	text-shadow: 0 1px 0 @jethroDarkText;
	font-size: 14px;
	font-weight: bold;
	color: white  !important;
	line-height: 30px;
}
.accordion .details-box {
	padding: 8px;
	max-width: 100%;
}
.accordion .details-box h3 {
	/* prevent the inside heading being bigger than the accordion heading */
	font-size: 14px;
	font-weight: bold;
	padding-top: 2px;
	padding-bottom: 2px;
	margin: -8px -8px 8px -8px;
}
.details-box .header-link {
	position: absolute;
	right: 10px;
}
.details-box .header-link, .details-box .header-link a  {
	line-height: 30px;
	color: white  !important;
	margin-top: -15px;
}
.accordion .details-box .header-link {
	margin-top: -8px;
}
.details-box .table {
	margin-bottom: 5px;
}






/* when editing (I think) */
.person-photo-container {
	width: 100%;
	position: relative;
}
.person-photo-container img {
	position: absolute;
	right: 0px;
	width: 170px;
}

.photo-tools:has(.remove-photo input:checked) .replace-photo, .photo-tools:has(.remove-photo input:checked) img, .photo-tools:has(.remove-photo input:checked) .new-photo-name  {
	display: none !important;
}
.photo-tools input[type=file] {
	display: none;
}
.photo-tools {
	height: 20px;
	line-height: 20px;
	padding: 4px 0px;
}
.photo-tools input.new-photo-name {
	display: none;
	margin-top: -4px;
	margin-right: 12px;
}
.photo-tools img, .photo-tools label, .photo-tools {
	margin: 0 12px 0 0 !important;
	display: inline-block;
	height: 20px;

}

/************** VIEW FAMILY ****************/
.details-box form {
	margin: 0px;
}
.family-details {
	margin-right: 15px;
	float: left;
}
.family-details .details-box {
	width: 630px;
}
.family-members-container {
	display: grid;
	grid-template-columns: 1fr 1fr; 
	column-gap: 10px;
	row-gap: 10px;
	box-sizing: border-box;
	margin-bottom: 10px;
 }
.family-member {
	box-sizing: border-box;
	border: 1px solid @jethroDarkest;
	background-color: @jethroLightest;
	padding: 5px;
	border-radius: 5px;
	height: 72px;
	overflow: hidden;
	color: @jethroDarkText;
}
.family-member.archived {
	color: @grayLight;
}
.family-member:hover {
	background-color: @jethroGrayish;
}
.family-member label {
	float: right;
	margin: -5px -5px 0 0;
	padding: 5px 5px 0 0;
	display: block;
	height: 20px;
	width: 20px;
}
.family-members-container a, .family-members-container a * {
	text-decoration: none !important;
}
.family-member img {
	margin: -5px 5px -5px -5px;
	width: 70px;
	float: left;
	border-top-left-radius: 5px;
	border-bottom-left-radius: 5px;
}
@media (max-width: 600px) {
	.family-details .details-box {
		width: 100%;
	}
	.family-members-container {
		grid-template-columns: 1fr;
	}
}

/**************** hOME PAGE *****************/

.homepage h3 {
	margin-top: 20px;
	overflow: hidden;
}
.homepage h3 span {
	white-space: nowrap;
}
.homepage {
	display: flex;
	flex-flow: row;
	flex-wrap: wrap;
	column-gap: 15px;
}
.homepage-box {
	flex-grow: 1;
	min-width: 200px;
	order: 5;
}
.homepage-box form {
	margin-bottom: 0px;
}
.homepage-search span.input-append {
	margin-bottom: 1px;
}

.homepage-search-options, .homepage-search-options *  {
	font-size: 11px;
	margin: 0px;
	max-width: 100%;
}
.homepage-search-options details {
	margin-left: 2px;
}
.homepage-search-options details div {
	display: flex;
	flex-flow: row;
	flex-wrap: wrap;
}
.homepage-search-options details label {
	padding-left: 1em;
}
@media (min-width: 740px) {
	.homepage .search-forms {
		order: 10; /* make it last */
		max-width: 400px;
	}
	.homepage .homepage-search-options details  {
		max-width: 250px;
	}
	.homepage .homepage-search-options details label {
		width: 28%;
	}
}
@media (max-width: 739px) {
	.homepage .search-forms {
		/* make it first and 100% width */
		width: 100%;
		clear: both;
		order: 1;
	}
}

/*************** MEMBERS HOME PAGE ******************/
.member-homepage-box {
	float: left;
	box-sizing: border-box;
	width: 100%;
}

@media (min-width: 650px) {
	.member-homepage-box {
		width: 30%;
		margin-right: 3%;
	}
	.member-homepage-box.family {
		width: 100%;
	}
}

@media (min-width: 800px) {
	.member-homepage-container {
		display: grid;
		grid-template-columns: 1fr 2fr; 
		grid-template-rows: auto auto auto;
		column-gap: 30px;
	}
	.member-homepage-smalls .member-homepage-box {
		width: 100%;
		float: none;
		position: relative;
	}
	.member-homepage-box {
		margin-bottom: 30px;
		position: relative;  /* to constrain the floating photo */
	}
}

.member-homepage-box img.family-photo {
	float: right !important;
	position: absolute;
	height: 150px;
	right: 5px;
	margin-bottom: 5px;
	border-radius: 5px;
	border: 1px solid @jethroDarkest;
}
@media (max-width: 440px) {
	.member-homepage-box img.family-photo {
		float: none !important;
		position: static;
	}
}

/*************** PERSON LIST IN MEMBER INTERFACE *************/
.member-homepage-box table {
	margin-bottom: 5px !important;
}
#member-list {
	max-width: 110ex;
	margin-left: 0px;
	margin-top: 15px;
}
#member-list form {
	margin-bottom: 10px;
}
#member-list .row-fluid [class*="span"] {
	min-height: 1px;
}

#member-list .member-row {
	margin-bottom: 5px;
}
#member-list .family-row {
	margin-bottom: 10px;
}
#member-list img.family {
	float: right;
	width: 150px;
	border-radius: 5px;
	border: 1px solid @jethroDarkest;
	margin-left: 10px;
}
#member-list div.member-family-details {
	margin: 5px 0px;
}
#member-list div.member-family-contents {
	margin-right: 152px;
}

.member-family-members {
	display: grid;
	grid-template-columns: 1fr 1fr; 
	column-gap: 10px;
	row-gap: 10px;
}
.member-family-members .family-member {

}
.member-family-members .family-member div {
	margin-left: 73px;
	position: absolute;
	overflow: visible;
}
#member-list h3 {
	clear: both;
	margin-bottom: 5px;
	margin-top: 15px;
}

@media (max-width: 900px) {
	.member-family-members {
		grid-template-columns: 1fr;
	}
}
@media (max-width: 440px) {
	#member-list img.family {
		float: none;
		max-height: 330px;
		margin: 0px 0px 5px 0px;
	}
	#member-list div.member-family-contents {
		margin-right: 0;
	}
}





/************** BULK ACTION ****************/
.bulk-action {
	display: none;
}
.bulk-action.well {
	margin-top: 15px;
}

/************* LISTINGS ****************/
@media (max-width: 640px) {
	#body td.action-cell a, #body td.action-cell button, #body td.action-cell .clickable, .link-collapse {
		text-indent: -9999px;
		text-decoration: none;
		font-size: 1px;
		display: inline-block;
		width: 20px;
		height: 20px !important;
		overflow: hidden;
		white-space: nowrap;
		padding: 0px;
	}
	#body td.action-cell form {
		display: inline-block !important;
		width: 20px;
		height: 20px !important;
		padding: 0px !important;
	}
	#body td.action-cell form * {
		position: absolute;
	}
	#body td.action-cell a i, #body td.action-cell button i, #body td.action-cell .clickable i, .link-collapse i {
		display: block;
		margin-top: 3px;
	}
}

/* widgets at the top of "list all" pages */
.list-all-controls {
	line-height: 30px; 
	min-height: 30px
}
.list-all-controls form.pull-right {
	margin-left: 8px;
}
.list-all-controls p {
	margin-bottom: 0px;
}

/* TAB AND ACCORION OVERRIDES */
ul.nav-tabs {
	margin-bottom: 0px;
}
.nav-tabs a {
	outline: 0;
	text-decoration: none !important;
}
.tab-content {
	border: 1px solid #ddd;
	border-top-width: 0px;
	padding: 15px;
	border-radius: 0px 0px 5px 5px;
}
.preview-pane, .tab-content, .nav-tabs > .active > a, .nav-tabs > .active > a:hover, .nav-tabs > .active > a:focus {
	background: @jethroLightest; /* lighter yellow */
}
#body .accordion-heading a {
	color: white !important;
	font-weight: bold;
	text-decoration: none;
	text-shadow: 0 1px 0 @jethroDarkText;
}
#body .accordion-inner {
	padding: 7px;
}


/*********** ROSTERS ***********/
#body table.roster {
	-webkit-box-shadow: 3px 3px 10px rgba(0,0,0,.2);
	-moz-box-shadow: 3px 3px 10px rgba(0,0,0,.2);
	box-shadow: 3px 3px 10px rgba(0,0,0,.2);
	width: auto;
	border-collapse: separate !important; /* need this for stick header's borders to show correctly */
	border-spacing: 0;
	border-width:  2px 1px 1px 2px !important; /*top left borders on table; bottom right borders on cells. */ 
	border-style: solid !important;
	border-color: @jethroDarkest;
}  
#body table.roster td, #body table.roster th {
	border-color: @jethroDarkest;
	border-width:  0px 1px 1px 0px ;
	border-style: solid !important;
	border-color: @jethroDarkest;
	padding: 4px;
}
#body table.roster thead  {
	position: sticky;
	top: 0;
	box-shadow: 0 3px 3px -1px rgba(0, 0, 0, 0.4);
	z-index: 99;
}
#body table.roster thead th {
	z-index: 99;
	background-image: none;
	filter: none;
	background-color: @jethroDarkish;
}
#body table.roster>tbody>tr:first-child>td, #body table.roster>tbody>tr:first-child>th {
	padding-top: 7px !important; /* so the drop shadow doesn't make the row look too skinny */
}
#body table.roster td, #body table.roster tbody th {
	background: @jethroLightest; /* lighter yellow */
}
#body table.roster tr.roster-next td, #body table.roster tr.roster-next th {
	background: @tableBackgroundHover !important;
}

.thick-left-border {
	border-left: 2px solid @jethroDarkest !important;
}
#body table.roster thead th.roster-date {
	text-align: center;
	position: sticky;
	left: 0;
}
#body table.roster tbody th.roster-date {
	text-align: right;
	position: sticky;
	left: 0;
	z-index: 1;
	white-space: nowrap;
}
#body table.roster th.roster-date .smallprint {
	font-weight: normal;
	line-height: 1.0;
}
#body table.roster a {
	white-space: nowrap;
}
#body table.roster ul.multi-person-finder {
	margin-bottom: 0px;
	margin-top: 0px;
}
#body table.roster ul.multi-person-finder li {
	padding-top: 2px;
	padding-bottom: 2px;
	margin-bottom: 3px;
	line-height: 1em;
}
#body table.roster input {
	margin-bottom: 0px;
	margin-top: 0px;
	padding: 1px 4px;
	height: 18px;
	line-height: 18px
}
#body table.roster *.clash, #body table.roster *.error {
	border: 1px solid red;
}

#body table.roster select {
	padding: 1px 2px;
	height: 22px;
	margin-bottom: 4px;
	margin-top: 0px;
}
table.roster select.unlisted-allocee, #body table.roster select option.unlisted-allocee {
	color: #e68a00;
}
table.roster select.unlisted-allocee option {
	color: @jethroDarkText;
}

table.roster input.clash, table.roster select.clash {
	border-color: red;
}
#body .rosteree-highlighted {
	background: #ccffcc;
	outline: 3px solid #ccffcc;
}
#body table.roster div.service-field-summary, #body table.roster div[class^='service-field-bible'] {
	max-width: 22ex !important;
}

/****** SERVICE PROGRAM ****/

table.service-program td.left-tools, table.service-program td.service {
	border-right-width: 0px !important;
}
table.service-program td.right-tools, table.service-program td.service  {
	border-left-width: 0px !important;
}
#body table.service-program td.left-tools, #body table.service-program td.right-tools {
	padding: 0px !important;
	height: 100%;
	width: 3ex /* NB */;
	text-align: center;
}
#body table.service-program > tbody > tr > td.service-date {
	white-space: nowrap;
	text-align: center;
	padding-left: 2px;
	padding-right: 2px;
	text-align: center;
	width: 11ex;
}

#body table.service-program .pull-right {
	padding-right: 0px;
	margin-left: 5px;
}

/* service details in services-list-all and within rosters */
/* these styles are replicated in call_display_roster.class.php */
#body table.roster p {
	line-height: 1.1;
	margin: 1.5px 0px;
	padding: 1px;
	max-width: 20em;
}
#body table.roster p.title {
	font-style: italic;
	font-size: 105%;
}
#body table.roster p.bible {
	color: @jethroLinkColor;
	font-weight: 400;
}
#body table.roster p.bible strong {
	color: @jethroLinkColor;
	font-weight: 600;
}
#body table.roster p.notes {
	font-size: 80%;
	color: #666;
	font-style: italic;
}



/* service-details is for edit mode */
table.service-details td, table.service-details th {
	background: none !important;
	padding: 1px 2px !important;
	border: 0px !important;
	font-weight: normal !important;
}
table.service-details th {
	width: 6.4ex; /* NB */
	text-align: right;
	font-size: 85%;
}
table.service-details td, td.service-details {
	width: 28ex; /* NB */
}

#body table.service-program td.service-details:hover {
	background-color: #f0f0e8 !important;
}
#body table.service-program td small, .service-details-inline small {
	color: #aaa !important;
	font-size: 75%;
	line-height: 0.5em;
}
#body table.service-details td table td {
	/* bible readings */
	border-bottom: 1px solid #ddd !important;
	border-top: 0px !important;;
	border-left: 0px !important;
	border-right: 0px !important;
	vertical-align: middle;
	padding-left: 0px !important;
}
#body table.service-details input.bible-ref {
	width: 95%;
}
#body table.service-details td table td.bible-options {
	white-space: nowrap;
	padding-left: 5px !important;
	width: 12ex;
}
#body table.service-details td.format input {
	width: 24.5ex; /* NB */
}
#body table.service-details td.topic input {
	width: 99%;
}
#body table.service-details td.format i.got-notes {
	background-color: #bbffbb;
  -webkit-box-shadow: 2px 2px 2px #88aa88;
  -moz-box-shadow: 2px 2px 2px #88aa88;
  box-shadow: 2px 2px 2px #88aa88;
  border-radius: 3px;
}
table.service-details td table td label {
	padding: 0px !important;
	display: inline !important;
	-webkit-user-select: none;  /* Chrome all / Safari all */
	-moz-user-select: none;     /* Firefox all */
	-ms-user-select: none;      /* IE 10+ */
	user-select: none;
}
table.service-details td table td input {
	margin: 0px 3px 0px 0px !important;
}
#body table.service-program tr.insert-space td {
	text-align: center;
	padding: 0px !important;
	height: 12px !important;
	line-height: 12px !important;
}
#body table.service-program tr.insert-space td button {
	height: 10px !important;
	width: 16px !important;
	background-image: url(../img/expand_up_down_green_small.png);
	background-color: transparent !important;
	border: 0px !important;
	margin: 0px !important;
	padding: 0px !important;
}

#body .service-program .notes-icon {
	height: 12px;
	width: 12px;
	margin: 2px;
	vertical-align: bottom
}
#body .service-program select {
	margin-bottom: 0px !important;
}


/*********** NOTES **************/
.notes-history-entry small {
	display: inline;
}
.notes-history-entry.well {
	padding: 20px 20px 10px 20px;
	max-width: 40em;
}
.notes-history-entry.well > [class^="icon-"], .notes-history-entry.well > [class*=" icon-"]  {
	position: absolute;
	margin-left: 1px;
}
#body .notes-history-entry blockquote {
	margin-bottom: 10px;
	margin-left: 1.5em;
}
#body .notes-history-entry p {
	margin: 0px;
	font-size: 14px; /* override bootstrap special blockquote size */
	line-height: 1.3em;
	margin-bottom: 4px;
}
.notes-history-entry p.subject {
	font-weight: bold;
	border: 0px;
	margin: 0px;
}

.notes-history-entry .author {
	font-style: italic;
	font-size: 0.8em;
	line-height: 1.1em;
}
.notes-history-entry h4 {
	margin: -10px 0px 7px 0px;
}
.notes-history-entry h4.note-update {
	margin-left: 45px;
	color: @grayLight;
	border-bottom: 1px solid @jethroDarkish;
	margin-top: 15px;
}
.notes-history-entry .comments {
	margin-left: 25px;
	margin-top: 15px;
}

/********* ATTENDANCE AND COLOURED RADIO BUTTONS **********/

	div.radio-button-group {
		margin: 3px;
		overflow: hidden;
		white-space: nowrap !important;

		-webkit-touch-callout: none;
		-webkit-user-select: none;
		-khtml-user-select: none;
		-moz-user-select: none;
		-ms-user-select: none;
		user-select: none;
	}

	div.radio-button-group.vertical {
		white-space: normal !important;
		width: 40px;
	}


	.radio-button-group div {
		background-image: none;
		margin: 2px;
		-webkit-touch-callout: none;
		-webkit-user-select: none;
		-khtml-user-select: none;
		-moz-user-select: none;
		-ms-user-select: none;
		user-select: none;
	}
	.radio-button-group div.active {
		border: 2px solid @jethroDarkText;
		margin: 0px !important;
		color: white;
		font-weight: bold;
		text-shadow: none;
	}
	.radio-button-group input {
		position: absolute;
		top:-9999px;
	}
	.radio-button-group div.value-present, .attendance-stats .present *, th.present {
		background-color: #A9C7A9 !important;
		background-image: none !important;
	}
	.radio-button-group div.value-present.active, td.present {
		background-color: #17A317 !important;
	}
	.radio-button-group div.value-absent, .attendance-stats .absent *, th.absent {
		background-color: #BD8986 !important;
		background-image: none !important;
	}
	.radio-button-group div.value-absent.active, td.absent {
		background-color: #bd362f !important;
	}
	.radio-button-group div.value-unknown {
		background-color: #CFA66C;
	}
	.radio-button-group div.value-unknown.active, td.unknown {
		background-color: #EA8B06 !important;
	}

	td.present, td.absent, td.unknown {
		text-align: center;
	}
	td.disabled {
		background: #aaa;
	}

	table.attendance-stats td {
		text-align: right;
		width: 6ex;
		color: @jethroDarkText !important;
	}

	#body .attendance-stats th, #body .attendance-stats td {
		white-space: nowrap;
		color: @jethroDarkText !important;
	}

	.attendance-stats .headcount * {
		background: @grayMid !important;
	}

	.attendance-stats .extras * {
		background: @grayLighter;
	}

	tfoot.attendance-stats th {
		text-align: right !important;
	}
	tfoot.attendance-stats td {
		/*font-weight: bold;*/
		text-align: center;
	}

	td.parallel-attendance {
		border-right: 1px solid @grayMid;
		border-left: 1px solid @grayMid;
	}

	.parallel-attendance-report th:not(.narrow) {
		min-width: 43px;
	}

	.parallel-attendance-report .new-cohort {
		border-left-width: 3px;
	}

	.thick-top-border, tr.thick-top-border td, tr.thick-top-border th  {
		border-top-width: 3px !important;
	}

/******* MULTI SELECT WIDGET *******/

   div.multi-select {
		-moz-transition: border 0.2s linear 0s, box-shadow 0.2s linear 0s;
		background-color: @white;
		border: 1px solid @grayMid;
		box-shadow: 0 1px 1px rgba(0, 0, 0, 0.075) inset;
		border-radius: 4px 4px 4px 4px;
		padding: 4px 6px 0px 6px;
		margin-bottom: 10px;
		color: @jethroDarkText;
		width: auto;
		max-width: 300px;
		overflow-y: auto;
		overflow-x: hidden;
	}
	div.multi-select label {
		padding-left: 24px !important;
		padding-right: 20px !important;
		white-space: nowrap;
		width: auto;
		display: block !important;
		margin-bottom: 5px !important;
	}
	div.multi-select label.active {
		background: @jethroGrayish;
	}
	div.multi-select label.checkbox input {
		margin-left: -22px !important;
	}

/************* SHARED ************/
div.multi-select:focus, div.radio-button-group:focus {
	/* copied from bootstrap */
  border-color: rgba(82, 168, 236, 0.8);
  outline: 0;
  outline: thin dotted \9;
  /* IE6-9 */

  -webkit-box-shadow: inset 0 1px 1px rgba(0,0,0,.075), 0 0 8px rgba(82,168,236,.6);
  -moz-box-shadow: inset 0 1px 1px rgba(0,0,0,.075), 0 0 8px rgba(82,168,236,.6);
  box-shadow: inset 0 1px 1px rgba(0,0,0,.075), 0 0 8px rgba(82,168,236,.6);
 }

/******** CHOOSER *************/

li div.delete-list-item, li div.delete-chosen-person {
	float: right;
	background-image: url(../img/cross_red.png);
	background-repeat: no-repeat;
	width: 12px;
	cursor: pointer;
	height: 12px;
	vertical-align: middle;
	position: relative;
	top: 0.5em;
	margin-top: -4px;
}

/********* DOCUMENTS ************/

.documents-container {
	margin-top: 20px;
	height: 100%;
	padding-left: 33ex;
}

.documents-container .documents-tree {
	position: absolute;
	margin-left: -33ex;
	width: 26ex;
	white-space: nowrap;
	height: 100%;
	min-height: 400px;
	overflow: auto;
}

.documents-container .documents-body {
	min-width: 50%;
	max-width: 100%;
}
.documents-container .parent-folder {
	display: none;
}
.documents-container td {
	white-space: nowrap;
	overflow: hidden;
}
@media (max-width: 770px) {
	.documents-container {
		padding-left: 0px;
		max-width: 100%;
	}
	.documents-container .documents-tree {
		display: none;
	}
	.documents-container table {
		width: 100% !important;
	}
	.documents-container tr.parent-folder {
		display: table-row;
	}
	.documents-container p.parent-folder {
		display: block;
	}
	.documents-container .file-detail {
		display: none;
	}
}
.documents-container h2 {
	float: left;
	margin-top: 0px;
	margin-right: 10px;
}
.documents-container .document-icons {
	width: auto;
	white-space: nowrap;
}
.documents-container .document-icons img {
	cursor: pointer;
}
@media (max-width: 640px) {
	#body .documents-container .document-icons {
		float: left;
		clear: left;
		margin-bottom: 10px;
	}
}
.documents-container .upload-progress {
	font-weight: bold;
	color: @jethroDarkish;
	text-align: center;
}
.documents-container .upload-progress img {
	width: 100%;
}
.documents-container .documents-tree ul, .documents-container .documents-tree li {
	margin: 0px;
	padding: 0px;
	list-style-type: none;
}
.documents-container .documents-tree a {
	display: block;
	padding: 2px 0px;
}
.documents-container .documents-tree li div {
	background: url(../img/folder.png) 3px 4px no-repeat;
	padding: 1px 2px 1px 22px;
}
.documents-container .documents-tree li ul {
	margin-left: 20px;
}
#current-folder, #current-folder a, #current-folder:visited, #current-folder a:visited {
	text-decoration: none;
	background-color: @jethroDarkish;
	color: @jethroLightText;
}

/************* AUTOCOMPLETE *************/

div.autosuggest {
	position: absolute;
	margin: 8px 0 0 0;
	padding: 4px 0px;
	background-color: @jethroDarkText;
	-webkit-border-radius: 5px;
	-moz-border-radius: 5px;
	border-radius: 5px;
	overflow: hidden;
	z-index: 9999;
}

div.autosuggest ul {
	list-style: none;
	margin: 0;
	padding: 0;
}

div.autosuggest ul li {
	color: @jethroGrayish;
	padding: 0;
	margin: 0 4px 4px;
	text-align: left;
}

div.autosuggest ul li a {
	color: @jethroGrayish;
	display: block;
	text-decoration: none;
	background-color: transparent;
	text-shadow: #000 0px 0px 5px;
	position: relative;
	padding: 0;
	width: 100%;
}
div.autosuggest ul li a:hover, div.autosuggest ul li.as_highlight a {
	background-color: @gray;
	-webkit-border-radius: 5px;
	-moz-border-radius: 5px;
	border-radius: 5px;
}

div.autosuggest ul li a span {
	display: block;
	padding: 3px 6px;
	font-weight: bold;
}

div.autosuggest ul li a span small {
	font-weight: normal;
	color: @grayLight;
}

div.autosuggest ul em {
	font-style: normal;
	color: @blue;
}

div.autosuggest ul li a .tl, div.autosuggest ul li a .tr {
	display: none;
}

/******** ATTENDANCE **********/
.attendance-config-table {
	display: inline-block;
	vertical-align: bottom;
}
.attendance-config-submit {
	vertical-align: bottom;
	display: inline-block;
}
.attendance-config-table > tbody > tr > td, .attendance-config-table > tbody > tr > th {
	line-height: 30px !important;
	padding-bottom: 4px;
}
.attendance-config-table > tbody > tr > td {
	min-width: 300px;
}
@media (max-width: 640px) {
	.attendance-config-table {
		width: 100% !important;
		margin: 0px;
	}
	.attendance-config-submit {
		float: right;
		margin-bottom: 0px;
	}
}
@media (max-width: 500px) {
	.attendance div.width-really-auto {
		width: 100% !important;
		display: block !important;
	}
	.attendance table {
		width: 100% !important;
	}
}
.attendance tr.headcount td, .attendance tr.headcount th {
	background: @grayMid;
}

/**** SYSTEM CONFIG ************/

table.system-config {
	width: 30em !important;
}
table.system-config th {
	white-space: nowrap;
}
/*table.system-config td * {
	white-space: normal;
	width: auto !important;
}*/
table.system-config td small {
	font-style: italic;
}

/*************** SERVICE PLANNING ****************/

div.service-content {
	border: 1px solid #dddddd;
	border-radius: 4px;
	padding: 5px;
	background-color: @jethroLightest;
}
.service-content h4 {
	font-size: 110%;
	color: @grayLight;
	margin-top: 1ex;
	margin-bottom: 0px;
}
table.run-sheet tbody td {
	background-color: @jethroLightest;
}
table.run-sheet .smallprint {
	line-height: 95%;
}

#service-comp-manager .tab-content {
	overflow-y: auto;
}

#service-planner .nav-tabs *, #service-comp-manager .nav-tabs * {
	font-size: 90%;
	font-weight: bold;
}
#service-planner .tab-pane {
	max-width: 100%;
}

#service-planner .comp-dragging tr.drop-hover td {
	border-top: 3px solid;
}

.component-in-transit {
	background: @jethroLight;
}
.component-in-transit td {
	border: 1px solid @grayMid;
	padding: 3px 8px;
}
.component-in-transit td.hide-in-transit {
	display: none;
}
.alt-title {
	color: @grayLight;
	font-size: 70%;
	font-style: italic;
}

#service-plan-container {
	padding-right: 15px; /* space for vertical scrollbar, so it doesn't trigger horizontal scrollbar' */
	max-height: 82vh;
	overflow: auto;
}
#service-plan-container, #service-plan-container table {
	margin-bottom: 5px; /* so it's consistent whether or not we are scrolling */
}
#service-plan .tools, #service-comps .tools {
	padding: 0px;
	vertical-align: middle;
	text-align: center;
}
#service-plan .tools ul {
	text-align: left;
}
#service-plan .tools .dropdown-toggle {
	display: block;
	padding: 3px 4px;
}
#service-plan .tools .dropdown-toggle i {
	padding: 0px !important;
	opacity: 50%;
}
#service-plan tbody td {
    -webkit-touch-callout: none;
    -webkit-user-select: none;
    -khtml-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
}
#service-plan tbody td, #service-plan tfoot td, .service-item-in-transit * {
	background: @jethroLightest;
}
.service-item-in-transit {
	border-bottom-style: solid;
	border-bottom-width: 1px;
}
#body #service-plan-placeholder {
	margin: 25px 15px;
}
/* as other items get added above the spacer, make the spacer smaller */
/* once there are 5 items (plus 2 invisible template rows), spacer is zero */
#body #service-plan-spacer, #body #service-plan-spacer td {
	height: 0px;
}
#body #service-plan-spacer:nth-child(4) td {
	height: 10em;
}
#body #service-plan-spacer:nth-child(5) td {
	height: 8em;
}
#body #service-plan-spacer:nth-child(6) td {
	height: 6em;
}
#body #service-plan-spacer:nth-child(7) td {
	height: 4em;
}
#body #service-plan-spacer:nth-child(8) td {
	height: 2em;
}

#service-plan textarea.unfocused, #service-plan input.unfocused {
	background: transparent;
	border-color: transparent;
	outline: 0px;
	box-shadow: 0px;
	-webkit-box-shadow: none;
}

#service-item-template, #service-heading-template {
	display: none;
}

#service-plan .service-item .visible-ad-hoc {
	display: none;
}
#service-plan .service-item.ad-hoc .visible-ad-hoc {
	display: inherit;
}
#service-plan .service-item.ad-hoc .hidden-ad-hoc {
	display: none;
}

.service-heading {
	font-weight: bold;
	width: 100%;
}

#service-plan input {
	margin: 0px !important;
	padding: 1px 3px;
}
#service-plan td.personnel {
	padding-top: 1px;
	padding-bottom: 1px;
}
#service-plan .personnel, #service-plan td.personnel input {
	width: 11ex;
}

#service-plan tbody textarea {
	display: block;
	font-size: 70%;
	line-height: 110%;
	width: 100%;
	margin: 0px;
}
td.run-sheet-comments {
	font-size: 85%;
}
td.run-sheet-comments * {
	line-height: 1em !important;
}
#service-plan tfoot tr:last-child td {
	border-top: 0px;
	padding-top: 0px;
}

#service-comps, #service-comps .tab-pane {
	height: 70vh;
}

#service-comps {
	margin-bottom: 20px;
	overflow: hidden;
}

#service-comps .tab-pane {
	padding-right: 5px;
	container-type: size; /* allows us to use the cqh unit within */
	overflow: hidden;
	box-sizing: border-box;
}
#service-comps .comps-table-container {
	height: 99cqh;
	overflow: auto;
}

#service-comps th {
	padding-right: 18px;
}

#service-comps table {
   margin-bottom: 0px;
}

#service-plan thead, #service-comps table thead {
	position: sticky;
	top: 0;
}
#service-comps th .icon-arrow-up, #service-comps th .icon-arrow-down {
	float: right;
	position: absolute;
	margin-right: -15px;
	margin-top: 3px;
}
#service-personnel div.column {
	float: left;
	width: 31.3%;
	margin: 0 3% 0 0%;
}
#service-personnel div:last-child  {
	margin-right: 0 !important;
}

#service-personnel label {
	font-weight: bold;
	margin: 0px;
	float: left;
	width: 8.5em;
	padding-right: 0.5em;
}
#service-personnel div div {
	margin-bottom: 5px;
}
#service-personnel div div div {
	margin-left: 9.2em;
}



@media (max-width: 900px) {
	#service-personnel div.column {
		width: 47%;
	}
}

@media (max-width: 480px) {
	#service-personnel div.column {
		width: 100%;
		margin: .5em 0 0 0;
	}
}


/*************** SMS MESSAGING ************************/
button.sms-success, #body tr.sms-success .mobile-tel, #body tr.sms-success .mobile-tel:hover {
	background: #d8e9cb; /* Old browsers */
	background: -moz-linear-gradient(top, #d8e9cb 0%, #abdc88 99%, #abdc88 99%, #d8e9cb 100%); /* FF3.6-15 */
	background: -webkit-linear-gradient(top, #d8e9cb 0%,#abdc88 99%,#abdc88 99%,#d8e9cb 100%); /* Chrome10-25,Safari5.1-6 */
	background: linear-gradient(to bottom, #d8e9cb 0%,#abdc88 99%,#abdc88 99%,#d8e9cb 100%); /* W3C, IE10+, FF16+, Chrome26+, Opera12+, Safari7+ */
	filter: progid:DXImageTransform.Microsoft.gradient( startColorstr='#d8e9cb', endColorstr='#d8e9cb',GradientType=0 ); /* IE6-9 */
	background-position: 0 0 !important;
}
#body tr.sms-failure .mobile-tel, #body tr.sms-failure .mobile-tel:hover {
	background-image: linear-gradient(linear, 0 0, 0 100%, from(#ff9c9c), to(#d22c2c)) !important;
	background-image: -webkit-gradient(linear, 0 0, 0 100%, from(#ff9c9c), to(#d22c2c)) !important;
	background-position: 0 0 !important;
}

div#send-sms-modal div.results {
  display: none;
}

div#send-sms-modal div.results {
  text-align: left;
}


/*************** CUSTOM FIELDS EDITOR *****************/

#custom-fields-editor label {
	margin: 0px !important;
}
#custom-fields-editor td {
	line-height: 30px;
}
#custom-fields-editor label.allownote {
	padding-top: 5px !important;
}
#custom-fields-editor input[type=checkbox] {
	margin-top: 0px !important;
}
#custom-fields-editor td>input[type=text] {
	margin: 0px !important;
}
#custom-fields-editor td table tr:first-child td {
	padding-top: 0px;
}
#custom-fields-editor tr.divider-before>td {
	border-top-width: 3px;
}
#custom-fields-editor tr .heading {
	display: none;
}
#custom-fields-editor tr.with-heading .heading {
	font-size: 100%;
	font-weight: bold;
	display: block;
	margin-bottom: 1em !important;
}
#custom-fields-editor tr.with-heading>td:not([class="name"]) {
	padding-top: 3.5em;
}

.note-template-fields {
	margin: 20px 0px 20px -80px;
}
@media (max-width: 480px) {
	.note-template-fields {
		margin: 20px 25px;
	}
}

#body .action-plan .fields td {
	vertical-align: middle;
}

/************ PRINT **************/
@media print {
	#jethro-overall-width, #jethro-overall-width-inner {
		display: block;
		width: 100%;
	}
	a[href]:after {
		content: "";
	}
	.no-print, .action-cell, .user-detail, .selector, .bulk-actions {
		display: none;
	}
	#jethro-nav, #jethro-nav-toprow, #jethro-nav-toprow h1, #jethro-nav-toprow .brand {
		display: block;
		margin: 0px;
		padding: 0px;
		line-height: 32px;
		font-size: 22px;
		height: 32px;
		white-space: nowrap;
		text-indent: 0px;
		color: #444;
	}
	#jethro-nav, #jethro-nav-toprow, #jethro-nav-toprow h1 {
		width: 100% !important;
		padding: 0px !important;
	}
	#jethro-nav h1 span {
		display: block !important;
		float: right !important;
		padding-right: 5px;
	}
	#jethro-nav .navbar {
		display: none;
	}
	#jethro-nav {
		border-bottom: 2px solid;
	}
}
