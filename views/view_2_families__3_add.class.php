<?php
include_once 'db_objects/action_plan.class.php';
class View_Families__Add extends View
{
	var $_family;

	static function getMenuPermissionLevel()
	{
		return Person::allowedToAdd() ? PERM_EDITPERSON : -1;
	}

	function processView()
	{
		$GLOBALS['system']->includeDBClass('family');
		$this->_family = new Family();

		if (array_get($_REQUEST, 'new_family_submitted')) {

			// some initial checks
			$i = 0;
			$found_member = FALSE;
			while (isset($_POST['members_'.$i.'_first_name'])) {
				if (!empty($_POST['members_'.$i.'_first_name'])) {
					$found_member = TRUE;
				}
				$i++;
			}
			if (!$found_member) {
				add_message(_('New family must have at least one member'), 'failure');
				return FALSE;
			}
			if ($GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
				if (REQUIRE_INITIAL_NOTE && empty($_POST['initial_note_subject'])) {
					add_message(_('A subject must be supplied for the initial family note'), 'failure');
					return FALSE;
				}
			}

			$GLOBALS['system']->doTransaction('begin');

			// Create the family record itself
			$this->_family->processForm();
			$success = $this->_family->create();
			
			if ($success) {
				// Add members
				$i = 0;
				$members = Array();
				$GLOBALS['system']->includeDBClass('person');
				while (isset($_POST['members_'.$i.'_first_name'])) {
					if (!empty($_POST['members_'.$i.'_first_name'])) {
						$member = new Person();
						$member->setValue('familyid', $this->_family->id);
						$member->processForm('members_'.$i.'_');
						if (!$member->create()) {
							$success = FALSE;
							break;
						}
						$members[] =& $member;
					}
					$i++;
				}
			}

			if ($success) {
				if ($GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
					if (REQUIRE_INITIAL_NOTE || !empty($_POST['initial_note_subject'])) {
						// Add note
						if (count($members) > 1) {
							$GLOBALS['system']->includeDBClass('family_note');
							$note = new Family_Note();
							$note->setValue('familyid', $this->_family->id);
						} else {
							$GLOBALS['system']->includeDBClass('person_note');
							$note = new Person_Note();
							$note->setValue('personid', $members[0]->id);
						}
						$note->processForm('initial_note_');
						$success = $note->create();
					}
				}

				if (!empty($_POST['execute_plan'])) {
					foreach ($_POST['execute_plan'] as $planid) {
						$plan = $GLOBALS['system']->getDBObject('action_plan', $planid);
						$plan->execute('family', $this->_family->id, process_widget('plan_reference_date', Array('type' => 'date')));
					}
				}
			}

			if ($success) {
				foreach (array_get($_REQUEST, 'groups', Array()) as $groupid) {
					$group = $GLOBALS['system']->getDBObject('person_group', $groupid);
					foreach ($members as $member) {
						$group->addMember($member->id, $_REQUEST['membership_status'][$groupid]);
					}
				}
			}

			// Before committing, check for duplicates
			if (empty($_REQUEST['override_dup_check'])) {
				$this->_similar_families = $this->_family->findSimilarFamilies();
				if (!empty($this->_similar_families)) {
					$GLOBALS['system']->doTransaction('rollback');
					return;
				}
			}

			if ($success) {
				$GLOBALS['system']->doTransaction('commit');
				add_message(_('Family Created'));
				redirect('families', Array('familyid' => $this->_family->id));
			} else {
				$GLOBALS['system']->doTransaction('rollback');
				$this->_family->id = 0;
				add_message(_('Error during family creation, family not created'), 'failure');
			}
		}
	}
	
	function getTitle()
	{
		return _('Add Family');
	}


	function printView()
	{
		if (!empty($this->_similar_families)) {
			$this->printSimilarFamilies();
		} else {
			$this->printForm();
		}
	}

	function printSimilarFamilies()
	{
		$msg = count($this->_similar_families) > 1
			? _('Several families already exist that are similar to the one you are creating')
			: _('A family similar to the one you are creating already exists');
		?>
		<p class="alert alert-error"><b>Warning: <?php echo $msg; ?>.</b></p>
		<?php 
		foreach ($this->_similar_families as $family) {
			?>
			<h4><a href="<?php echo build_url(array('view' => 'families', 'familyid' => $family->id)); ?>"><?php echo _('Family'); ?>#<?php echo $family->id; ?></a></h4>
			<?php
			$family->printSummaryWithMembers();
		}
		?>

		<form method="post" class="min">
		<?php print_hidden_fields($_POST); ?>
		<input type="submit" class="btn" name="override_dup_check" value=<?php echo _('Create new family anyway');?> />
		</form>

		<form method="get" class="min">
		<input type="submit" class="btn" value=<?php echo _('Cancel family creation');?> />
		</form>
		<?php
	}

	function printForm()
	{
		$GLOBALS['system']->includeDBClass('person');
		$person = new Person();
		$person->fields['first_name']['width'] = 11;
		$person->fields['last_name']['width'] = 11;
		$person->fields['email']['width'] = 25;

		$customFields = $GLOBALS['system']->getDBObjectData('custom_field', Array('show_add_family' => 1), 'AND', 'rank');
		?>
		<form method="post" id="add-family" class="form-horizontal">
			<input type="hidden" name="new_family_submitted" value="1" />
			<div class="">

			<label><?php echo _('Family Name:'); ?></label>
			<?php $this->_family->printFieldInterface('family_name'); ?>
			
			</div>

			<div>
			<h3><?php echo _('Family Members');?></h3>
			<table class="expandable table-full-width">
			<?php
			include_once 'include/size_detector.class.php';
			if (SizeDetector::isNarrow() || count($customFields) > 0) {
				// horizontal view would get too wide if we added custom fields to it
				?>
				<tr>
					<td>
						<div class="compact-2col family-member-box">
							<label><?php echo _('First Name');?></label>
							<label><?php echo _('Last Name');?></label>
							<div><?php $person->printFieldInterface('first_name', 'members_0_'); ?></div>
							<div class="last_name preserve-value"><?php $person->printFieldInterface('last_name', 'members_0_'); ?></div>

							<label><?php echo _('Gender');?></label>
							<label><?php echo _('Age');?></label>
							<div><?php $person->printFieldInterface('gender', 'members_0_'); ?></div>
							<div><?php $person->printFieldInterface('age_bracket', 'members_0_'); ?></div>

							<label><?php echo _('Status');?></label>
							<label><?php echo _('Congregation');?></label>
							<div class="person-status preserve-value"><?php $person->printFieldInterface('status', 'members_0_'); ?></div>
							<div class="congregation"><?php $person->printFieldInterface('congregationid', 'members_0_'); ?></div>

							<label><?php echo _('Mobile');?></label>
							<label><?php echo _('Email');?></label>
							<div><?php $person->printFieldInterface('mobile_tel', 'members_0_'); ?></div>
							<div><?php $person->printFieldInterface('email', 'members_0_'); ?></div>

						<?php
						$field = new Custom_Field();
						foreach ($customFields as $fieldID => $fDetails) {
							$field->populate($fieldID, $fDetails);
							?>
							<label class="fullwidth"><?php $field->printFieldValue('name'); ?></label>
							<div class="fullwidth"><?php $field->printWidget('', Array(), 'members_0_'); ?></div>
							<?php
						}
						?>
							
						</div>
					</td>
				</tr>
				<?php
			} else {
				?>
				<thead>
					<tr>
						<td><?php echo _('First Name');?></td>
						<td><?php echo _('Last Name');?></td>
						<td><?php echo _('Gender');?></td>
						<td><?php echo _('Age');?></td>
						<td><?php echo _('Status');?></td>
						<td><?php echo _('Cong.');?></td>
						<td><?php echo _('Mobile Tel');?></td>
						<td><?php echo _('Email');?></td>
					</tr>
				<thead>
				<tbody>
					<tr>
						<td><?php $person->printFieldInterface('first_name', 'members_0_'); ?></td>
						<td class="last_name preserve-value"><?php $person->printFieldInterface('last_name', 'members_0_'); ?></td>
						<td><?php $person->printFieldInterface('gender', 'members_0_'); ?></td>
						<td><?php $person->printFieldInterface('age_bracket', 'members_0_'); ?></td>
						<td class="person-status preserve-value"><?php $person->printFieldInterface('status', 'members_0_'); ?></td>
						<td class="congregation preserve-value"><?php $person->printFieldInterface('congregationid', 'members_0_'); ?></td>
						<td><?php $person->printFieldInterface('mobile_tel', 'members_0_'); ?></td>
						<td><?php $person->printFieldInterface('email', 'members_0_'); ?></td>
					</tr>
				</tbody>
				<?php
			}
			?>
			</table>
			</div>

			<h3><?php echo _('Family Details');?> <small><?php echo _('(optional)');?></small></h3>
			<?php
			$this->_family->fields['family_name']['readonly'] = 1;
			$this->_family->printForm();
			$this->_family->fields['family_name']['readonly'] = 0;
			?>

		<?php
		if ($GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
			?>
			<div <?php echo REQUIRE_INITIAL_NOTE ? '' : 'class="optional"'; ?>>
			<h3><?php echo _('Initial Note');?> <small>(<?php echo REQUIRE_INITIAL_NOTE ? _('required') : _('optional'); ?>)</small></h3>
			<?php
				$GLOBALS['system']->includeDBClass('family_note');
				$note = new Family_Note();
				$note->printForm('initial_note_');
			?>
			</div>
			<?php
		}

		$groups = $GLOBALS['system']->getDBObjectData('person_group', Array('!show_add_family' => 'no'));
		if ($groups) {
			?>
			<h3><?php echo _('Groups'); ?></h3>
			<p><?php echo _('Add the members of this family:'); ?></p>
			<div class="indent-left">
				<?php
				foreach ($groups as $groupid => $group) {
					?>
					<label class="checkbox">
						<input name="groups[]" value="<?php echo $groupid; ?>"
							   type="checkbox"
							   <?php if ($group['show_add_family'] == 'selected') echo 'checked="checked"'; ?>
						/>
						<?php
						echo _('as');
						Person_Group::printMembershipStatusChooser('membership_status['.$groupid.']');
						echo _('of');
						echo ' '.ents($group['name']);
						?>
					</label>
					<?php
				}
				?>
			</div>
			<?php
		}

		if ($plan_chooser = Action_Plan::getMultiChooser('execute_plan', 'create_family')) {
			?>
			<h3><?php echo _('Action plans');?> <small><?php echo _('(optional)');?></small></h3>
			<p><?php echo _('Execute the following action plans for the new family:');?> </p>
			<div class="indent-left">
				<?php echo $plan_chooser; ?>
				<p><?php _('Reference date for plans:')?> <?php print_widget('plan_reference_date', Array('type' => 'date'), NULL); ?></p>
			</div>
			<?php
		}
		?>
		<h3><?php echo _('Create');?></h3>
			<div class="align-right">
				<input type="submit" class="btn" value=<?php echo _('Create Family');?> />
				<input type="button" class="back btn" value=<?php echo _('Cancel');?> />
			</div>
		</form>
		<?php
	}
}
?>
