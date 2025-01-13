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
		if (!empty($_REQUEST['viewing'])) {
			// They are editing, but clicked the "view roster" button, cancelling the edit. Release locks and redirect to View.
			$this->releaseLocks();
			redirect('rosters__display_roster_assignments', Array('viewing' => NULL));
			return;
		}
		if (!empty($_REQUEST['goback']) && empty($_SESSION['roster_backto'])) {
			// Save where we came from in order to go back there afterwards
			$_SESSION['roster_backto'] = ($_SERVER['HTTP_REFERER']);
		}

		parent::processView();

		if (!empty($_POST) && !empty($this->_view)) {
			if ($this->_view->processAllocations($this->_start_date, $this->_end_date, true)) {
				add_message("Roster assignments saved");
				if (!empty($_SESSION['roster_backto'])) {
					// Somebody (probably the run sheet page) wants us to redirect back there.
					header('Location: '.($_SESSION['roster_backto']));
					unset($_SESSION['roster_backto']);
					exit;
				} else {
					redirect('rosters__display_roster_assignments', Array('editing' => NULL));
				}
			}
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

	private function releaseLocks()
	{
		if (!empty($_REQUEST['viewid'])) {
			$this->_view = $GLOBALS['system']->getDBObject('roster_view', (int)$_REQUEST['viewid']);
			$this->_view->releaseLocks();
		}
	}

}

