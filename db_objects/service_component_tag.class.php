<?php
class Service_Component_Tag extends db_object
{
	var $_load_permission_level = 0; // want PERM_VIEWSERVICE | PERM_VIEWROSTER
	var $_save_permission_level = 0; // FUTURE: PERM_EDITSERVICE;

	function _getFields()
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

}