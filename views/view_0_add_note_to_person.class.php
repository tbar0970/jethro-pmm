<?php
class View__Add_Note_To_Person extends View
{
	private $_note;
	private $_person;
	private $_note_template;

	static function getMenuPermissionLevel()
	{
		return PERM_EDITNOTE;
	}

	function processView()
	{
		if (empty($_REQUEST['personid'])) {
			trigger_error(_('Cannot add note, no person ID specified'), E_USER_WARNING);
			return;
		}
		if (!is_array($_REQUEST['personid'])) {
			$this->_person = $GLOBALS['system']->getDBObject('person', $_REQUEST['personid']);
			$_REQUEST['personid'] = Array($_REQUEST['personid']);
		}
		if ($templateID = array_get($_REQUEST, 'note_template_id')) {
			$this->_note_template = new Note_Template($templateID);
		}

		$GLOBALS['system']->includeDBClass('person_note');
		$this->_note = new Person_Note();
		$this->_note->processForm();
		if (array_get($_REQUEST, 'new_note_submitted')) {
			if ($this->_note_template) {
				$this->_note_template->processNoteFieldWidgets();
				$this->_note_template->applyDataBlock($this->_note);
			}
			$success = $failure = 0;
			foreach ($_REQUEST['personid'] as $personid) {
				if ($this->_note_template && $this->_note_template->usesCustomFields()) {
					$person = new Person($personid);
					if (!$person->acquireLock()) {
						add_message("Could not acquire lock on ".$person->toString().' - note not saved', 'error');
						continue; // don't save the note if can't apply the values
					}
					$this->_note_template->applyFieldValues($person);
					if (!$person->save()) {
						add_message("Could not save values on ".$person->toString().' - note not saved', 'error');
						continue; // don't save the note if can't apply the values
					}
				}
				$this->_note->id = 0;
				$this->_note->setValue('personid', $personid);
				if ($this->_note->create()) $success++;
			}
			if ($success) {
				if (array_get($_REQUEST, 'then') == 'refresh_opener') {
					$msg = $this->_person ? 'Note added to '.$this->_person->toString() : 'Note added';
					add_message($msg);
					?>
					<script>window.opener.location.reload();window.close();</script>
					<?php
					exit;
				} else if ($this->_person) {
					add_message(_('Note added'));
					redirect('persons', Array('personid' => $this->_person->id), 'note_'.$this->_note->id); // exits
				} else {
					if ($success == count($_REQUEST['personid'])) {
						add_message(_('Note added to ').count($_REQUEST['personid'])._(' persons'));
					} else {
						add_message(_('Note successfully added to ').$success._(' of the ').count($_REQUEST['personid'])._(' selected persons'));
					}
					redirect(-1);
				}
			}
		}
	}
	
	function getTitle()
	{
		if (empty($this->_person)) {
			return;
		}
		return _('Add note to ').$this->_person->toString();
	}


	function printView()
	{
		if (empty($this->_person)) {
			return;
		}
		?>
		<form method="post" class="form-horizontal">
			<input type="hidden" name="personid" value="<?php echo $this->_person->id; ?>" />
			<?php
			if ($this->_note_template) {
				$this->_note->setTemplate($this->_note_template);
			}
			$this->_note->printForm();

			?>	
			<div class="controls">
				<input type="submit" name="new_note_submitted" class="btn" value="<?php echo _('Add Note to Person')?>" />
				<a class="btn" href="<?php echo build_url(Array('view' => 'persons', 'personid' => $this->_person->id)); ?>">Cancel</a>
		</form>
		<?php
	}
}