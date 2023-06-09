<!DOCTYPE html>
<html lang="en">
<head>
	<?php include 'head.template.php' ?>
</head>
<body id="login">
	<form method="post" id="login-box" class="well">
		<?php
		require_once 'include/size_detector.class.php';
		SizeDetector::printFormFields();
		?>
		<div id="login-header">
			<h1><span>Jethro PMM </span> <?php echo ents(SYSTEM_NAME); ?></h1>
		</div>
		<div id="login-body" class="form-horizontal">
			<p class="alert alert-info">Welcome,  <?php echo ents($person_name); ?></p>
			<p class="alert alert-error">Your current Jethro password is not strong enough. Please set a stronger password to continue.</p>
			<?php
			if (!empty($this->_error)) {
				echo '<div class="alert alert-error">'.$this->_error.'</div>';
			}
			$sm = new Staff_Member();
			$sm->printFieldInterface('password', 'new_');
			?>
			<div class="control-group pull-right">
				<input type="submit" class="btn" value="Save new password" class="btn" />
				<input type="hidden" name="password_upgrade_key" value="<?php echo $password_upgrade_key; ?>" />
			</div>
		</div>
	</form>
</body>
</html>
