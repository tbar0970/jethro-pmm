<!DOCTYPE html>
<html lang="en">
<head>
	<?php include dirname(dirname(dirname(__FILE__))).'/templates/head.template.php' ?>
</head>
<body id="login">
	<form method="post" id="login-box" class="well disable-submit-buttons" action="<?php echo build_url(Array('logout' => NULL)); ?>" target="_top">
		<input type="hidden" name="login_key" value="<?php echo $login_key; ?>" />
		<?php
		require_once 'include/size_detector.class.php';
		SizeDetector::printFormFields();
		?>
		<div id="login-header">
			<h1><?php echo ents(SYSTEM_NAME); ?></h1>
		</div>
		<div id="login-body" class="member-login">
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
			<p>If we have your email address on file, you can log in here.</p>

			<div style="display:flex">
				<label style="margin: 5px 5px 0 0"><b>Email: </b></label>
				<input style="flex-grow:10" type="email" name="email" autofocus="autofocus" class="compulsory" value="<?php echo ents(array_get($_REQUEST, 'email', '')); ?>" placeholder="Email" />
			</div>

			<div id="member-login-options">
				<div id="member-login-left">
					<b>Got a password?</b><br />
					Enter your password to log in<br />
						<div class="input-append">
							<input type="password" name="password" value="" placeholder="Password"/><br>
							<input type="submit" name="login-request" class="btn" value="Log in" />
						</div>
				</div>
				<div id="member-login-right">
					<b>No password?<br>Forgot password?</b><br />
					<input class="btn" type="submit" name="password-request" value="Send activation link" />
				</div>
			</div>


			<table class="valign-top" style="width:100%">
				<tr>
					<td style="padding-right: 1em; padding-bottom: 0">
					</td>
					<td style="border-left: 2px solid #bbb; padding-left: 1em; padding-right: 0px; padding-bottom: 0; width: 1%; white-space: nowrap">
					</td>

				</tr>
			</table>
		<?php
		if (defined('MEMBER_LOGIN_NOTE') && MEMBER_LOGIN_NOTE) {
			echo '<p>'.MEMBER_LOGIN_NOTE.'</p>';
		}
		?>
		</div>
	</form>
</body>
</html>
