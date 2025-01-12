<?php
class View__Persons_Bulk_Update extends View
{
	var $_person;
	var $_allowedFields = Array('status', 'age_bracketid', 'congregationid');

	static function getMenuPermissionLevel()
	{
		return PERM_EDITPERSON;
	}

	function processView()
	{
		if (empty($_POST['personid'])) {
			trigger_error("Cannot update persons, no person ID specified", E_USER_WARNING);
			return;
		}

		$customValues = Array();
		$customFields = $GLOBALS['system']->getDBObjectData('custom_field', Array(), 'OR', 'rank');
		$dummyField = new Custom_Field();
		foreach ($customFields as $fieldid => $fieldDetails) {
			$dummyField->populate($fieldid, $fieldDetails);
			if ($val = $dummyField->processWidget()) {
				$customValues[$fieldid] = $val;
			}
		}

		foreach ($this->_allowedFields as $field) {
			if (array_get($_POST, $field, '') == '') unset($_POST[$field]);
		}
		
		if (empty($customValues) && count(array_intersect(array_keys($_POST), $this->_allowedFields)) == 0) {
			add_message("Cannot update; no new values were specified", 'error');
			if (!empty($_REQUEST['backto'])) {
				parse_str($_REQUEST['backto'], $back);
				unset($back['backto']);
				$back['*'] = NULL;
				redirect($back['view'], $back);
			}
			return;
		}
		
		$success = 0;
		$GLOBALS['system']->setFriendlyErrors(TRUE);
		foreach ((array)$_REQUEST['personid'] as $personid) {

			$this->_person = new Person((int)$personid);
			if (!$this->_person->acquireLock()) {
				add_message($this->_person->toString().' is locked by another user and cannot be updated. Please try again later', 'error');
			}
			
			foreach ($this->_allowedFields as $field) {
				if (strlen(array_get($_POST, $field, ''))) {
					// we need our own isset
					$this->_person->processFieldInterface($field);
				}
			}

			foreach ($customValues as $fieldid => $val) {
				$this->_person->setCustomValue($fieldid, $val, array_get($_POST, 'custom_'.$fieldid.'_add', FALSE));
			}

			if ($this->_person->validateFields() && $this->_person->save()) {
				$success++;
			}
		}
		if ($success == count($_REQUEST['personid'])) {
			add_message('Fields updated for ' .count($_REQUEST['personid']).' persons');
		} else if ($success > 0) {
			add_message("Fields updated for $success persons; some persons could not be updated");
		} else {
			add_message('There was a problem updating the fields. Check your selected persons.');
		}
		if (!empty($_REQUEST['backto'])) {
			parse_str($_REQUEST['backto'], $back);
			unset($back['backto']);
			$back['*'] = NULL;
			redirect($back['view'], $back);
		}
		
	}
	
	function getTitle()
	{
		if (empty($this->_person)) {
			return;
		}
		return 'Update status for person';
	}


	function printView()
	{
		
	}
}