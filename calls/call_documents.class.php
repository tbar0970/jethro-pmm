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
		if (!empty($_REQUEST['zipfile'])) {
			$view->serveZip();
			exit;
		}
	}
}