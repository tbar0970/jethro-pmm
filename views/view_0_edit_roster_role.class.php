<?php
require_once 'views/abstract_view_edit_object.class.php';
class View__Edit_Roster_Role extends Abstract_View_Edit_Object
{
	var $_editing_type = 'roster_role';
	var $_on_success_view = 'rosters__define_roster_roles';
	var $_on_cancel_view = 'rosters__define_roster_roles';
	var $_submit_button_label = 'Save ';

	static function getMenuPermissionLevel()
	{
		return PERM_MANAGEROSTERS;
	}
}