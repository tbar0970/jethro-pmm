<?php
class Service_Component_Tag extends db_object
{
	protected $_load_permission_level = 0; // want PERM_VIEWSERVICE | PERM_VIEWROSTER
	protected $_save_permission_level = 0; // FUTURE: PERM_EDITSERVICE;

	protected static function _getFields()
	{

		$fields = Array(
			'tag'		=> Array(
									'type'		=> 'text',
									'width'		=> 80,
									'initial_cap'	=> TRUE,
								   ),
		);
		return $fields;
	}


	function toString()
	{
		return $this->values['tag'];
	}

	/**
	 * Save this (new) tag to the database IF there isn't already a tag with the same name.
	 * @return bool Whether it was newly created
	 */
	public function createIfNew()
	{
		$db = $GLOBALS['db'];
		$sql = 'SELECT id
			FROM service_component_tag
			WHERE tag = '.$db->quote($this->getValue('tag'));
		$id = $db->queryOne($sql);
		if (!(int)$id) {
			return $this->create();
		} else {
			$this->id = $id;
			return FALSE;
		}
	}


}