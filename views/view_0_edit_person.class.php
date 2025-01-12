<?php
require_once 'abstract_view_edit_object.class.php';
class View__Edit_Person extends Abstract_View_Edit_Object
{
	var $_editing_type = 'person';
	var $_on_success_view = 'persons';
	var $_on_cancel_view = 'persons';
	var $_submit_button_label = 'Update Person ';
	var $_form_classnames = 'warn-unsaved';

	static function getMenuPermissionLevel()
	{
		return PERM_EDITPERSON;
	}
}