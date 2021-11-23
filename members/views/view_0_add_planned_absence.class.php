<?php
class View__Add_Planned_Absence extends View
{
	private $_create_type = 'planned_absence';
	private $_success_message = 'Planned absence saved';
	private $_on_success_view = 'persons';
	private $_failure_message = 'Error saving planned absence';
	private $_submit_label = 'Save';
	private $_title = 'Add Planned Absence';

	var $_new_object;

	private function _getUsersFamilyMembers()
	{
		$person = new Person($GLOBALS['user_system']->getCurrentPerson('id'));
		return $person->getFamily()->getMemberData();
	}

	function processView()
	{
		$GLOBALS['system']->includeDBClass($this->_create_type);
		$this->_new_object = new $this->_create_type();

		if (array_get($_REQUEST, 'new_'.$this->_create_type.'_submitted')) {
			if (array_diff($_REQUEST['personid'], array_keys($this->_getUsersFamilyMembers()))) {
				trigger_error(E_USER_ERROR, 'Attempt to add absence for a person outside the users family');
				exit;
			}

			$GLOBALS['system']->doTransaction('BEGIN');
			$this->_new_object->processForm();
			foreach ($_REQUEST['personid'] as $personid) {
				$x = clone $this->_new_object;
				$x->setValue('personid', $personid);
				if ($x->hasRosterAssignments()) {
					$p = new Person($personid);
					add_message("Absence could not be saved because ".$p->toString().' is already assigned to roster roles during the absent period. Arrange a swap or subsitutute, then try again', 'failure');
					$GLOBALS['system']->doTransaction('ROLLBACK');
					return;
				}
				if (!$x->create()) {
					add_message("Error saving planned absences", 'failure');
					$GLOBALS['system']->doTransaction('ROLLBACK');
					return;
				}
			}
			$GLOBALS['system']->doTransaction('COMMIT');
			add_message("Planned absence saved", 'success');
			redirect('rosters');

		}
	}

	

	function printView()
	{
		?>
		<form method="post" id="add-<?php echo $this->_create_type; ?>">
			<input type="hidden" name="new_<?php echo $this->_create_type; ?>_submitted" value="1" />
			<?php
			$members = $this->_getUsersFamilyMembers();
			if (count($members) > 1) {
				?>
				<div class="form-horizontal">
					<div class="control-group">
						<label class="control-label">
							For
						</label>
						<div class="controls">
							<?php
							foreach ($members as $id => $detail) {
								?>
								<label class="checkbox">
									<input type="checkbox" name="personid[]" value="<?php echo $id; ?>" checked="checked" />
									<?php echo ents($detail['first_name'].' '.$detail['last_name']); ?>
								</label>
								<?php
							}
							?>
						</div>
					</div>
				</div>
				<?php
			} else {
				print_hidden_field('personid', $person->id);
			}
			
			$this->_new_object->printForm();
			?>
			<div class="form-horizontal"><div class="controls">
				<button type="submit" class="btn"><?php echo _($this->_submit_label); ?></button>
				<button type="button" class="btn back"><?php echo _('Cancel');?></button>
			</div></div>
		</form>
		<?php
	}


	
	function getTitle()
	{
		return 'Add Planned Absence';
	}

}