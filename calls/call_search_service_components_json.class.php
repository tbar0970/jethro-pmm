<?php
class Call_Search_Service_Components_Json extends Call
{
	function run()
	{
		$GLOBALS['system']->includeDBClass('service_component');
		$results = Service_Component::search(array_get($_REQUEST, 'search'), array_get($_REQUEST, 'tagid'), array_get($_REQUEST, 'congregationid'));

		echo '['.implode(',', array_keys($results)).']';
	}
}