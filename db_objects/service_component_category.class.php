<?php
class Service_Component_Category extends db_object
{
	var $_load_permission_level = 0; // want PERM_VIEWSERVICE | PERM_VIEWROSTER
	var $_save_permission_level = 0; // FUTURE: PERM_EDITSERVICE;

	protected static function _getFields()
	{

		$fields = Array(
			'category_name'		=> Array(
									'type'		=> 'text',
									'width'		=> 80,
									'initial_cap'	=> TRUE,
								   ),
			'runsheet_title_format'	=> Array(
									'type'		=> 'text',
									'width'		=> 80,
									'initial_cap'	=> TRUE,
									'default' => '%title%',
								   ),
			'handout_title_format'	=> Array(
									'type'		=> 'text',
									'width'		=> 80,
									'initial_cap'	=> TRUE,
									'default' => '%title%',
								   ),
			'show_in_handout_default'		=> Array(
									'type'		=> 'select',
									'options'  => Array(
													'0' => 'No',
													'title' => 'Title only',
													'full'  => 'Title and Content',
													),
									'default' => 'full',
								   ),
			'show_on_slide_default'		=> Array(
									'type'		=> 'select',
									'options'  => Array('No', 'Yes'),
									'default' => 1,
								   ),
			'length_mins_default'		=> Array(
									'type'		=> 'int',
									'width'		=> 6,
								   ),
		);
		return $fields;
	}
	
	function getInitSQL()
	{
		$res = (array)parent::getInitSQL();
		$res[] = 'INSERT INTO service_component_category
				  (category_name, runsheet_title_format, handout_title_format, length_mins_default)
				  VALUES 
				  ("Songs", "Song: %title%", "Song: %title%", 3),
				  ("Prayers", "%title%", "%title%", 2),
				  ("Creeds", "The %title%", "The %title%", 2),
				  ("Other", "%title%", "%title%", 1);';
		return $res;
		
	}


	function toString()
	{
		return $this->values['category_name'];
	}

}