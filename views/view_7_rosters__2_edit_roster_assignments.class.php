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
		parent::processView();
		$saved = FALSE;
		if (!empty($_POST) && !empty($this->_view)) {
			$this->_view->processAllocations($this->_start_date, $this->_end_date, true);
			add_message('Roster assignments saved');
			$saved = TRUE;
		}

		if (!empty($_SESSION['roster_backto'])) {
			bam("Got backto");
			// Somebody (probably the run sheet page) wants us to redirect back there.
			header('Location: '.urldecode($_SESSION['roster_backto']));
			unset($_SESSION['roster_backto']);
			exit;
		}

		if (!empty($_REQUEST['goback']) && !empty($_SERVER['HTTP_REFERER'])) {
			// Save where we came from in order to go back there afterwards
			$_SESSION['roster_backto'] = urlencode($_SERVER['HTTP_REFERER']);
		}

		if (($saved || !empty($_REQUEST['viewing']))) {
			// They clicked the "view roster" button - redirect back to the view page.
			redirect('rosters__display_roster_assignments');
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
