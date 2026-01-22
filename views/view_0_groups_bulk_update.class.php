<?php
class View__Groups_Bulk_Update extends View
{

	static function getMenuPermissionLevel()
	{
		return PERM_EDITPERSON;
	}

	function processView()
	{
		if (empty($_POST['groupid'])) {
			trigger_error("Cannot update groups, no group IDs specified", E_USER_WARNING);
			return;
		}

        $success = 0;
        $action = '';
		$GLOBALS['system']->setFriendlyErrors(TRUE);
		foreach ((array)$_REQUEST['groupid'] as $groupid) {
            $g = $GLOBALS['system']->getDBOBject('person_group', $groupid);
            if (!$g->acquireLock()) {
				add_message($g->toString().' is locked by another user and cannot be updated. Please try again later', 'error');
                continue;
			}


            switch (TRUE) {
                case !empty($_REQUEST['action_delete']):
                    if ($g->delete()) {
                        $success++;
                    }
                    $action = 'deleted';
                    break;
                case !empty($_REQUEST['action_archive']):
                    $g->setValue('is_archived', 1);
                    if ($g->save()) {
                        $success++;
                    }
                    $action = 'archived';
                    break;
                case !empty($_REQUEST['action_set_category']):
                    $g->setValue('categoryid', (int)$_REQUEST['categoryid']);
                    if ($g->save()) {
                        $success++;
                    }
                    $action = 'updated';
                    break;
            }
            $g->releaseLock();

        }
        
		if ($success == count((array)$_REQUEST['groupid'])) {
			add_message($success.' groups were '.$action, 'success');
		} else if ($success > 0) {
			add_message($success.' groups were '.$action.' but some could not be '.$action, 'success');
		} else {
			add_message('There was a problem and no groups were '.$action.'.  Check your selected groups.', 'error');
			exit;
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

	}


	function printView()
	{
		
	}
}