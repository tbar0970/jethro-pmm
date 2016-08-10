<?php
class Call_Contact_List extends Call
{
	function run()
	{
		$GLOBALS['system']->initErrorHandler();
		require_once 'views/view_2_families__4_contact_list.class.php';
		if (!$GLOBALS['user_system']->havePerm(View_Families__Contact_List::getMenuPermissionLevel())) return;
		$view = new View_Families__Contact_List();
		header('Content-disposition: attachment; filename="Contact-List.html"');
		$view->processView();
		$view->printResults(TRUE);
	}
}

?>
