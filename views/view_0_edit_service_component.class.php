<?php
require_once 'views/abstract_view_edit_object.class.php';
class View__Edit_Service_Component extends Abstract_View_Edit_Object
{
	var $_editing_type = 'service_component';
	var $_on_success_view = 'services__service_components';
	var $_on_cancel_view = 'services__service_components';
	var $_submit_button_label = 'Save ';

	static function getMenuPermissionLevel()
	{
		return PERM_SERVICECOMPS;
	}

	protected function _doSuccessRedirect()
	{
		redirect($this->_on_success_view, Array(), 'cat'.array_get($_REQUEST, 'categoryid'));
	}
}
?>
