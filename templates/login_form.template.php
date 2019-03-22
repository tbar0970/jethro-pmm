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
			<noscript>
				<div class="alert"><strong><?php echo _('Error: Javascript is Disabled')?></strong><br /><?php echo _('For Jethro to function correctly you must enable javascript, which is done most simply by lowering the security level your browser uses for this website')?></div>
			</noscript>
			<?php
			dump_messages();
			if (!empty($this->_error)) {
				echo '<div class="alert alert-error">'.$this->_error.'</div>';
			} else {
				echo ' <h3>Control Centre Login</h3>';
			}
			?>
			<div class="control-group">
				<label class="control-label" for="username"><?php echo _('Username')?></label>
				<div class="controls">
					<input type="text" name="username" id="username" placeholder="Username"
					<?php if (defined('PREFILL_USERNAME')) echo 'value="'.PREFILL_USERNAME.'"'; ?>
					/>
				</div>
			</div>
			<div class="control-group">
				<label class="control-label" for="password"><?php echo _('Password')?></label>
				<div class="controls">
					<input type="password" name="password" id="password"
		   			<?php if (defined('PREFILL_PASSWORD')) echo 'value="'.PREFILL_PASSWORD.'"'; ?>
					placeholder="Password" />
				</div>
			</div>
			<div class="control-group">
				<div class="controls">
					<input type="submit" value="Log In" class="btn" />
					<input type="hidden" name="login_key" value="<?php echo $login_key; ?>" /><br />
				</div>
			</div>
		<?php
		if (defined('LOGIN_NOTE') && LOGIN_NOTE) {
			?>
			<div class="control-group">
				<div class="controls">
					<?php echo '<p>'.LOGIN_NOTE.'</p>'; ?>
				</div>
			</div>
			<?php
		}
		if (ifdef('MEMBER_LOGIN_ENABLED') || ifdef('PUBLIC_AREA_ENABLED')) {
			?>
			<hr />
			<?php
		}
		if (ifdef('PUBLIC_AREA_ENABLED')) {
			?>
			<a class="clickable pull-right" href="./public">Public area</a>
			<?php
		}
		if (ifdef('MEMBER_LOGIN_ENABLED')) {
			?>
			<a class="clickable" href="./members">Members area</a>
			<?php
		}

		?>

		</div>
	</form>
</body>
</html>
