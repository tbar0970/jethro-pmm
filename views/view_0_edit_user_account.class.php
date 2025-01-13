<?php
class View__Edit_User_Account extends View
{
	var $_staff_member;

	static function getMenuPermissionLevel()
	{
		return PERM_SYSADMIN;
	}

	function processView()
	{
		$this->_staff_member = $GLOBALS['system']->getDBObject('staff_member', $_REQUEST['staff_member_id']);
		if (!empty($_POST['edit_staff_submitted'])) {
			if ($this->_staff_member->haveLock()) {
				$this->_staff_member->processForm();
				if ($this->_staff_member->checkUniqueUsername() && $this->_staff_member->save()) {
					$this->_staff_member->releaseLock();
					add_message('Account Updated ');
					redirect('admin__user_accounts'); // exits
				}
			}
		}
	}
	
	function getTitle()
	{
		return _('Editing User Account for ').$this->_staff_member->toString();
	}

	function printView()
	{
		$show_form = true;
		if (!empty($_POST['edit_staff_submitted'])) {
			if (!$this->_staff_member->haveLock()) {
				// lock expired
				if ($this->_staff_member->acquireLock()) {
					// managed to reacquire lock - ask them to try again
					?>
					<div class="failure"><?php echo _('Your changes could not be saved because your lock had expired. Try making your changes again using the form below')?></div>
					<?php
					$show_form = true;
				} else {
					// could not re-acquire lock
					?>
					<div class="failure"><?php echo _('Your changes could not be saved because your lock has expired.  The lock has now been acquired by another user.  Wait some time for them to finish and then')?> <a href="?view=_edit_person&personid=<?php echo $this->_staff_member->id; ?>"><?php echo _('try again')?></a></div>
					
					<?php
					$show_form = false;
				}
			} else {
				// must have been some other problem
				$show_form = true;
			}
		} else {
			// hasn't been submitted yet
			if (!$this->_staff_member->acquireLock()) {
				?>
				<div class="failure"><?php echo _('This person cannot currently be edited because another user has the lock. '). _('Wait some time for them to finish and then')?><a href="?view=_edit_person&personid=<?php echo $this->_staff_member->id; ?>"><?php echo _('try again')?></a></div>
				<?php
				$show_form = false;
			}
		}
		if ($show_form) {
			?>
			<form method="post" id="person_form" class="form-horizontal" data-lock-length="<?php echo db_object::getLockLength() ?>">
				<input type="hidden" name="edit_staff_submitted" value="1" />
				<?php $this->_staff_member->printForm(); ?>
				<div class="controls">
					<button type="submit"  class="btn"><?php echo _('Update Account')?></button>
					<a href="?view=persons&personid=<?php echo $this->_staff_member->id; ?>" class="btn">Cancel</a>
				</div>
			</form>
			<?php
		}
	}
}