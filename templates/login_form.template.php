<?php include 'layout.php' ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<?php include 'head.template.php' ?>
</head>
<body id="login">
	<?php startLayout(false); ?>
		<form method="post" id="login-box" class="form-signin m-x-auto">
			<h1>Jethro PMM </span></h1>
			<h2>Please sign in</h2>
			<?php
			if (!empty($this->_error)) {
				echo '<div class="form-group bmd-form-group">'.$this->_error.'</div>';
			}
			?>
			<div class="form-group bmd-form-group">
				<label class="bmd-label-floating" for="username"><?php echo _('Username')?></label>
				<input class="form-control" autofocus="true" required="true" type="text" name="username" id="username"
				<?php if (defined('PREFILL_USERNAME')) echo 'value="'.PREFILL_USERNAME.'"'; ?>
				/>
			</div>
			<div class="form-group bmd-form-group">
				<label class="bmd-label-floating" for="password"><?php echo _('Password')?></label>
				<input class="form-control" required="true" type="password" name="password" id="password"
		   			<?php if (defined('PREFILL_PASSWORD')) echo 'value="'.PREFILL_PASSWORD.'"'; ?>
				/>
				</div>
			</div>
			<span class="bmd-form-group">
				<noscript>
					<div class="alert"><strong><?php echo _('Error: Javascript is Disabled')?></strong><br /><?php echo _('For Jethro to function correctly you must enable javascript, which is done most simply by lowering the security level your browser uses for this website')?></div>
				</noscript>
			</span>
			<button class="btn btn-lg btn-primary btn-block" type="submit">Sign in</button>
			<input type="hidden" name="login_key" value="<?php echo $login_key; ?>" />
		</form>
		<?php
		if (defined('LOGIN_NOTE') && LOGIN_NOTE) {
			?>
			<div class="m-x-auto">
				<?php echo '<p>'.LOGIN_NOTE.'</p>'; ?>
			</div>
			<?php
		}
		?>
	<?php finishLayout(); ?>
</body>
<?php include 'footer.template.php' ?>
</html>
