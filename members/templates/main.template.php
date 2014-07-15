<?php
if (empty($_REQUEST['raw'])) {
	?>
<!DOCTYPE html>
<html lang="en">
<head>
	<?php include JETHRO_ROOT.'/templates/head.template.php'; ?>
</head>

<body id="jethro-public">
	<div id="jethro-nav-background">
	</div>
	<div id="jethro-overall-width">
		<div id="jethro-overall-width-inner">
		<div id="jethro-nav">

			<div id="jethro-nav-toprow">

				<div class="user-detail pull-right" style="line-height: 40px">
					<form class="min" method="post" action="<?php echo BASE_URL; ?>/members/">
						<input type="hidden" name="logout" value="1" />
						<button class="btn-link" type="submit">Log out</button>
					</form>
				</div>

				<!-- narrow-style nav dropdown -->
				<span class="navbar">
				<a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
				</a>
				</span>

				<!-- logo and system name -->
				<h1>
					<span><?php echo SYSTEM_NAME; ?></span>
				</h1>

			</div><!--/.toprow-->

			<div class="navbar">
				<div class="collapse nav-collapse">
					<ul class="nav">
					<?php $GLOBALS['system']->printNavigation(); ?>
					<li class="visible-phone"><a href="?logout=1">Log out</a></li>
					</ul>
				</div><!--/.nav-collapse -->
			</div><!--/.navbar-->

		</div>
		<div id="body">
			<?php 		
			dump_messages(); 

			if ($title = $GLOBALS['system']->getTitle()) {
				echo '<h1>'.ents($title).'</h1>';
			}
}

$GLOBALS['system']->printBody();

if (empty($_REQUEST['raw'])) {
	?>
		</div>
	</div>
	</div>
</body>
</html>
<?php
}
