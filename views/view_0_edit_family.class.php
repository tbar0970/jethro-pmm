<?php
require_once 'abstract_view_edit_object.class.php';
class View__Edit_Family extends Abstract_View_Edit_Object
{
	var $_editing_type = 'family';
	var $_on_success_view = 'families';
	var $_on_cancel_view = 'families';
	var $_submit_button_label = 'Update Family ';

	static function getMenuPermissionLevel()
	{
		return PERM_EDITPERSON;
	}
}