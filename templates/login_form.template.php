<!DOCTYPE html>
<html lang="en" ng-app="login_form.template">
<head>
	<?php include 'head.template.php' ?>
</head>
<body id="login" ng-controller="LoginFormTemplate">
	<form method="post" id="login-box" class="well">
		<?php 
		require_once 'include/size_detector.class.php';
		SizeDetector::printFormFields();
		?>
		<div id="login-header">
			<h1><span>Jethro PMM </span> {{systemName}}</h1>
		</div>
		<div id="login-body" class="form-horizontal">
			<noscript>
				<div class="alert"><strong>Error: Javascript is Disabled</strong><br />For Jethro to function correctly you must enable javascript, which is done most simply by lowering the security level your browser uses for this website</div>
			</noscript>
			<div class="alert alert-error">{{pageError}}</div>
			<h3 name="loginHeader">Login</h3>
			<div class="control-group">
				<label class="control-label" for="username">Username</label>
				<div class="controls">
					<input type="text" name="username" id="username" value="{{prefillUsername}}" placeholder="Username"	/>
				</div>
			</div>
			<div class="control-group">
				<label class="control-label" for="password">Password</label>
				<div class="controls">
					<input type="password" name="password" id="password" value="{{prefillPassword}}" placeholder="Password" />
				</div>
			</div>
			<div class="control-group">
				<div class="controls">
					<input type="submit" value="Log In" class="btn" />
					<input type="hidden" name="login_key" value="{{loginKey}}" /><br />
				</div>
			</div>
			<div class="control-group">
				<div class="controls">
					<p name="loginNote">{{loginNote}}</p>
				</div>
			</div>
		</div>
	</form>
<script>
var loginFormTemplate = angular.module("login_form.template",[]);
loginFormTemplate.controller("LoginFormTemplate",function($scope){
	$scope.systemName = "<?php echo ents(SYSTEM_NAME); ?>";
	$scope.pageError = "<?php if (!empty($this->_error)) { echo $this->_error; }?>";
	if ($scope.pageError && $scope.pageError != ""){
		$("[name='loginHeader']").addClass("hidden");
	} else {
		$(".alert-error").addClass("hidden");
	}
	$scope.loginNote = "<?php if (defined('LOGIN_NOTE') && LOGIN_NOTE) { echo LOGIN_NOTE; } ?>";
	if ($scope.loginNote == "") $("[name='loginNote']").addClass("hidden");
	$scope.prefillUsername = "<?php if (defined('PREFILL_USERNAME')) echo PREFILL_USERNAME; ?>";
	$scope.prefillPassword = "<?php if (defined('PREFILL_PASSWORD')) echo PREFILL_PASSWORD; ?>";
	$scope.loginKey = "<?php echo $login_key; ?>";
});
</script>
</body>
</html>
