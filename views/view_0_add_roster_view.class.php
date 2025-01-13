<?php
class View__Add_Roster_View extends View
{
	var $_view;

	static function getMenuPermissionLevel()
	{
		return PERM_MANAGEROSTERS;
	}

	function processView()
	{
		$GLOBALS['system']->includeDBClass('roster_view');
		$this->_view = new Roster_View();
		if (!empty($_REQUEST['new_view_submitted'])) {
			$this->_view->processForm();
			if ($this->_view->create()) {
				add_message('View added');
				redirect('rosters__define_roster_views', Array()); // exits
			}
		}
	}

	function getTitle()
	{
		return 'Add Roster View';
	}


	function printView()
	{
		?>
		<form method="post" class="form-horizontal">
			<input type="hidden" name="new_view_submitted" value="1" />
			<h3>New View Details</h3>
			<?php
			$this->_view->printForm();
			?>
			<div class="controls">
				<input type="submit" class="btn" value="Add View" />
				<input type="button" class="btn back" value="Cancel" />
			</div>
		</form>
		<?php

	}
}