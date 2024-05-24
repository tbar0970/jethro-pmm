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

				<div class="user-detail pull-right">
					<div>
						<input type="hidden" name="logout" value="1" />
						Logged in as 
						<span class="dropdown">
						<a class="dropdown-toggle" id="user-menu" data-toggle="dropdown" href="#">
							<?php echo $GLOBALS['user_system']->getCurrentMember('first_name').' '.$GLOBALS['user_system']->getCurrentMember('last_name'); ?>
							<i class="caret"></i> 
						</a>
						<ul class="dropdown-menu pull-right" role="menu" aria-labelledby="user-menu">
							<li><a href="?view=_change_password">Change Password</a></li>
							<li><a href="<?php echo BASE_URL; ?>members/?logout=1" data-method="post">Log out</a></li>
						</ul>
						</span>
					</div>				
				
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

			if ($title = $GLOBALS['system']->getPageHeading()) {
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
