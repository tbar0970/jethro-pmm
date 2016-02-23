<?php
class View__Add_User_Account extends View
{
	var $_sm;
	var $_sm_fields = Array('username', 'password', 'active', 'permissions', 'restrictions');

	static function getMenuPermissionLevel()
	{
		return PERM_SYSADMIN;
	}

	function processView()
	{
		$GLOBALS['system']->includeDBClass('staff_member');
		$this->_sm = new Staff_Member();

		if (!empty($_REQUEST['new_sm_submitted'])) {
			if (empty($_REQUEST['personid'])) {
				trigger_error('You must choose a person record to create the user account for');
				return;
			}
			$person =& $GLOBALS['system']->getDBObject('person', $_REQUEST['personid']);
			$this->_sm->processForm('', $this->_sm_fields);
			if ($this->_sm->checkUniqueUsername()) {
				if ($this->_sm->createFromChild($person)) {
					add_message('User account Added');
					redirect('admin__user_accounts');
				} else {
					trigger_error('Failed to create user account');
				}
			}
		}
	}

	function getTitle()
	{
		return 'Add User Account';
	}


	function printView()
	{
		?>
		<form method="post" class="form form-horizontal" action="" novalidate>
			<input type="hidden" name="new_sm_submitted" value="1" />
			<div class="control-group">
				<label class="control-label">Person Record</label>
				<div class="controls">
					<?php Person::printSingleFinder('personid', NULL) ?>
					<p class="help-inline">If the user does not yet exist in the system as a person, you must <a href="?view=families__add">add them first</a></p>
				</div>
			</div>
		<?php
		foreach ($this->_sm_fields as $field) {
			?>
			<div class="control-group">
				<label class="control-label"><?php echo $this->_sm->getFieldLabel($field); ?></label>
				<div class="controls">
					<?php $this->_sm->printFieldInterface($field);?>
				</div>
			</div>
			<?php
		}
		?>
			<div class="controls">
				<input type="submit" class="btn" value="Create user account" />
				<input type="button" class="btn back" value="Cancel" />
			</div>
		</form>
		<?php
	}
}
?>
