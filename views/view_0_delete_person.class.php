<?php
class View__Delete_Person extends View
{
	private $_person = NULL;
	private $_staff_member = NULL;
	private $_notes = NULL;
	
	const EXPLANATION = '<ul>
				<li>Change their name to "[Removed]"</li>
				<li>Change their status to "archived"</li>
				<li>Blank out all their fields and custom fields, except congregation and age bracket</li>
				<li>Clear their history, notes and photo</li>
				<li>Do the same for their family, if the family has no remaining non-archived members</li>
				<li>Preserve their (anonymous) roster assignments, group memberships and attendance records</li>
			</ul>
';
	
	static function getMenuPermissionLevel()
	{
		return PERM_SYSADMIN;
	}

	function processView()
	{
		if ($_REQUEST['personid']) {
			$this->_person = new Person((int)$_REQUEST['personid']);
		}
		if (empty($this->_person)) trigger_error("Person not found", E_USER_ERROR); // exits
		$this->_staff_member = $GLOBALS['system']->getDBObject('staff_member', $this->_person->id);

		$this->_notes = $GLOBALS['system']->getDBObjectData(
											'person_note',
											Array('personid' => $this->_person->id, 'status' => 'pending'),
											'AND'
			  	  );
		$family = $GLOBALS['system']->getDBObject('family', $this->_person->getValue('familyid'));
		$members = $family->getMemberData();
		if (count($members) == 1) {
			$fnotes = $GLOBALS['system']->getDBObjectData(
											'family_note',
											Array('familyid' => $family->id, 'status' => 'pending'),
											'AND'
					  );
			$this->_notes = array_merge($this->_notes, $fnotes);
		}

		if (!empty($pnotes)) {

		}

		// TODO: check for active notes on the person or (if they are single-person family) the family.
		// If so, tell the user to resolve the notes first.
		
		if (empty($this->_staff_member) && !empty($_POST['confirm_delete'])) {
			// delete the person altogether
			$this->_person->delete();
			add_message($this->_person->toString().' has been deleted', 'success');
			redirect('home');
			
		} else if (!empty($_POST['confirm_archiveclean'])) {
			// archive and anononmize the person
			if (!$this->_person->acquireLock()) {
				add_message('This person cannot be deleted because somebody else holds the lock.  Try again later.', 'error');
				redirect('persons', Array('personid' => $this->_person->id)); // exits
			}
			$message = $this->_person->toString().' has been archived and cleaned';
			$res = $this->_person->archiveAndClean();
			if ($res == 2) $message .= ' and so has their family';
			if ($res == 1) $message .= ' but their family has a remaining member and has been left untouched';
			if ($res) {
				add_message($message, 'success');
				redirect('persons', Array('personid' => $this->_person->id)); // exits
			}

		}
	}

	public function getTitle()
	{
		return _('Delete ').$this->_person->toString();
	}

	public function printView()
	{
		$buttons = Array(
					'delete' => _('Delete altogether'),
					'archiveclean' => _('Archive and Clean'),
					);
		if ($this->_notes) {
			?>
			<p>
				<?php
				echo _('This person cannot be deleted because they or their family have notes requiring action. ');
				echo _('Resolve the note(s) and then try again.');
				?>
			</p>
			<button type="button" class="btn back">Back</button>
			<?php
			return;
		} else if ($this->_staff_member) {
			?>
			<p><?php echo _('This person has a user account and cannot be deleted altogether.')?></p>
			<p><?php echo _('You can archive and clean this person, which will')?>
			<?php 
			echo self::EXPLANATION;
			echo '</p>';
			unset($buttons['delete']);
		} else if (Roster_Role_Assignment::hasAssignments($this->_person->id) || $this->_person->getAttendance()) {
			?>
			<p><?php echo _('Deleting this person is not recommended since they have roster assignments and/or attendance records, and deleting them will affect historical statistics.')?></p>
			<p><?php echo _('It is recommended that you archive and clean the person, which will')?>
			<?php
			echo self::EXPLANATION;
			echo '</p>';
			$buttons['delete'] = 'Delete anyway';
		}
		?>
		<form method="post">
			<input type="hidden" name="personid" value="<?php echo (int)$this->_person->id; ?>" />
		<?php
		foreach ($buttons as $key => $label) {
			?>
			<input type="submit" class="btn" name="confirm_<?php echo $key; ?>" value="<?php echo ents($label); ?>" />
			<?php
		}
		?>
		<button type="button" class="btn back">Cancel</button>

		</form>
		<?php
	}


}
?>
