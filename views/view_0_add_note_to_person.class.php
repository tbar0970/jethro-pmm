<?php
class View__Add_Note_To_Person extends View
{
	var $_note;
	var $_person;

	static function getMenuPermissionLevel()
	{
		return PERM_EDITNOTE;
	}

	function processView()
	{
		if (empty($_REQUEST['personid'])) {
			trigger_error("Cannot add note, no person ID specified", E_USER_WARNING);
			return;
		}
		if (!is_array($_REQUEST['personid'])) {
			$this->_person =& $GLOBALS['system']->getDBObject('person', $_REQUEST['personid']);
			$_REQUEST['personid'] = Array($_REQUEST['personid']);
		}
		$GLOBALS['system']->includeDBClass('person_note');
		$this->_note = new Person_Note();
		if (array_get($_REQUEST, 'new_note_submitted')) {
			$this->_note->processForm();
			$success = TRUE;
			foreach ($_REQUEST['personid'] as $personid) {
				$this->_note->id = 0;
				$this->_note->setValue('personid', $personid);
				$success = $success && $this->_note->create();
			}
			if ($success) {
				if ($this->_person) {
					add_message('Note added');
					redirect('persons', Array('personid' => $this->_person->id), 'note_'.$this->_note->id); // exits
				} else {
					add_message('Note added to '.count($_REQUEST['personid']).' persons');
					redirect('home');
				}
			}
		}
	}
	
	function getTitle()
	{
		if (empty($this->_person)) {
			return;
		}	
		return 'Add note to '.$this->_person->toString();
	}


	function printView()
	{
		if (empty($this->_person)) {
			return;
		}	
		?>
		<form method="post" class="form-horizontal">
			<input type="hidden" name="new_note_submitted" value="1" />
			<input type="hidden" name="personid" value="<?php echo $this->_person->id; ?>" />
			<h3>New Note Details</h3>
			<?php
			$this->_note->printForm();
			?>	
			<div class="controls">
				<button type="submit" class="btn">Add Note to Person</button>
				<a class="btn" href="<?php echo build_url(Array('view' => 'persons', 'personid' => $this->_person->id)); ?>">Cancel</a>
		</form>
		<?php
	}
}
?>
