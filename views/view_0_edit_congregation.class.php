<?php
class View__Edit_Congregation extends View
{
	var $_congregation;

	static function getMenuPermissionLevel()
	{
		return PERM_SYSADMIN;
	}

	function processView()
	{
		$this->_congregation = $GLOBALS['system']->getDBObject('congregation', (int)$_REQUEST['congregationid']);
		if (is_null($this->_congregation)) {
			trigger_error('Congregation #'.(int)$_REQUEST['congregationid'].' does not exist', E_USER_WARNING);
			return;
		}

		$processed = FALSE;

		if (!empty($_POST['edit_congregation_submitted'])) {
			if ($this->_congregation->haveLock()) {
				$this->_congregation->processForm();
				if ($this->_congregation->save()) {
					$this->_congregation->releaseLock();
					add_message('Congregation Updated');
					$processed = TRUE;
				}
			}
		}
		
		if ($processed) {
			redirect('admin__congregations'); // exits
		}


	}
	
	function getTitle()
	{
		if ($this->_congregation) {
			return _('Editing Congregation ').$this->_congregation->toString();
		} else {
			return 'Error';
		}
	}


	function printView()
	{
		if (empty($this->_congregation)) return;
		$show_form = true;
		if (!empty($_POST['edit_congregation_submitted'])) {
			if (!$this->_congregation->haveLock()) {
				// lock expired
				if ($this->_congregation->acquireLock()) {
					// managed to reacquire lock - ask them to try again
					?>
					<div class="failure"><?php echo _('Your changes could not be saved because your lock had expired.  Try making your changes again using the form below')?></div>
					<?php
					$show_form = true;
				} else {
					// could not re-acquire lock
					?>
					<div class="failure"><?php echo _('Your changes could not be saved because your lock has expired.  The lock has now been acquired by another user.  Wait some time for them to finish and then ')?><a href="?view=_edit_congregation&congregationid=<?php echo $this->_congregation->id; ?>"><?php echo _('try again')?></a></div>
					<?php
					$show_form = false;
				}
			} else {
				// must have been some other problem
				$show_form = true;
			}
		} else {
			// hasn't been submitted yet
			if (!$this->_congregation->acquireLock()) {
				?>
				<div class="failure"><?php echo _('This congregation cannot currently be edited because another user has the lock.  Wait some time for them to finish and then ')?><a href="?view=_edit_congregation&congregationid=<?php echo $this->_congregation->id; ?>"><?php echo _('try again')?></a></div>
				<?php
				$show_form = false;
			}
		}
		if ($show_form) {
			?>
			<form method="post" class="form form-horizontal" id="congregation_form" data-lock-length="<?php echo db_object::getLockLength() ?>">
				<input type="hidden" name="edit_congregation_submitted" value="1" />
				<?php $this->_congregation->printForm(); ?>
				<div class="controls">
					<button type="submit" class="btn"><?php echo _('Update Congregation')?></button>
					<a href="?view=admin__congregations" class="btn">Cancel</a>
				</div>
			</form>

			<?php
		}
	}
}