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
		if (!empty($_SESSION['roster_backto'])) {
			// Somebody (probably the run sheet page) wants us to redirect back there.
			add_message('Roster assignments saved');
			header('Location: '.ents($_SESSION['roster_backto']));
			unset($_SESSION['roster_backto']);
			exit;
		}

		if (!empty($_REQUEST['goback'])) {
			// Save where we came from in order to go back there afterwards
			$_SESSION['roster_backto'] = $_SERVER['HTTP_REFERER'];
		}

		if (!empty($_REQUEST['viewing']) && $_REQUEST['view'] == 'rosters__edit_roster_assignments') {
			// We're finished editing, redirect back to the view page.
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
