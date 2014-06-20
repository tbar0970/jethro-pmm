<!DOCTYPE html>
<html lang="en">
<head>
	<?php include dirname(dirname(dirname(__FILE__))).'/templates/head.template.php' ?>
</head>
<body id="login">
	<form method="post" id="login-box" class="well">
		<?php 
		require_once 'include/size_detector.class.php';
		SizeDetector::printFormFields();
		?>
		<div id="login-header">
			<h1><?php echo ents(SYSTEM_NAME); ?></h1>
		</div>
		<div id="login-body" class="form-horizontal">
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
			<div class="control-group">
				<label class="control-label nowrap">My email address is: </label>
				<div class="controls">
					<input type="text" name="username" autofocus="autofocus" id="email" value="" placeholder="Username" />
				</div>
			</div>
			<div class="control-group">
				<label class="control-label radio">
					<input type="radio" checked="checked" name="action" value="login">
					I have a password:
				</label>
				<div class="controls input-append">
					<input type="password" name="password" id="password" value="" placeholder="Password" />
					<input type="submit" class="btn" value="Log in" />
				</div>
			</div>
			
			<div class="control-group">
				<label class="control-label radio">
					<input type="radio" name="action" value="register">
					Please send me a new password
				</label>
				<div class="controls">
					<input class="btn" type="submit" value="Send" />
				</div>
			</div>


		</div>
	</form>
</body>
</html>
