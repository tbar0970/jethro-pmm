<?php
class service_item extends db_object
{
	var $_load_permission_level = 0; // want PERM_VIEWSERVICE | PERM_VIEWROSTER
	var $_save_permission_level = 0; // FUTURE: PERM_EDITSERVICE;

	function _getFields()
	{

		$fields = Array(
			'serviceid'	=> Array(
									'type'				=> 'reference',
									'references'		=> 'service',
									'label'				=> 'Service',
									'show_id'			=> FALSE,
								   ),
			'rank'		=> Array(
									'type'		=> 'int',
								   ),
			'componentid'	=> Array(
									'type'				=> 'reference',
									'references'		=> 'service_component',
									'label'				=> 'Service Component',
									'show_id'			=> FALSE,
									'allow_empty'       => TRUE,
								   ),
			'length_mins'		=> Array(
									'type'		=> 'int',
									'width'		=> 6,
								   ),
			'heading_text'		=> Array(
									'type'		=> 'text',
									'width'		=> 80,
									'initial_cap'	=> TRUE,
								   ),
			'note'		=> Array(
									'type'		=> 'text',
									'width'		=> 80,
									'height'    => 4,
									'initial_cap'	=> TRUE,
								   ),
		);
		return $fields;
	}

	function _getUniqueKeys()
	{
		return Array(
				'servicerank' => Array('serviceid', 'rank'),
			   );
	}
	

	function toString()
	{
		if (!empty($this->values['componentid'])) {
			return $this->getFormattedValue('componentid');
		} else {
			return $this->values['heading_text'];
		}
	}

}