<?php
class Call_Search_Service_Components_Json extends Call
{
	function run()
	{
		$conds = Array();
		if (!empty($_REQUEST['search'])) {
			$s = $_REQUEST['search'];
			$conds += Array(
						'title' => '%'.$s.'%',
						'alt_title' => '%'.$s.'%',
						'content_html' => '%'.$s.'%',
					);
		}
		if (!empty($_REQUEST['tagid'])) {
			$conds['tagid'] = (int)$_REQUEST['tagid'];
		}
		$results = $GLOBALS['system']->getDBObjectData('service_component', $conds, 'OR');

		echo '['.implode(',', array_keys($results)).']';
	}
}
?>
