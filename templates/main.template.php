<!DOCTYPE html>
<html lang="en">
<head>
	<?php include 'head.template.php'; ?>
</head>

<body>
	<div id="jethro-nav-background">
	</div>
	<div id="jethro-overall-width">
		<div id="jethro-overall-width-inner">
		<div id="jethro-nav">

			<div id="jethro-nav-toprow">

				<!-- narrow-style nav dropdown -->
				<span class="navbar">
				<a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
				</a>
				</span>

				<!-- user details (full width) -->				
				<div class="user-detail pull-right">
					<div>
						<span class="dropdown">
							<a class="dropdown-toggle btn" id="user-menu" data-toggle="dropdown" href="#">
								<i class="icon-user"></i>
								<i class="caret"></i>
							</a>
							<ul class="dropdown-menu pull-right" role="menu" aria-labelledby="user-menu">
								<li class="user-header">
									<a href="?view=persons&personid=<?php echo $GLOBALS['user_system']->getCurrentUser('id'); ?>">
										<small>Logged in as</small><br />
										<b><?php echo $GLOBALS['user_system']->getCurrentUser('first_name').' '.$GLOBALS['user_system']->getCurrentUser('last_name'); ?></b>
									</a>
								</li>
							<?php
							if ($GLOBALS['user_system']->getCurrentRestrictions()) {
								?>
								<li class="restrictions" title=""<?php echo _('This user account can only see persons in certain congregations or groups')?>"> <?php echo _('Restrictions in effect')?> </li>
								<?php
							}
							?>
								<li><a href="?view=_edit_me"><?php echo _('Edit me')?></a></li>
								<li><a href="./?logout=1" data-method="post"><?php echo _('Log out')?></a></li>
							<?php
							if (MEMBER_LOGIN_ENABLED) {
								?>
								<li><a href="./members"><?php echo _('Go to members area')?></a></li>
								<?php
							}
							?>
							</ul>
						</span>
					</div>
				</div>
			<?php
			if (!empty($_REQUEST['view']) && $_REQUEST['view'] != 'home') {
				?>
				<form method="get" class="form-horizontal global-search no-print pull-right">
					<input type="hidden" name="view" value="_mixed_search" />
					<span class="input-append fullwidth">
						<input type="text" name="search" class="no-autofocus" enterkeyhint="Search" placeholder="<?php echo _('Search');?>..." />
						<button type="submit" class="btn"><i class="icon-search"></i></button>
					</span>
				</form>
				<?php
			}
			?>

				<!-- logo and system name -->
				<h1>
					<a class="brand" href="<?php echo BASE_URL; ?>" tabindex="-1">Jethro PMM</a>
					<span><?php echo SYSTEM_NAME; ?></span>
				</h1>

			</div><!--/.toprow-->

			<div class="navbar">
				<div class="collapse nav-collapse">
					<ul class="nav">
						<li id="user-detail-in-nav" class="dropdown">
							<a href="#" class="dropdown-toggle" data-toggle="dropdown"><b>User:</b> <?php echo $GLOBALS['user_system']->getCurrentUser('first_name').' '.$GLOBALS['user_system']->getCurrentUser('last_name'); ?> <i class="caret"></i></a>
							<ul class="dropdown-menu">
								<li><a href="?view=persons&personid=<?php echo $GLOBALS['user_system']->getCurrentUser('id'); ?>"><?php echo _('View my details'); ?></a</li>
								<li><a href="?view=_edit_me"><?php echo _('Edit my account')?></a></li>
								<li>
									<a href="./?logout=1" data-method="post"><?php echo _('Log out')?></a>
								</li>
							<?php
							if (MEMBER_LOGIN_ENABLED) {
								?>
								<li><a href="./members"><?php echo _('Go to members area')?></a></li>
								<?php
							}
							?>
							</ul>
						</li>
					<?php $GLOBALS['system']->printNavigation(); ?>
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

			$GLOBALS['system']->printBody();
			?>
		</div>
	</div>
	</div>
</body>
</html>
