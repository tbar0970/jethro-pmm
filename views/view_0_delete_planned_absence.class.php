<?php
class View__Delete_Planned_Absence extends View
{
	function processView()
	{
		$absence = new Planned_Absence((int)$_REQUEST['id']);
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