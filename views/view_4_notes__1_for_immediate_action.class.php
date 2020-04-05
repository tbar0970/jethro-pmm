<?php
require_once 'views/abstract_view_notes_list.class.php';
class View_Notes__For_Immediate_Action extends Abstract_View_Notes_List
{
	static function getMenuPermissionLevel()
	{
		return PERM_VIEWMYNOTES;
	}

	function _getNotesToShow($assigneeID=NULL, $search=NULL)
	{
		$conds = Array(
			'status' => 'pending',
			'<action_date' => date('Y-m-d', strtotime('tomorrow'))
		);
		if ($assigneeID) {
			$conds['assignee'] = $assigneeID;
		}
		if ($search) {
			$conds['subject'] = '%'.$search.'%';
		}
		$res = $GLOBALS['system']->getDBObjectData('person_note', $conds, 'AND', '', TRUE) + $GLOBALS['system']->getDBObjectData('family_note', $conds, 'AND', '', TRUE);
		uasort($res, Array($this, '_compareNoteDates'));
		return $res;
	}


	function getTitle()
	{
		return _('Notes For Immediate Action');
	}
}
?>
