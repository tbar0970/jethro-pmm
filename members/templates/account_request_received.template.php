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
			<h3><i class="icon-ok-sign"></i> Account request received</h3>

			<p>If your email address is recognised by our system, you'll soon receive an email with a link to activate your account.</p>

			<p>If the email hasn't arrived after a few minutes, please check your junk or spam folders. Or if you think we might have a different email address on file, try again using that one.</p>

			<?php
			if (ifdef('MEMBER_REGO_HELP_EMAIL')) {
				?>
				Please contact <a href="mailto:<?php echo ents(MEMBER_REGO_HELP_EMAIL); ?>"><?php echo ents(MEMBER_REGO_HELP_EMAIL); ?></a> for help.</p>
				<?php
			}
			?>
			</p>
			
			<a class="btn btn-lnk" href="<?php echo BASE_URL; ?>members">&laquo; Back to login form</a>
			
		</div>
	</form>
</body>
</html>
