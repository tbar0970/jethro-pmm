<?php
class View__Delete_Planned_Absence extends View
{
	function processView()
	{
		$absence = new Planned_Absence((int)$_REQUEST['id']);
		if ($absence && $absence->delete()) {
			add_message('Unavailability deleted', 'success');
			redirect(-1, Array(), 'rosters');
		} else {
			add_message("Error while deleting unavailability");
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