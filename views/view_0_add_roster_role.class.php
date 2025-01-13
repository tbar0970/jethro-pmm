<?php
class View__Add_Roster_Role extends View
{
	var $_role;

	static function getMenuPermissionLevel()
	{
		return PERM_MANAGEROSTERS;
	}

	function processView()
	{
		$GLOBALS['system']->includeDBClass('roster_role');
		$this->_role = new Roster_Role();
		
		if (!empty($_REQUEST['new_role_submitted'])) {
			$this->_role->processForm();
			if ($this->_role->create()) {
				add_message('Role added');
				redirect('rosters__define_roster_roles', Array()); // exits		
			}
		}
	}
	
	function getTitle()
	{
		return 'Add Roster Role';
	}


	function printView()
	{
		?>
		<form method="post" class="form-horizontal">
			<input type="hidden" name="new_role_submitted" value="1" />
			<h3>New Role Details</h3>
			<?php
			$this->_role->printForm();
			?>
			<div class="controls">
				<button type="submit" class="btn">Add Role</button>
				<button type="button" class="btn back">Cancel</button>
			</div>
		</form>
		<?php

	}
}