<?php
include_once 'include/db_object.class.php';
class Service_Component_Tagging extends db_object
{
	protected static function _getFields()
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

	public function getForeignKeys()
	{
		return Array('tagid' => 'service_component_tag(id) ON DELETE CASCADE');
	}

	function getInstancesQueryComps($params, $logic, $order) {
		$res = parent::getInstancesQueryComps($params, $logic, $order);
		$res['from'] .= ' JOIN service_component_tag sct ON sct.id = tagid ';
		$res['select'][] = 'sct.tag';
		return $res;
	}

	/**
	 * Create a new Service_Component-Tag relationship if one doesn't exist
	 * @return bool Whether it was newly created
	 */
	public function createIfNew()
	{
		$db = $GLOBALS['db'];
		$sql = 'SELECT id
			FROM service_component_tagging 
			WHERE tagid = '.$db->quote($this->getValue('tagid')).'
			AND componentid = '.$db->quote($this->getValue('componentid'));
		$id = $db->queryOne($sql);
		if (!(int)$id) {
			return $this->create();
		} else {
			$this->id = $id;
			return FALSE;
		}
	}
}