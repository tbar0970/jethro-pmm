	<title>
		<?php
		if (isset($GLOBALS['system']) && ($title = $GLOBALS['system']->getTitle())) echo $title.' - ';
		if (!defined('IS_PUBLIC')) echo 'Jethro - ';
		echo ifdef('SYSTEM_NAME', '');
		?>
	</title>
	<meta name="robots" content="noindex, nofollow">
	<meta name="mobile-web-app-capable" content="yes">
	<link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>/resources/img/iphone-icon.png"/>
	<meta name="viewport" content="width=device-width, initial-scale=1.0<?php
		if (FALSE === strpos(array_get($_SERVER, 'HTTP_USER_AGENT', ''), 'iPad')) echo ', user-scalable=no';
		?>">
        <meta name="jethro-sms-configured" content="<?php require_once JETHRO_ROOT.'/include/jethro_sms.php'; echo (int)(Jethro\Sms\canSendSms()); ?>"/><?php // Used by js to show/hide 'Sms via Jethro' on the mobile number ?>
        <meta name="jethro-is-narrow" content="<?php require_once 'include/size_detector.class.php'; echo (int)SizeDetector::isNarrow(); ?>"/>
        <meta name="jethro-is-mac" content="<?php echo str_contains((string) array_get($_SERVER, 'HTTP_USER_AGENT', ''), 'Macintosh') ? '1' : '0'; ?>"/>
	<!--[if IE]>
	<link type="text/css" rel="stylesheet" href="<?php echo BASE_URL; ?>/resources/css/jethro_msie.css" />
	<![endif]-->
<?php

// ---- CSS ------ //
$customLessVars = defined('CUSTOM_LESS_VARS') ? constant('CUSTOM_LESS_VARS') : NULL;
$customCSSFile ='jethro-'.JETHRO_VERSION.'-custom.css';
if (file_exists(JETHRO_ROOT.'/'.$customCSSFile)) {
	?>
	<link type="text/css" rel="stylesheet" href="<?php echo BASE_URL; ?>/<?php echo $customCSSFile; ?>" />
	<?php
} else if ((JETHRO_VERSION == 'DEV') || $customLessVars) {
	$devMode = TRUE;
	if (JETHRO_VERSION != 'DEV') {
		// In a production environment with custom vars, act like a dev environment
		// if the conf file has been modified in the last 2 minutes
		$devMode = (time() - filemtime(JETHRO_ROOT.'/conf.php') < 120);
	}
	// Load LESS and build CSS in the browser.
	// NB we use an older version of LESS (1.7.5) because bootstrap 2.3 is not compatible with the latest LESS.
	?>
	<link rel="stylesheet/less" type="text/css" href="<?php echo BASE_URL; ?>/resources/less/jethro.less.php" />
	<link type="text/css" rel="stylesheet" href="<?php echo BASE_URL; ?>/resources/css/jquery-ui.css" />
	<script>
		less = {
		  env: "<?php echo $devMode ? "development" : "production"; ?>",
		  dumpLineNumbers: <?php echo $devMode ? "'comments'" : "false"; ?>
		};
	</script>
	<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/less.js/1.7.5/less.min.js"></script>
	<?php
} else {
	// use packaged combined CSS
	?>
	<link type="text/css" rel="stylesheet" href="<?php echo BASE_URL; ?>/resources/css/jethro-<?php echo JETHRO_VERSION; ?>.css" />
	<?php
}

// ---- JAVASCRIPT ------ //

if (JETHRO_VERSION == 'DEV') {
	?>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>/resources/js/jquery.js"></script>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>/resources/js/bootstrap.js"></script>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>/resources/js/tb_lib.js"></script>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>/resources/js/jethro.js"></script>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>/resources/js/bsn_autosuggest.js"></script>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>/resources/js/jquery-ui.js"></script>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>/resources/js/jquery.ui.touch-punch.min.js"></script>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>/resources/js/stupidtable.min.js"></script>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>/resources/js/treeview.js"></script>
	<?php
} else {
	?>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>/resources/js/jethro-<?php echo JETHRO_VERSION; ?>.js"></script>
	<?php
}

// The members portal uses Member_User_System, which has no permission levels (no havePerm());
// skip the staff-only SMS/Datastar block there.
if (isset($GLOBALS['user_system'], $GLOBALS['system']) && method_exists($GLOBALS['user_system'], 'havePerm')) {
	// PERM_VIEWSMS too: the Messages page uses its history helpers (updateSmsCostSum).
	$smsUi = $GLOBALS['system']->featureEnabled('SMS')
		&& ($GLOBALS['user_system']->havePerm(PERM_SENDSMS) || $GLOBALS['user_system']->havePerm(PERM_VIEWSMS));
	if ($smsUi) {
		?>
		<script type="text/javascript" src="<?php echo BASE_URL; ?>/resources/js/jethro-sms.js?v=<?php echo urlencode(JETHRO_VERSION); ?>"></script>
		<?php
	}

	// Load datastar only on pages that need it. See docs/docs/developer/sms/SMS_DATASTAR.md
	// Datastar drives the System Configuration status panels — SMTP, Mailchimp, etc, which are SYSADMIN-only
	// needed for SMS statusline/preview SSE
	$datastarNeeded ??= $smsUi;
	$datastarNeeded ??= $GLOBALS['user_system']->havePerm(PERM_SYSADMIN);
	if ($datastarNeeded) {
		?>
		<script type="module" src="<?php echo BASE_URL; ?>/resources/js/datastar-1.0.4.min.js"></script>
		<?php
	}
}

if (defined('EXTRA_HEAD_HTML')) {
	echo EXTRA_HEAD_HTML;
}
