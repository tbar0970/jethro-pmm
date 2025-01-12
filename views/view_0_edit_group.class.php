<?php
require_once 'abstract_view_edit_object.class.php';
class View__Edit_Group extends Abstract_View_Edit_Object
{
	var $_editing_type = 'person_group';
	var $_on_success_view = 'groups';
	var $_on_cancel_view = 'groups';
	var $_submit_button_label = 'Update Group ';
	var $_object_id_field = 'groupid';

	static function getMenuPermissionLevel()
	{
		return PERM_EDITGROUP;
	}

	function __construct()
	{
		$this->_on_cancel_view = array_get($_REQUEST, 'back_to', 'groups');
	}

	function _processObjectEditing()
	{
		$mod_count = 0;
		$processed = FALSE;
		switch (array_get($_REQUEST, 'action')) {
			case 'add_member':
			case 'add_members':
				$personids = array_get($_POST, 'personid', Array());
				list($status_options, $default_status) = Person_Group::getMembershipStatusOptionsAndDefault();
				$mstatus = array_get($_POST, 'membership_status', $default_status);

				// When moving from an old group to this one, the magic membership status _PRESERVE_
				// means we should look up their status in the old group and use that status in the new group.
				if (!empty($_POST['remove_from_groupid'])) {
					$old_group = $GLOBALS['system']->getDBObject('person_group', (int)$_POST['remove_from_groupid']);
					if ($mstatus == '_PRESERVE_') {
						$old_memberships = $old_group->getMembers();
					}
				}

				// overwrite_membership means if they are already in the group with a different status,
				// their membership status will be updated.  Used for single-person actions but not bulk.
				$overwrite = array_get($_POST, 'overwrite_membership');
				if (!empty($personids)) {
					if (!is_array($personids)) {
						$personids = Array($personids);
					}
					foreach ($personids as $personid) {
						$new_member = $GLOBALS['system']->getDBObject('person', (int)$personid);
						$newstatus = ($mstatus == '_PRESERVE_') ? $old_memberships[$personid]['membership_status_id'] : $mstatus;
						if ($new_member->id) {
							if ($this->_edited_object->addMember((int)$personid, $newstatus, $overwrite)) {
								$mod_count++;
							}
						}
					}
					$verb = !empty($_POST['remove_from_groupid']) ? 'moved' : 'added';
					if (count($personids) > 1) {
						add_message($mod_count.' persons '.$verb.' to group');
					} else {
						add_message('Person '.$verb.' to group');
					}
				}

				if (!empty($_POST['remove_from_groupid']) && !empty($old_group)) {
					$old_group->removeMembers($_POST['personid']);
				}

				$processed = TRUE;
				break;

			case 'remove_member':
			case 'remove_members':
				$personids = array_get($_POST, 'personid');
				if (!empty($personids)) {
					if (!is_array($personids)) {
						$personids = Array($personids);
					}
					foreach ($personids as $personid) {
						if ($this->_edited_object->removeMember((int)$personid)) {
							$mod_count++;
						}
					}
					if (count($personids) > 1) {
						add_message($mod_count.' persons removed from group');
					} else {
						add_message('Person removed from group');
					}
					$processed = TRUE;
				}
				break;

			case 'delete':
				if (array_get($_POST, 'action') == 'delete') { // must be POSTed
					$GLOBALS['user_system']->checkPerm(PERM_EDITGROUP);
					$name = $this->_edited_object->toString();
					if ($this->_edited_object->delete()) {
						add_message('Group "'.$name.'" deleted');
						redirect('groups__list_all', Array('groupid' => NULL, 'action' => NULL)); // exits
					} else {
						redirect('groups', Array('groupid' => $this->_edited_object->id));
					}
				} else {
					add_message('Groups can only be deleted in the same browser window. Please try again');
				}
				break;
		}


		if (!$processed) {
			// normal group edit
			$GLOBALS['user_system']->checkPerm(PERM_EDITGROUP);
			$processed = parent::_processObjectEditing();
		}
		
		if ($processed) {
		
			switch (array_get($_REQUEST, 'back_to')) {
				case 'persons':
					redirect('persons', Array('personid' => (int)reset($personids)), 'groups');
				case 'groups__list_all':
					redirect('groups__list_all', Array('groupid' => NULL, 'action' => NULL)); // exits
				case 'groups':
				default:
					redirect('groups', Array('groupid' => $this->_edited_object->id)); // exits
			}
		}


	}
	
}