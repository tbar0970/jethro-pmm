<?php
include_once 'include/db_object.class.php';
class Service_Component_Tagging extends db_object
{
	function _getFields()
	{

		$fields = Array(
			'tagid'	=> Array(
									'type'				=> 'reference',
									'references'		=> 'service_component_tag',
									'label'				=> 'Tag',
									'show_id'			=> TRUE,
								   ),	
			'componentid'	=> Array(
									'type'				=> 'reference',
									'references'		=> 'service_component',
									'label'				=> 'Component',
									'show_id'			=> TRUE,
								   ),
			);
		return $fields;
	}
	
	function _getUniqueKeys()
	{
		return Array(
				'comptag' => Array('tagid', 'componentid'),
			   );
	}	
}
?>
