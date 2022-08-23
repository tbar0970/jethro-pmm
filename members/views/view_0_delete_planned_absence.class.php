<?php
class View__Delete_Planned_Absence extends View
{
	function processView()
	{
		$absence = new Planned_Absence((int)$_REQUEST['id']);

		$person = new Person($GLOBALS['user_system']->getCurrentPerson('id'));
		$fmembers = $person->getFamily()->getMemberData();
		if (!isset($fmembers[$absence->getValue('personid')])) {
			trigger_error(E_USER_ERROR, 'Attempt to delete absence for a person outside the users family');
			exit;
		}

		if ($absence && $absence->delete()) {
			add_message('Planned absence deleted', 'success');
			redirect(-1, Array(), 'rosters');
		} else {
			add_message("Error while deleting planned absence");
		}
	}

	function printView()
	{
	}

	public function getTitle()
	{
		return _('Delete ').$this->_person->toString();
	}

}