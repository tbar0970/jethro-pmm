	<title>
		<?php
		if (!defined('IS_PUBLIC')) echo 'Jethro PMM - ';
		echo SYSTEM_NAME;
		if (isset($GLOBALS['system']) && ($title = $GLOBALS['system']->getTitle())) echo ' - '.$title;
		?>
	</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<!--[if IE]>
	<link type="text/css" rel="stylesheet" href="<?php echo BASE_URL; ?>resources/css/jethro_msie.css" />
	<![endif]-->
<?php
if (JETHRO_VERSION != 'DEV') {
	?>
	<link type="text/css" rel="stylesheet" href="<?php echo BASE_URL; ?>/resources/css/jethro-<?php echo JETHRO_VERSION; ?>.css" />
	<script type="text/javascript" src="<?php echo BASE_URL; ?>resources/js/jethro-<?php echo JETHRO_VERSION; ?>.js"></script>
	<?php
} else {
	?>
	<link type="text/css" rel="stylesheet" href="<?php echo BASE_URL; ?>resources/css/bootstrap.css" />
	<link type="text/css" rel="stylesheet" href="<?php echo BASE_URL; ?>resources/css/jethro.css" />
	<link type="text/css" rel="stylesheet" href="<?php echo BASE_URL; ?>resources/css/jquery-ui.css" />
	<script type="text/javascript" src="<?php echo BASE_URL; ?>resources/js/jquery.js?t=<?php echo time(); ?>"></script>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>resources/js/bootstrap.js?t=<?php echo time(); ?>"></script>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>resources/js/tb_lib.js?t=<?php echo time(); ?>"></script>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>resources/js/jethro.js?t=<?php echo time(); ?>"></script>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>resources/js/bsn_autosuggest.js?t=<?php echo time(); ?>"></script>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>resources/js/jquery-ui.js?t=<?php echo time(); ?>"></script>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>resources/js/stupidtable.min.js?t=<?php echo time(); ?>"></script>
	<script type="text/javascript" src="<?php echo BASE_URL; ?>resources/js/angular.min.js"></script>
	<?php
}

if (defined('EXTRA_HEAD_HTML')) {
	echo EXTRA_HEAD_HTML;
}

