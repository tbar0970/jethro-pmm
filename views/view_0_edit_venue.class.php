<?php
require_once 'abstract_view_edit_object.class.php';
class View__Edit_Venue extends Abstract_View_Edit_Object
{
	var $_editing_type = 'venue';
	var $_on_success_view = 'attendance__checkins';
	var $_on_cancel_view = 'attendance__checkins';
	var $_submit_button_label = 'Save';
	var $_object_id_field = 'venueid';

	static function getMenuPermissionLevel()
	{
		return PERM_SYSADMIN;
	}
}