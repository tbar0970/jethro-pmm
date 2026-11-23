<?php
class View_Persons extends View
{
	// for viewing one persons
	var $_person;
	var $_family;

	function processView()
	{
		if (!empty($_REQUEST['personid'])) {
			$this->_person = $GLOBALS['system']->getDBObject('person', (int)$_REQUEST['personid']);
			if ($this->_person) {
				$this->_family = $this->_person->getFamily();
				$this->_processShareConsent();
			}
		}
	}

	/**
	 * Staff consent edits from the person record's Sharing tab (#792).
	 */
	private function _processShareConsent()
	{
		if (!array_get($_POST, 'share_consent_submitted') || !$GLOBALS['user_system']->havePerm(PERM_EDITGROUP)) {
			return;
		}
		$GLOBALS['system']->includeDBClass('person_group');
		Person_Group::applyShareConsentCheckboxes(
			(int)$this->_person->id,
			Person_Group::getGroups((int)$this->_person->id),
			(array)array_get($_POST, 'share_visible', Array()),
			(array)array_get($_POST, 'share_contact', Array()),
			(array)array_get($_POST, 'share_inherit_visible', Array()),
			(array)array_get($_POST, 'share_inherit_contact', Array()),
			(array)array_get($_POST, 'share_rendered', Array())
		);
		add_message('Sharing settings updated', 'success');
	}


	function getTitle()
	{
		if ($this->_person) {
			return _('Viewing Person: ').$this->_person->toString();
		} else {
			return _('Person not found');
		}
	}


	function printView()
	{
		if ($this->_person) {
			$person =& $this->_person;
			$family =& $this->_family;
			include dirname(dirname(__FILE__)).'/templates/view_person.template.php';
		}
	}
}