<?php
class Call_Service_Comp_Detail extends Call
{
	function run() 
	{
		$GLOBALS['system']->initErrorHandler();
		$comp = $GLOBALS['system']->getDBObject('service_component', (int)$_REQUEST['id']);
		include 'templates/service_component_detail.template.php';
	}
}

?>
