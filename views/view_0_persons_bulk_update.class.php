<?php
class View__Persons_Bulk_Update extends View
{
	var $_person;
	var $_allowedFields = Array('status', 'age_bracket', 'congregationid');

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
		
		foreach ($this->_allowedFields as $field) {
			if (array_get($_POST, $field, '') == '') unset($_POST[$field]);
		}
		
		if (empty($_POST['date_typeid']) && count(array_intersect(array_keys($_POST), $this->_allowedFields)) == 0) {
			add_message("Cannot update; no new values were specified", 'error');
			if (!empty($_REQUEST['backto'])) {
				parse_str($_REQUEST['backto'], $back);
				unset($back['backto']);
				redirect($back['view'], $back);
			}
			return;
		}
		
		if (!is_array($_POST['personid'])) {
			$_REQUEST['personid'] = Array($_REQUEST['personid']);
		}
		$GLOBALS['system']->includeDBClass('person');

		$success = 0;
		$GLOBALS['system']->setFriendlyErrors(TRUE);
		foreach ($_REQUEST['personid'] as $personid) {

			$this->_person = new Person((int)$personid);
			
			foreach ($this->_allowedFields as $field) {
				if (isset($_POST[$field])) {
					$this->_person->setValue($field, $_POST[$field]);
				}
			}
			if (!empty($_POST['date_typeid'])) {
				$params = Person::getDateSubfieldParams();
				$dateval = process_widget('date_val', $params['date']);
				if (!$dateval) {
					trigger_error("Invalid date value; cannot set date field");
					return;
				}
				$this->_person->addDate($dateval, $_POST['date_typeid'], $_POST['date_note']);
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
?>
