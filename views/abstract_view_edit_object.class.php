<?php
class Abstract_View_Edit_Object extends View
{
	var $_edited_object;
	var $_editing_type = '';
	var $_on_success_view = '';
	var $_on_cancel_view = '';
	var $_submit_button_label = 'Save ';
	var $_object_id_field = '';
	var $_form_classnames = '';


	function getEditingTypeFriendly()
	{
		return ucwords(str_replace('_', ' ', $this->_editing_type));
	}


	function _initEditedObject()
	{
		if (empty($this->_object_id_field)) $this->_object_id_field = $this->_editing_type.'id';
		$this->_edited_object = $GLOBALS['system']->getDBObject($this->_editing_type, (int)$_REQUEST[$this->_object_id_field]);
		if (is_null($this->_edited_object)) {
			trigger_error($this->getEditingTypeFriendly().' #'.(int)$_REQUEST[$this->_object_id_field].' does not exist', E_USER_WARNING);
			return false;
		}
		return true;
	}

	function processView()
	{
		if (!$this->_initEditedObject()) return false;
		if ($this->_processObjectEditing()) {
			add_message($this->getEditingTypeFriendly().' Updated');
			$this->_doSuccessRedirect();
		}
	}

	protected function _doSuccessRedirect()
	{
		if (array_get($_REQUEST, 'then') == 'refresh_opener') {
			?>
			<script>window.opener.location.reload();window.close();</script>
			<?php
			exit;			
		} else {
			redirect($this->_on_success_view, Array($this->_editing_type.'id' => $this->_edited_object->id)); // exits	
		}
	}

	function _processObjectEditing()
	{
		if (!empty($_POST['edit_object_submitted'])) {
			if ($this->_edited_object->haveLock()) {
				$this->_edited_object->processForm();
				if ($this->_edited_object->save()) {
					$this->_edited_object->releaseLock();
					return TRUE;
				}
			}
		}
		return FALSE;
	}
	
	function getTitle()
	{
		if (!$this->_edited_object) return _('Error');
		return _('Editing ').$this->_edited_object->toString();
	}


	function printView()
	{
		if (empty($this->_edited_object)) return;
		$show_form = true;
		if (!empty($_POST['edit_object_submitted'])) {
			if (!$this->_edited_object->haveLock()) {
				// lock expired
				if ($this->_edited_object->acquireLock()) {
					// managed to reacquire lock - ask them to try again
					?>
					<div class="failure"><?php echo _('Your changes could not be saved because your lock had expired.  Try making your changes again using the form below');?></div>
					<?php
					$show_form = true;
				} else {
					// could not re-acquire lock
					?>
					<div class="failure"><?php echo _('Your changes could not be saved because your lock has expired.  The lock has now been acquired by another user.  Wait some time for them to finish and then');?> <a href="<?php echo $_SERVER['QUERY_STRING']; ?>">_(try again)</a></div>
					<?php
					$show_form = false;
				}
			} else {
				// must have been some other problem
				$show_form = true;
			}
		} else {
			// hasn't been submitted yet
			if (!$this->_edited_object->acquireLock()) {
				?>
				<div class="failure">This <?php echo $this->getEditingTypeFriendly(); ?> cannot currently be edited because another user has the lock.  Wait some time for them to finish and then <a href="?<?php echo $_SERVER['QUERY_STRING']; ?>">try again</a></div>
				<?php
				$show_form = false;
			}
		}
		if ($show_form) {
			?>
			<form method="post" enctype="multipart/form-data" data-lock-length="<?php echo db_object::getLockLength() ?>" id="edit-<?php echo $this->_editing_type; ?>" class="<?php echo $this->_form_classnames; ?>">
				<input type="hidden" name="edit_object_submitted" value="1" />
				<?php 
				if ($then = array_get($_REQUEST, 'then')) print_hidden_field('then', $then);
				$this->_edited_object->printForm(); 
				?>
				<hr />
				<div class="form form-horizontal"><div class="control-group"><div class="controls">
					<button class="btn"><?php echo _($this->_submit_button_label); ?></button>
				<?php
				if ($this->_on_cancel_view) {
					?>
					<a class="btn cancel" href="?view=<?php echo _($this->_on_cancel_view); ?>&<?php echo $this->_editing_type; ?>id=<?php echo $this->_edited_object->id; ?>">Cancel</a>
					<?php
				}
				?>
				</div></div></div>
			</form>
			<?php
		}
	}
}