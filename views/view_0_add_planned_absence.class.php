<?php
require_once 'abstract_view_add_object.class.php';
class View__Add_Planned_Absence extends Abstract_View_Add_Object
{
	var $_create_type = 'planned_absence';
	var $_success_message = 'Planned absence saved';
	var $_on_success_view = 'persons';
	var $_failure_message = 'Error saving planned absence';
	var $_submit_label = 'Save';
	var $_title = 'Add Planned Absence';

	static function getMenuPermissionLevel()
	{
		return PERM_EDITROSTER;
	}
	
	function getTitle()
	{
		$person = new Person((int)$_REQUEST['personid']);
		return 'Add Planned Absence for '.$person->toString();
	}

	function processView() 
	{
		parent::processView();
	}
	
	function _beforeCreate()
	{
		$this->_new_object->setValue('personid', (int)$_REQUEST['personid']);
	}

	protected function _doSuccessRedirect()
	{
		if ($this->_new_object->hasRosterAssignments()) {
			$person = new Person((int)$_REQUEST['personid']);
			add_message($person->toString().' is already assigned to roster roles during the absent period. You should edit the roster to address this.', 'warning');
		}
		redirect($this->_on_success_view, Array('personid' => $_REQUEST['personid']), 'rosters');
	}
	
}