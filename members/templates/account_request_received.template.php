<!DOCTYPE html>
<html lang="en">
<head>
	<?php include dirname(dirname(dirname(__FILE__))).'/templates/head.template.php' ?>
</head>
<body id="login">
	<form method="post" id="login-box" class="well" action="" novalidate>
		<div id="login-header">
			<h1><?php echo ents(SYSTEM_NAME); ?></h1>
		</div>
		<div id="login-body">
			<h3>Email sent</h3>

			<p>Please read the email that we have sent you, and click the link inside the email to finish creating your account.</p>

			<p>If the email hasn't arrived after a few minutes, please check your junk or spam folders.</p>
			
			<p>If you still haven't received the email, it might be because your email address is not yet recorded in our database.  

			<?php
			if (defined('MEMBER_REGO_HELP_EMAIL')) {
				?>
				Please contact <a href="<?php echo ents(MEMBER_REGO_HELP_EMAIL); ?>"><?php echo ents(MEMBER_REGO_HELP_EMAIL); ?></a> for help.</p>
				<?php
			}
			?>
			</p>
			
			<a class="btn btn-lnk" href="<?php echo BASE_URL; ?>members">&laquo; Back to login form</a>
			
		</div>
	</form>
</body>
</html>
