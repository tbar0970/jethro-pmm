<?php
class View__Delete_Person extends View
{
	private $_person = NULL;
	private $_staff_member = NULL;
	
	const EXPLANATION = '<ul>
				<li>Change their name to "Removed"</li>
				<li>Change their status to "archived"</li>
				<li>Blank out all their fields except congregation</li>
				<li>Clear their history and notes</li>
				<li>Preserve their (anonymous) roster assignments, group memberships and attendance records</li>
			</ul>
';
	
	static function getMenuPermissionLevel()
	{
		return PERM_SYSADMIN; // TODO: check
	}

	function processView()
	{
		if ($_REQUEST['personid']) {
			$this->_person = new Person((int)$_REQUEST['personid']);
		}
		if (empty($this->_person)) trigger_error("Person not found", E_USER_ERROR); // exits
		$this->_staff_member = $GLOBALS['system']->getDBObject('staff_member', $this->_person->id);
		
		if (empty($this->_staff_member) && !empty($_POST['confirm_delete'])) {
			// delete the person altogether
			$this->_person->delete();
			
		} else if (!empty($_POST['confirm_archiveclean'])) {
			// archive and anononmize the person
			if (!$this->_person->aquireLock()) {
				add_message('This person cannot be deleted because somebody else holds the lock.  Try again later.', 'error');
				redirect('persons', Array('personid' => $this->_person->id)); // exits
			}
			$this->_person->archiveAndClean();
			add_message($this->_person->toString().' has been archived and cleaned', 'success');
			redirect('persons', Array('personid' => $this->_person->id)); // exits
			
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
		
		if ($this->_staff_member) {
			?>
			<p><?php echo _('This person has a user account and cannot be deleted altogether.')?></p>
			<p><?php echo _('You can archive and clean this person, which will')?></p>
			<?php 
			echo self::EXPLANATION;
			unset($buttons['delete']);
		} else if ($this->_person->hasRosterAssignments() || $this->_person->hasAttendance()) {
			?>
			<p><?php echo _('Deleting this person is not recommended since they have roster assignments and/or attendance records, and deleting them will affect historical statistics.')?></p>
			<p><?php echo _('It is recommended that you archive and clean the person, which will')?></p>
			<?php
			echo self::EXPLANATION;
			$buttons['delete'] = 'Delete anyway';
		}
		?>
		<form method="post">
			<input type="hidden" name="personid" value="<?php echo (int)$this->_person->id; ?>" />
		<?php
		foreach ($buttons as $key => $label) {
			?>
			<input type="button" class="btn" name="confirm_<?php echo $key; ?>" value="<?php echo ents($label); ?>" />
			<?php
		}
		?>
		</form>
		<?php
	}


}
?>
