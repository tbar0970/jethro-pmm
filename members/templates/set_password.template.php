<!DOCTYPE html>
<html lang="en">
<head>
	<?php include dirname(dirname(dirname(__FILE__))).'/templates/head.template.php' ?>
</head>
<body id="login">
	<form method="post" action="?" id="login-box" class="well disable-submit-buttons">
		<div id="login-header">
			<h1><?php echo ents(SYSTEM_NAME); ?></h1>
		</div>
		<div id="login-body">
			<?php
			if (!empty($this->_error)) {
				echo '<div class="alert alert-error">'.$this->_error.'</div>';
			} else {
				echo ' <h3>Set Password</h3>';
			}
			?>
			<p>Great - your email address has now been verified. </p>
			
			<p>Now we just need you to choose a password to use next time you log in.</p>
			
			<label>Password:</label>		
			<input type="password" name="password1" data-minlength="<?php echo MEMBER_PASSWORD_MIN_LENGTH; ?>" />
			
			<label>And again, to confirm:</label>
			<input type="password" name="password2" data-minlength="<?php echo MEMBER_PASSWORD_MIN_LENGTH; ?>" />
			<br />
			
			<input type="submit" class="btn" name="set-password" value="Continue" />
		</div>
	</form>
</body>
</html>
