<?php
require_once 'abstract_view_edit_object.class.php';
class View__Edit_Group_Category extends Abstract_View_Edit_Object
{
	var $_editing_type = 'person_group_category';
	var $_on_success_view = 'groups__manage_categories';
	var $_on_cancel_view = 'groups__manage_categories';
	var $_submit_button_label = 'Update Group Category';
	var $_object_id_field = 'categoryid';

	static function getMenuPermissionLevel()
	{
		return PERM_MANAGEGROUPCATS;
	}
}