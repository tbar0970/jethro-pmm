<?php
require_once 'views/abstract_view_notes_list.class.php';
class View_Notes__For_Immediate_Action extends Abstract_View_Notes_List
{
	function _getNotesToShow()
	{
		return $this->_getNotes(Array(
			'<action_date' => date('Y-m-d', strtotime('tomorrow'))
		));
	}


	function getTitle()
	{
		return _('Notes For Immediate Action');
	}
}