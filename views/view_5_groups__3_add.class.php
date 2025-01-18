<?php
require_once 'abstract_view_add_object.class.php';
class View_Groups__Add extends Abstract_View_Add_Object
{
	var $_create_type = 'person_group';
	var $_success_message = 'New group created';
	var $_on_success_view = 'groups';
	var $_failure_message = 'Error creating group';
	var $_submit_label = 'Create Group';
	var $_title = 'Add Person Group';

	function processView() {
		if (!empty($_REQUEST['create_another'])) {
			$this->_on_success_view = $_REQUEST['view'];
		}
		parent::processView();
	}

	static function getMenuPermissionLevel()
	{
		return PERM_EDITGROUP;
	}

	function printView()
	{
		?>
		<form method="post" class="form-horizontal" id="add-<?php echo $this->_create_type; ?>">
			<input type="hidden" name="new_<?php echo $this->_create_type; ?>_submitted" value="1" />
			<?php
			$this->_new_object->printForm();
			?>
			<hr />
			<div class="controls">
				<input class="btn" type="submit" value="<?php echo _('Save and view group');?>" />
				<input class="btn" name="create_another" type="submit" value="<?php echo _('Save group and create another');?>" />
				<a href="<?php echo build_url(Array('view' => 'groups__list_all')); ?>" class="btn">Cancel</a>
			</div>
		</form>
		<?php
	}

	function _afterCreate()
	{
		if (!empty($_POST['personid']) && is_array($_POST['personid'])) {

			// When moving from an old group to this one, the magic membership status _PRESERVE_
			// means we should look up their status in the old group and use that status in the new group.

			$mstatus = array_get($_POST, 'membership_status', NULL);
			if (!empty($_POST['remove_from_groupid'])) {
				$old_group = $GLOBALS['system']->getDBObject('person_group', (int)$_POST['remove_from_groupid']);
				if ($mstatus == '_PRESERVE_') {
					$old_memberships = $old_group->getMembers();
				}
			}

			foreach ($_POST['personid'] as $personid) {
				$newstatus = ($mstatus == '_PRESERVE_') ? $old_memberships[$personid]['membership_status_id'] : $mstatus;
				$this->_new_object->addMember((int)$personid, $newstatus);
			}

			if (!empty($_POST['remove_from_groupid'])) {
				if ($old_group) {
					$old_group->removeMembers($_POST['personid']);
				}
			}
		}
	}
}