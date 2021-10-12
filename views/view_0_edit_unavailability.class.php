<?php
require_once 'abstract_view_edit_object.class.php';
class View__Edit_Unavailability extends Abstract_View_Edit_Object
{
	var $_editing_type = 'person_unavailable';
	var $_on_success_view = 'persons';
	var $_on_cancel_view = 'persons';
	var $_submit_button_label = 'Update Availability ';
	var $_object_id_field = 'id';

	static function getMenuPermissionLevel()
	{
		return PERM_EDITPERSON;
	}

	function _processObjectEditing()
	{
		$processed = FALSE;
		switch (array_get($_REQUEST, 'action')) {

			case 'remove_dates':
				$personid = array_get($_POST, 'personid');
				$from = array_get($_POST, 'from');
				if (!empty($personid)) {
					$GLOBALS['system']->includeDBClass('person_unavailable');
					Person_Unavailable::removeDates((int)$personid,$from);
				}
				break;
		}

		redirect('persons', Array('personid' => (int)$personid), 'availability');

	}
	
}
?>
