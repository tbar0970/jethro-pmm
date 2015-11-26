<?php
require_once 'members/views/view_0_edit_ical.class.php';
class View__Manage_Ical extends View__Edit_Ical
{
	function _loadPerson()
	{
		$this->person = $GLOBALS['system']->getDBObject('person', $GLOBALS['user_system']->getCurrentUser('id'));	
	}
}
