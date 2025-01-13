<?php
require_once 'abstract_view_add_object.class.php';
class View__Add_Group_Category extends Abstract_View_Add_Object
{
	var $_create_type = 'person_group_category';
	var $_success_message = 'New category created';
	var $_on_success_view = 'groups__manage_categories';
	var $_failure_message = 'Error creating category';
	var $_submit_label = 'Create Category';
	var $_title = 'Add Group Category';

	static function getMenuPermissionLevel()
	{
		return PERM_MANAGEGROUPCATS;
	}
	
}