	<title>
		<?php
		if (isset($GLOBALS['system']) && ($title = $GLOBALS['system']->getTitle())) echo $title.' - ';
		if (!defined('IS_PUBLIC')) echo 'Jethro - ';
		echo ifdef('SYSTEM_NAME', '');
		?>
	</title>
	<meta name="robots" content="noindex, nofollow">
	<meta name="mobile-web-app-capable" content="yes">
	<link rel="apple-touch-icon" href="resources/img/iphone-icon.png"/>
	<meta name="viewport" content="width=device-width, initial-scale=1.0<?php
		if (FALSE === strpos(array_get($_SERVER, 'HTTP_USER_AGENT', ''), 'iPad')) echo ', user-scalable=no';
		?>">
	<!--[if IE]>
	<link type="text/css" rel="stylesheet" href="<?php echo BASE_URL; ?>resources/css/jethro_msie.css" />
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
	<link rel="stylesheet/less" type="text/css" href="<?php echo BASE_URL; ?>resources/less/jethro.less.php" />
	<link type="text/css" rel="stylesheet" href="<?php echo BASE_URL; ?>resources/css/jquery-ui.css" />
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
	<link type="text/css" rel="stylesheet" href="<?php echo BASE_URL; ?>resources/css/jethro-<?php echo JETHRO_VERSION; ?>.css" />
	<?php
}

// ---- JAVASCRIPT ------ //

if (JETHRO_VERSION == 'DEV') {
	?>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>resources/js/jquery.js?t=<?php echo time(); ?>"></script>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>resources/js/bootstrap.js?t=<?php echo time(); ?>"></script>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>resources/js/tb_lib.js?t=<?php echo time(); ?>"></script>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>resources/js/jethro.js?t=<?php echo time(); ?>"></script>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>resources/js/bsn_autosuggest.js?t=<?php echo time(); ?>"></script>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>resources/js/jquery-ui.js?t=<?php echo time(); ?>"></script>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>resources/js/jquery.ui.touch-punch.min.js?t=<?php echo time(); ?>"></script>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>resources/js/stupidtable.min.js?t=<?php echo time(); ?>"></script>
	<?php
} else {
	?>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>resources/js/jethro-<?php echo JETHRO_VERSION; ?>.js"></script>
	<?php
}

if (defined('EXTRA_HEAD_HTML')) {
	echo EXTRA_HEAD_HTML;
}

