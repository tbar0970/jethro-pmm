<?php
require_once 'views/abstract_view_edit_object.class.php';
class View__Edit_Roster_View extends Abstract_View_Edit_Object
{
	var $_editing_type = 'roster_view';
	var $_on_success_view = 'rosters__define_roster_views';
	var $_on_cancel_view = 'rosters__define_roster_views';
	var $_submit_button_label = 'Save ';

	static function getMenuPermissionLevel()
	{
		return PERM_MANAGEROSTERS;
	}
}