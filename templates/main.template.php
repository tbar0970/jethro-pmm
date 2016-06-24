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
						<input type="hidden" name="logout" value="1" />
						Logged in as
						<span class="dropdown">
							<a class="dropdown-toggle" id="user-menu" data-toggle="dropdown" href="#">
								<?php echo $GLOBALS['user_system']->getCurrentUser('first_name').' '.$GLOBALS['user_system']->getCurrentUser('last_name'); ?>
								<i class="caret"></i>
							</a>
							<ul class="dropdown-menu pull-right" role="menu" aria-labelledby="user-menu">
								<li><a href="?view=_edit_me">Edit me</a></li>
								<li><a href="?view=home&logout=1" data-method="post">Log out</a></li>
							</ul>
						</span>

					<?php
					if ($GLOBALS['user_system']->getCurrentRestrictions()) {
						?>
						<p class="restrictions" title="This user account can only see persons in certain congregations or groups"> Restrictions in effect </p>
						<?php
					}
					?>

					</div>
				</div>

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
							<a href="javascript:;" class="dropdown-toggle" data-toggle="dropdown"><b>User:</b> <?php echo $GLOBALS['user_system']->getCurrentUser('first_name').' '.$GLOBALS['user_system']->getCurrentUser('last_name'); ?> <i class="caret"></i></a>
							<ul class="dropdown-menu">
								<li><a href="?view=_edit_me">Edit me</a></li>
								<li>
									<a class="log-out" href="#"><form class="min" method="post" action="<?php echo BASE_URL; ?>">
										<input type="hidden" name="logout" value="1" />
										<button class="btn-link" type="submit">Log out</button>
									</form></a>
								</li>
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

			if ($title = $GLOBALS['system']->getTitle()) {
				echo '<h1>'.ents($title).'</h1>';
			}

			$GLOBALS['system']->printBody();
			?>
		</div>
	</div>
	</div>
</body>
</html>
