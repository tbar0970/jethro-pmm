<?php
require_once 'abstract_view_edit_object.class.php';
class View__Edit_Me extends Abstract_View_Edit_Object
{
	var $_editing_type = 'staff_member';
	var $_on_success_view = 'home';
	var $_on_cancel_view = 'home';
	var $_submit_button_label = 'Update Account ';

	function _initEditedObject()
	{
		$this->_edited_object = $GLOBALS['system']->getDBObject('staff_member', $GLOBALS['user_system']->getCurrentUser('id'));
		return true;
	}
	
	function getTitle()
	{
		return _('Editing User Account for ').$this->_edited_object->toString();
	}

	function getEditingTypeFriendly() {
		return 'User Account';
	}

}