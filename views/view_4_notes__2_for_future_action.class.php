<?php
require_once 'views/abstract_view_notes_list.class.php';
class View_Notes__For_Future_Action extends Abstract_View_Notes_List
{
	static function getMenuPermissionLevel()
	{
		return PERM_VIEWNOTE;
	}

	function _getNotesToShow($assigneeID=NULL, $search=NULL)
	{
		$conds = Array('status' => 'pending', '>action_date' => date('Y-m-d'));
		if ($search) {
			$conds['subject'] = '%'.$search.'%';
		}
		if ($assigneeID) $conds['assignee'] = $assigneeID;
		return $GLOBALS['system']->getDBObjectData('person_note', $conds, 'AND', '', TRUE) + $GLOBALS['system']->getDBObjectData('family_note', $conds, 'AND', '', TRUE);
		uasort($notes, Array($this, '_compareNoteDates'));
	}


	function getTitle()
	{
		return _('Notes For Future Action');
	}
}
?>
