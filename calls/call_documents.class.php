<?php
class Call_Documents extends Call
{
	function run() 
	{
		$GLOBALS['system']->initErrorHandler();
		require_once 'views/view_9_documents.class.php';
		if (!$GLOBALS['user_system']->havePerm(View_Documents::getMenuPermissionLevel())) return;
		$view = new View_Documents();
		$view->processView();
		if (!empty($_REQUEST['getfile'])) {
			$view->serveFile();
			exit();
		}

		?>
		<html>
			<head>
				<?php include 'templates/head.template.php'; ?>
			</head>
			<body id="iframe">
				<div id="body">
				<?php
				$view->printIframeContents();
				?>
				</div>
			</body>
		</html>
		<?php
	}
}

?>
