<?php
class View__Add_Unavailability extends View
{

	static function getMenuPermissionLevel()
	{
		return PERM_EDITPERSON;
	}

	function processView()
	{
		if (empty($_REQUEST['personid'])) {
			trigger_error(_('Cannot add period, no person ID specified'), E_USER_WARNING);
			return;
		}
		
		switch (array_get($_REQUEST, 'action')) {

			case 'add_dates':
				$personid = array_get($_POST, 'personid');
				$from = array_get($_POST, 'params_from_y').'-'.array_get($_POST, 'params_from_m').'-'.array_get($_POST, 'params_from_d');
				$to = array_get($_POST, 'params_to_y').'-'.array_get($_POST, 'params_to_m').'-'.array_get($_POST, 'params_to_d');
				$GLOBALS['system']->includeDBClass('person_unavailable');
				Person_Unavailable::addDates((int)$personid,$from,$to);
				break;
		}

		redirect('persons', Array('personid' => (int)$personid), 'availability');
	}

	function getTitle()
	{
		return 'Add date range';
	}


	function printView()
	{
		return ' ';
	}

}
?>
