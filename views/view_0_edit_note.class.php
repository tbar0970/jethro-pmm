<?php
class View__Edit_Note extends View
{
	var $_note;
	var $_person;
	var $_family;

	static function getMenuPermissionLevel()
	{
		return PERM_VIEWMYNOTES;
	}

	function processView()
	{
		$this->_note = $GLOBALS['system']->getDBObject($_REQUEST['note_type'].'_note', (int)$_REQUEST['noteid']);
		if ((!$GLOBALS['user_system']->havePerm(PERM_EDITNOTE))
				&& ($this->_note->getValue('assignee') != $GLOBALS['user_system']->getCurrentUser('id'))) {
			trigger_error("Current user does not have permission to edit note #".$this->_note->id);
			return;
		}


		if (!empty($_POST['delete_note']) && $this->_note->canBeDeleted() && $GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
			if ($this->_note->delete()) {
				add_message(_('Note deleted'), 'success');
				$this->redirectAfterEdit();
			} else {
				add_message(_('Failed to delete note'), 'failure');
			}
			return;
		}
		$note_type = ($_REQUEST['note_type'] == 'family') ? 'family_note' : 'person_note';
		$this->_note = $GLOBALS['system']->getDBObject($note_type, $_REQUEST['noteid']);
		if ($_REQUEST['note_type'] == 'family') {
			$this->_family = $GLOBALS['system']->getDBObject('family', $this->_note->getValue('familyid'));
		} else {
			$this->_person = $GLOBALS['system']->getDBObject('person', $this->_note->getValue('personid'));
		}

		if (!empty($_POST['update_note_submitted'])) {
			$GLOBALS['system']->doTransaction('begin');
			$success = TRUE;
			if ($this->_note->haveLock()) {
				$fieldsToSave = array_keys($this->_note->fields);
				if (!$this->_note->canEditOriginal()) {
					$fieldsToSave = array_diff($fieldsToSave, Array('subject', 'details'));
				}
				$this->_note->processForm('', $fieldsToSave);
				if (!$this->_note->save()) {
					$success = FALSE;
				}

				if ($success) {
					$GLOBALS['system']->includeDBClass('note_comment');
					$comment = new Note_Comment();
					$comment->processForm();
					if (trim($comment->getValue('contents')) != '') {
						$comment->setValue('noteid', $this->_note->id);
						if (!$comment->create()) {
							$success = FALSE;
						}
					}
				}
			} else {
				add_message('Lock on note object not held', 'failure');
				$success = FALSE;
			}

			if ($success) {
				$this->_note->releaseLock();
				$GLOBALS['system']->doTransaction('commit');
				add_message('Note Updated');
				$this->redirectAfterEdit();

			} else {
				add_message('Errors while processing, could not save changes', 'failure');
				$GLOBALS['system']->doTransaction('rollback');
			}
		}
	}

	private function redirectAfterEdit() {
		$next_view = array_get($_REQUEST, 'back_to', '');
		if (empty($next_view)) {
			$next_view = ($_REQUEST['note_type'] == 'family') ? 'families' : 'persons';
		}
		switch ($next_view) {
			case 'persons':
				$params = Array('personid' => $this->_note->getValue('personid'));
				$hash = 'note_'.$this->_note->id;
				break;
			case 'families':
				$params = Array('familyid' => $this->_note->getValue('familyid'));
				$hash = 'note_'.$this->_note->id;
				break;
			default:
				$params = Array();
				$hash = '';
		}
		redirect($next_view, $params + Array('*' => NULL), $hash); // exits

	}

	function getTitle()
	{
		if ($this->_person) {
			return _('Editing Person Note for ').$this->_person->toString();
		} else {
			return _('Editing Note for ').$this->_family->toString();
		}
	}


	function printView()
	{
		$show_form = true;
		if (!empty($_POST['update_note_submitted'])) {
			if (!$this->_note->haveLock()) {
				// lock expired
				if ($this->_note->acquireLock()) {
					// managed to reacquire lock - ask them to try again
					print_message(_('Your changes could not be saved because your lock had expired. ')._('Try making your changes again using the form below'), 'failure');
					$show_form = true;
				} else {
					print_message(_('Your changes could not be saved because your lock had expired. ')._('The lock has now been acquired by another user. ')._('Wait some time for them to finish and then ').'<a href="?view=_edit_note&noteid='.$this->_note->id.'">'._('try again').'</a>', 'failure', true);
					// could not re-acquire lock
					$show_form = false;
				}
			} else {
				// must have been some other problem
				$show_form = true;
			}
		} else {
			// hasn't been submitted yet
			if (!$this->_note->acquireLock()) {
				print_message('This note cannot currently be edited because another user has the lock.  Wait some time for them to finish and then <a href="?view=_edit_note&noteid='.$this->_note->id.'&note_type='.$_REQUEST['note_type'].'">try again</a>', 'failure', true);
				$show_form = false;
			}
		}

		if ($show_form && !empty($_REQUEST['edit_original'])) {
			print_message('NB: Notes are designed to accumulate as a historical record, so they should usually only be edited to correct a mistake', '');
			?>
			<form method="post" class="well" action="<?php echo build_url(Array('edit_original' => NULL)); ?>">
				<input type="hidden" name="update_note_submitted" value="1" />
				<?php
				$this->_note->printForm();
				?>
				<div class="control-group form-horizontal">
					<div class="controls">
						<input type="submit" class="btn" value="Save" />
						<input type="button" value="Cancel" class="btn back" />
					</div>
				</div>
			</form>
			<?php

		} else {
			$show_edit_link = FALSE;
			$d  = $GLOBALS['system']->getDBObjectData(get_class($this->_note), Array('id' => $this->_note->id));
			foreach ($d as $id => $entry) {
				$dummy =& $this->_note;
				include 'templates/single_note.template.php';
			}
		}
	}
}