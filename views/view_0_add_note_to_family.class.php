<?php
class View__Add_Note_To_Family extends View
{
	var $_note;
	var $_family;

	static function getMenuPermissionLevel()
	{
		return PERM_EDITNOTE;
	}

	function processView()
	{
                if (empty($_REQUEST['familyid'])) {
                        trigger_error("Cannot add note, no family ID specified", E_USER_WARNING);
                        return;
                }
		$this->_family = $GLOBALS['system']->getDBObject('family', $_REQUEST['familyid']);
		$GLOBALS['system']->includeDBClass('family_note');
		$this->_note = new Family_Note();
		if (array_get($_REQUEST, 'new_note_submitted')) {
			$this->_note->processForm();
			$this->_note->setValue('familyid', $this->_family->id);
			if ($this->_note->create()) {
				add_message(_('Note added'));
				redirect('families', Array('familyid' => $this->_family->id), 'note_'.$this->_note->id); // exits
			}
		} else {
			$members = $this->_family->getMemberData();
			if (count($members) == 1) {
				add_message(_('Family has only one member, so adding note to that person instead'));
				redirect('_add_note_to_person', Array('personid' => key($members))); // exits
			}
		}
	}

	function getTitle()
	{
		return 'Add Note to '.$this->_family->toString();
	}


	function printView()
	{
		?>
		<form method="post" class="form-horizontal">
			<input type="hidden" name="new_note_submitted" value="1" />
			<input type="hidden" name="familyid" value="<?php echo ents($_REQUEST['familyid']); ?>" />
			<h3><?php echo _('New Note Details')?></h3>
			<?php
			$this->_note->printForm();
			?>
			<div class="controls">
				<button type="submit" class="btn"><?php echo _('Add Note to Family')?></button>
				<a class="btn" href="<?php echo build_url(Array('view' => 'families', 'familyid' => $this->_family->id)); ?>"><?php echo _('Cancel')?></a>
		</form>
		<?php

	}
}