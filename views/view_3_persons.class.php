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
				$this->_family = $GLOBALS['system']->getDBObject('family', $this->_person->getValue('familyid'));
			}
		}
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