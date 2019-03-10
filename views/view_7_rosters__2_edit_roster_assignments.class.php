<?php
require_once str_replace('2_edit', '1_display', __FILE__);
class View_Rosters__Edit_Roster_Assignments extends View_Rosters__Display_Roster_Assignments
{
	var $_editing = true;

	static function getMenuPermissionLevel()
	{
		return PERM_EDITROSTER;
	}

	function processView()
	{
		if (!empty($_REQUEST['viewing']) && $_REQUEST['view'] == 'rosters__edit_roster_assignments') {
			redirect('rosters__display_roster_assignments');
		}

		parent::processView();

		if (!empty($_POST) && !empty($this->_view)) {
			$this->_view->processAllocations($this->_start_date, $this->_end_date, true);
		}
	}

	function getTitle()
	{
		if ($this->_view) {
			return 'Edit Roster Assignments for "'.$this->_view->getValue('name').'"';
		} else {
			return 'Edit Roster Assignments';
		}
	}

}
?>
