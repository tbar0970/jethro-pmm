<?php
class View_Families extends View
{
	// Displays a single family
	var $_family;

	function processView()
	{
		if (!empty($_REQUEST['familyid'])) {
			$this->_family =& $GLOBALS['system']->getDBObject('family', (int)$_REQUEST['familyid']);
		}
	}

	function getTitle()
	{
		if ($this->_family) {
			return 'Viewing Family: '.$this->_family->getValue('family_name');
		}
		return 'Error: No family supplied';
	}

	
	function printView()
	{
		if ($this->_family) {
			$family =& $this->_family;
			include dirname(dirname(__FILE__)).'/templates/view_family.template.php';
		}
	}
}
?>
