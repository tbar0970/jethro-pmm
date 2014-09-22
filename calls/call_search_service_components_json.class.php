<?php
class Call_Search_Service_Components_Json extends Call
{
	function run()
	{
		$s = $_REQUEST['search'];
		$conds = Array(
					'title' => '%'.$s.'%',
					'alt_title' => '%'.$s.'%',
					'content_html' => '%'.$s.'%',
				);
		$results = $GLOBALS['system']->getDBObjectData('service_component', $conds, 'OR');

		echo '['.implode(',', array_keys($results)).']';
	}
}
?>
