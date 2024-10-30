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
			<p class="alert alert-info">Welcome,  <?php echo ents($person_name); ?>!<br />
				To finish logging in, please enter the 6-digit code sent by SMS.</p>
			<?php
			if (!empty($this->_error)) {
				echo '<div class="alert alert-error">'.$this->_error.'</div>';
			}

			print_widget('2fa_code', Array('type'=>'text'), '');

			?>
			<div class="control-group pull-right">
				<input type="submit" class="btn" value="Go" class="btn" />
				<input type="hidden" name="2fa_key" value="<?php echo ents($key); ?>" />
			</div>
			<?php
			
			$trust_days = ifdef('2FA_TRUST_DAYS', 30);
			if ($trust_days) { // user can set it to false/0 to disable this feature
				echo '<label class="checkbox">';
				print_widget('2fa_trust', Array('type' => 'checkbox'), '');
				echo 'Trust this device for '.$trust_days.' days</label>';
			}
			?>
		</div>
	</form>
</body>
</html>
