<!DOCTYPE html>
<html lang="en">
<head>
	<?php include dirname(dirname(dirname(__FILE__))).'/templates/head.template.php' ?>
</head>
<body id="login">
	<form method="post" id="login-box" class="well">
		<input type="hidden" name="login_key" value="<?php echo $login_key; ?>" />
		<?php 
		require_once 'include/size_detector.class.php';
		SizeDetector::printFormFields();
		?>
		<div id="login-header">
			<h1><?php echo ents(SYSTEM_NAME); ?></h1>
		</div>
		<div id="login-body">
			<noscript>
				<div class="alert"><strong>Error: Javascript is Disabled</strong><br />For Jethro to function correctly you must enable javascript, which is done most simply by lowering the security level your browser uses for this website</div>
			</noscript>
			<?php
			if (!empty($this->_error)) {
				echo '<div class="alert alert-error">'.$this->_error.'</div>';
			} else {
				echo ' <h3>Member Login</h3>';
			}
			?>
			<label class="">
			What is your email address? <br />
			<input type="email" name="email" autofocus="autofocus" class="compulsory" value="<?php echo ents(array_get($_REQUEST, 'email', '')); ?>" placeholder="Username" />
			</label>
			
		
			<label>
				If you already have an account, enter your password:
				<div class=" input-append">
					<input type="password" name="password" value="" placeholder="Password" />
					<input type="submit" name="login-request" class="btn" value="Log in" />
				</div>
			</label>
			
			<label>
				If you don't have an account, we can send you an email to create one:
				<input class="btn" type="submit" name="password-request" value="Email me now" />
			</label>




		</div>
	</form>
</body>
</html>
