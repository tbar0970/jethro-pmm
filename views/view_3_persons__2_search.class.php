<?php
class View_Persons__Search extends View
{
	var $_person_data;
	var $_search_terms = Array('first_name', 'last_name', 'email', 'mobile_tel', 'work_tel');

	function processView()
	{
		$params = Array();
		if (!empty($_REQUEST['name'])) {
			$GLOBALS['system']->includeDBClass('person');
			$this->_person_data = Person::getPersonsByName($_REQUEST['name']);
		} else {
			foreach ($this->_search_terms as $term) {
				if (!empty($_REQUEST[$term])) {
					$params[$term] = $_REQUEST[$term];
				}
			}
			if (!empty($params)) {
				$this->_person_data = ($GLOBALS['system']->getDBObjectData('person', $params, 'AND', 'last_name'));
			}
		}
		if ((count($this->_person_data) == 1)) {
			add_message('One matching person found');
			redirect('persons', Array('name' => NULL, 'personid' => key($this->_person_data)));
		}

		$archiveds = Array();
		foreach ($this->_person_data as $k => $v) {
			if ($v['status'] == 'archived') {
				$archiveds[$k] = $v;
				unset($this->_person_data[$k]);
			}
		}
		foreach ($archiveds as $k => $v) {
			$this->_person_data[$k] = $v;
		}
	}

	
	function getTitle()
	{
		return 'Person Search Results';
	}

	
	function printView()
	{
		$persons =& $this->_person_data;
		if (empty($persons)) {
			?>
			<p>No matching persons were found</p>
			<?php
		} else {
			$special_fields = Array('congregation');
			include dirname(dirname(__FILE__)).'/templates/person_list.template.php';
		}
	}
}
?>
