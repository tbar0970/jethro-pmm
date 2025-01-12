<?php
class View__Move_Person_To_Family extends View
{
	var $_person;

	static function getMenuPermissionLevel()
	{
		return PERM_EDITPERSON;
	}

	function processView()
	{
		$GLOBALS['system']->setFriendlyErrors(TRUE);
		$this->_person = $GLOBALS['system']->getDBObject('person', (int)$_REQUEST['personid']);
		if (!empty($_REQUEST['move_to'])) {
			if ($_REQUEST['move_to'] == 'new') {
				$old_familyid = $this->_person->getValue('familyid');
				$family = $GLOBALS['system']->getDBObject('family', (int)$this->_person->getValue('familyid'));
				$family->id = 0;
				$family->create();
				$this->_person->setValue('familyid', $family->id);
				$this->_person->save();
				add_message('New family created with same details as old family.  You should update the new family\'s details as required');
				$this->_annotateOldEmptyFamily($old_familyid, $family);
				redirect('_edit_family', Array('familyid' => $family->id)); // exits
			} else {
				if (empty($_REQUEST['familyid'])) {
					trigger_error("You must select a new family to move to, or choose to create a new family");
					return false;
				}
				$family = $GLOBALS['system']->getDBObject('family', (int)$_REQUEST['familyid']);
				if ($family) {
					$old_familyid = $this->_person->getValue('familyid');
					$this->_person->setValue('familyid', (int)$_REQUEST['familyid']);
					if ($this->_person->save()) {
						add_message('Person moved to family "'.$family->toString().'"');
						$this->_annotateOldEmptyFamily($old_familyid, $family);
						redirect('persons', Array('personid' => $this->_person->id)); // exits

					}
				}
			}

		}
	}

	private function _annotateOldEmptyFamily($old_familyid, $new_family)
	{
		$remaining_members = $GLOBALS['system']->getDBObjectData('person', Array('familyid' => $old_familyid));
		if (empty($remaining_members)) {
			$old_family = new Family($old_familyid);
			if ($GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
				// add a note
				$GLOBALS['system']->includeDBClass('family_note');
				$note = new Family_Note();
				$note->setValue('familyid', $old_familyid);
				$note->setValue('status', 'no_action');
				$note->setValue('subject', 'Archived by System');
				$note->setValue('details', 'The system is archiving this family because its last member ('.$this->_person->toString().' #'.$this->_person->id.') has been moved to another family ('.$new_family->toString().' #'.$new_family->id.')');
				$note->create();
			}

			// archive the family record
			$old_family->setValue('status', 'archived');
			$old_family->save(FALSE);
		}		
	}

	function getTitle()
	{
		return 'Editing '.$this->_person->toString();
	}


	function printView()
	{
		$show_form = true;
		if (!empty($_POST['move_to'])) {
			if (!$this->_person->haveLock()) {
				// lock expired
				if ($this->_person->acquireLock()) {
					// managed to reacquire lock - ask them to try again
					?>
					<div class="failure">Your changes could not be saved because your lock had expired.  Try making your changes again using the form below</div>
					<?php
					$show_form = true;
				} else {
					// could not re-acquire lock
					?>
					<div class="failure">Your changes could not be saved because your lock has expired.  The lock has now been acquired by another user.  Wait some time for them to finish and then <a href="?view=_edit_person&personid=<?php echo $this->_person->id; ?>">try again</a></div>
					<?php
					$show_form = false;
				}
			} else {
				// must have been some other problem
				$show_form = true;
			}
		} else {
			// hasn't been submitted yet
			if (!$this->_person->acquireLock()) {
				?>
				<div class="failure">This person cannot currently be edited because another user has the lock.  Wait some time for them to finish and then <a href="?view=_edit_person&personid=<?php echo $this->_person->id; ?>">try again</a></div>
				<?php
				$show_form = false;
			}
		}
		if ($show_form) {
			?>
			<form method="post" class="form-horizontal" data-lock-length="<?php echo db_object::getLockLength() ?>">
				<div class="control-group">
					<label class="control-label">Current Family</label>
					<div class="controls controls-text">
						<?php echo $this->_person->printFieldValue('familyid'); ?>
					</div>
				</div>
				<div class="control-group">
					<label class="control-label">New Family</label>
					<div class="controls">
						<p class="radio-list">
						<label class="radio inline">
							<input type="radio" name="move_to" value="existing" />
							Move to an existing family:
						</label>
						<?php Family::printSingleFinder('familyid'); ?>
						</p>

						<p class="radio-list">
						<label class="radio">
							<input type="radio" name="move_to" value="new" />
							Create a new family containing only this person
							<br />(Details will be copied from the old family)</span>
						</label>
						
						</p>
					</div>
				</div>
				<div class="controls">
					<button type="submit" class="btn">Go</button>
					<a class="btn" href="?view=persons&personid=<?php echo $this->_person->id; ?>">Cancel</a>
				</div>
			</form>
			<?php
		}
	}
}