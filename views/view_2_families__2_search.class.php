<?php
class View_Families__Search extends View
{
	var $_family_data;
	var $_search_terms = Array('address_street', 'address_suburb', 'address_postcode', 'family_name');
	var $_search_params = Array();

	function processView()
	{
		$this->_search_params = Array();
		if (!empty($_REQUEST['name'])) $_REQUEST['family_name'] = $_REQUEST['name'];
		foreach ($this->_search_terms as $term) {
			if (!empty($_REQUEST[$term])) {
				$this->_search_params[$term] = $_REQUEST[$term];
			}
		}
		if (!empty($this->_search_params)) {
			$this->_family_data = ($GLOBALS['system']->getDBObjectData('family', $this->_search_params, 'AND', 'family_name'));
		}
		if (empty($this->_family_data) && !empty($this->_search_params['family_name'])) {
			$this->_search_params['family_name'] = '%'.$this->_search_params['family_name'].'%';
			$this->_family_data = ($GLOBALS['system']->getDBObjectData('family', $this->_search_params, 'AND', 'family_name'));
		}

		if (count($this->_family_data) == 1) {
			add_message('One matching family found');
			redirect('families', Array('familyid' => key($this->_family_data), 'name' => NULL)); //exits
		}
	}
	
	function getTitle()
	{
		return 'Family Search Results';
	}

	function printView()
	{
		$families =& $this->_family_data;
		if (empty($families)) {
			?>
			<p>No matching families were found</p>
			<?php
		} else {
			include dirname(dirname(__FILE__)).'/templates/family_list.template.php';
		}
	}
}
?>
