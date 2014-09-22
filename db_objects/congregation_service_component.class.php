<?php
include_once 'include/db_object.class.php';
class Congregation_Service_Component extends db_object
{
	function _getFields()
	{

		$fields = Array(
			'congregationid'	=> Array(
									'type'				=> 'reference',
									'references'		=> 'congregation',
									'label'				=> 'Congregation',
									'show_id'			=> FALSE,
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
				'congcomp' => Array('congregationid', 'componentid'),
			   );
	}
	
	function getInstancesQueryComps($params, $logic, $order)
	{
		$res = parent::getInstancesQueryComps($params, $logic, $order);
		$res['select'][] = 'cong.*';
		$res['from'] .= ' JOIN congregation cong ON cong.id = congregation_service_component.congregationid';
		return $res;
	}
}
?>
