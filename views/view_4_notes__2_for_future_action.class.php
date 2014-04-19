<?php
require_once 'views/abstract_view_notes_list.class.php';
class View_Notes__For_Future_Action extends Abstract_View_Notes_List
{
	static function getMenuPermissionLevel()
	{
		return PERM_VIEWNOTE;
	}

	function _getNotesToShow($assigneeID=NULL)
	{
		$conds = Array('status' => 'pending', '>action_date' => date('Y-m-d'));
		if ($assigneeID) $conds['assignee'] = $assigneeID;
		return $GLOBALS['system']->getDBObjectData('person_note', $conds, 'AND') + $GLOBALS['system']->getDBObjectData('family_note', $conds, 'AND');
		uasort($notes, Array($this, '_compareNoteDates'));
	}


	function getTitle()
	{
		return 'Notes For Future Action';
	}
}
?>