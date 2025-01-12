<?php
require_once('db_objects/action_plan.class.php');
class View__Add_Person_To_Family extends View
{
	var $_person;
	var $_family;

	static function getMenuPermissionLevel()
	{
		return Person::allowedToAdd() ? PERM_EDITPERSON : -1;
	}

	function processView()
	{
		$this->_family = $GLOBALS['system']->getDBObject('family', $_REQUEST['familyid']);
		$GLOBALS['system']->includeDBClass('person');
		$this->_person = new Person();
		if (array_get($_REQUEST, 'new_person_submitted')) {
			$GLOBALS['system']->doTransaction('begin');
			$this->_person = new Person();
			$this->_person->processForm();
			$this->_person->setValue('familyid', $this->_family->id);
			if ($this->_person->create()) {

				if (!empty($_POST['execute_plan'])) {
					foreach ($_POST['execute_plan'] as $planid) {
						$plan = $GLOBALS['system']->getDBObject('action_plan', $planid);
						$plan->execute('person', $this->_person->id, process_widget('plan_reference_date', Array('type' => 'date')));
					}
				}

				$GLOBALS['system']->doTransaction('commit');
				add_message('New family member added');
				redirect('families', Array('familyid' => $this->_family->id)); // exits
			} else {
				$GLOBALS['system']->doTransaction('rollback');
			}
		} else {
			$this->_person->setValue('last_name', $this->_family->getValue('family_name'));
		}
	}

	function getTitle()
	{
		return 'Add Person to '.$this->_family->toString();

	}


	function printView()
	{
		?>
		<form method="post" id="add-family">
			<input type="hidden" name="new_person_submitted" value="1" />
			<input type="hidden" name="familyid" value="<?php echo ents($_REQUEST['familyid']); ?>" />
			<h3><?php echo _('New Person Details')?></h3>
			<?php
			$this->_person->printForm();

			if ($chooser = Action_Plan::getMultiChooser('execute_plan', 'add_person')) {
				?>
				<h3><?php echo _('Action Plans')?></h3>
				<p><?php echo _('Execute the following action plans for the new person:')?></p>
				<?php echo $chooser; ?>
				<p><?php echo _('Reference date for plans:')?> <?php print_widget('plan_reference_date', Array('type' => 'date'), NULL); ?></p>
				<?php
			}
			?>
			<button type="submit" class="btn"><?php echo _('Add Family Member')?></button>
			<a href="<?php echo build_url(Array('view' => 'families')); ?>" class="btn">Cancel</a>
		</form>
		<?php
	}
}