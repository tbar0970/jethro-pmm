<?php
class service_item extends db_object
{
	protected $_load_permission_level = 0; // want PERM_VIEWSERVICE | PERM_VIEWROSTER
	protected $_save_permission_level = 0; // FUTURE: PERM_EDITSERVICE;

	protected static function _getFields()
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

			// this is ony populated for ad-hoc items - otherwise the title comes from the component.
			'title'		=> Array(
									'type'		=> 'text',
									'width'		=> 80,
									'initial_cap'	=> TRUE,
									'allow_empty' => true,
							),

			// this gets copied from the component on save.
			'show_in_handout'		=> Array(
									'type'		=> 'select',
									'options'  => Array(
													'0' => 'No',
													'title' => 'Title only',
													'full'  => 'Title and Content',
													),
									'label'    => 'Show on Handout?',
									'editable' => true,
									'show_in_summary' => true,
									'note' => 'Items that are shown on the handout appear with numbers on the run sheet.',
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
			'personnel'		=> Array(
									'type'		=> 'text',
									'width'		=> 80,
									'height'    => 4,
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
	
	public function getForeignKeys()
	{
		return Array(
			'serviceid' => "`service` (`id`) ON DELETE CASCADE",
			'componentid' => "`service_component` (`id`) ON DELETE RESTRICT",
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