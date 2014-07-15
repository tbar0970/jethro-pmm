<!DOCTYPE html>
<html lang="en">
<head>
	<?php include dirname(dirname(dirname(__FILE__))).'/templates/head.template.php' ?>
</head>
<body id="login">
	<form method="post" id="login-box" class="well">
		<div id="login-header">
			<h1><?php echo ents(SYSTEM_NAME); ?></h1>
		</div>
		<div id="login-body">
			<h3>Email sent</h3>

			<p>Please read the email that we have sent you, and click the link inside the email to finish creating your account.</p>

			<p>If the email hasn't arrived after a few minutes, please check your junk or spam folders.</p>

		<?php
		if (defined('MEMBER_REGO_HELP_EMAIL')) {
			?>
			<p>If you still haven't received the email, it might be because your email address is not yet recorded in our database.  Please contact <a href="<?php echo ents(MEMBER_REGO_HELP_EMAIL); ?>"><?php echo ents(MEMBER_REGO_HELP_EMAIL); ?></a> for help.</p>
			<?php
		}
		?>
			
		</div>
	</form>
</body>
</html>
