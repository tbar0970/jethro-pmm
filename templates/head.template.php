	<title>
		<?php
		if (!defined('IS_PUBLIC')) echo 'Jethro PMM - ';
		echo SYSTEM_NAME;
		if (isset($GLOBALS['system']) && ($title = $GLOBALS['system']->getTitle())) echo ' - '.$title;
		?>
	</title>
	<!-- Required meta tags always come first -->
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<meta http-equiv="x-ua-compatible" content="ie=edge">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<!-- Material Design fonts -->
	<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700">
	<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">

	<!-- Bootstrap Material Design -->
	<link rel="stylesheet" href="https://cdn.rawgit.com/FezVrasta/bootstrap-material-design/dist/dist/bootstrap-material-design.min.css">
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

if (defined('EXTRA_HEAD_HTML')) {
	echo EXTRA_HEAD_HTML;
}
