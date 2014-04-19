<?php
class View__Directory extends View
{
	private $stateMessage = '';
	
	function processView() {
		$send401 = TRUE;
		
		if (!defined('MEMBER_DIRECTORY_GROUPID')) {
			$this->stateMessage = 'Feature not set up';
			$send401 = FALSE;
		} else if (!empty($_REQUEST['congregationid'])) {
			$userConst = 'MEMBER_DIRECTORY_USERNAME_'.(int)$_REQUEST['congregationid'];
			$passConst = 'MEMBER_DIRECTORY_PASSWORD_'.(int)$_REQUEST['congregationid'];
			if ((!defined($userConst))	|| !defined($passConst)) {
				$this->stateMessage = 'Invalid congregation';
				$send401 = false;
			} else if (!isset($_SERVER['PHP_AUTH_USER'])) {
				$this->stateMessage = 'This page is only available to authorised users';
			} else if (
					($_SERVER['PHP_AUTH_USER'] == constant($userConst))
					&& ($_SERVER['PHP_AUTH_PW'] == constant($passConst))
			) {
				$this->stateMessage = TRUE;
				$send401 = FALSE;
			} else {
				$this->stateMessage = 'Wrong username or password';
			}
		} else if (defined('MEMBER_DIRECTORY_USERNAME') && defined('MEMBER_DIRECTORY_PASSWORD')) {
			if (!isset($_SERVER['PHP_AUTH_USER'])) {
				$this->stateMessage = 'This page is only available to authorised users';
				$send401 = TRUE;
			} else if (($_SERVER['PHP_AUTH_USER'] == MEMBER_DIRECTORY_USERNAME)
					 && ($_SERVER['PHP_AUTH_PW'] == MEMBER_DIRECTORY_PASSWORD)) {
				$this->stateMessage = TRUE;
				$send401 = FALSE;
			} else {
				$this->stateMessage = 'Wrong username or password';
			}
		} else {
			$this->stateMessage = 'This feature is not enabled on this system';
			$send401 = false;
		}
		
		if ($send401) {
			header('WWW-Authenticate: Basic realm="Member Directory"');
			header('HTTP/1.0 401 Unauthorized');
		}
			
	}

	function printView()
	{
		if ($this->stateMessage !== TRUE) {
			print_message($this->stateMessage, 'failure');
			return;
		}
		if (defined('MEMBER_DIRECTORY_HEADER')) echo MEMBER_DIRECTORY_HEADER;
		require_once 'views/view_2_families__4_contact_list.class.php';
		$GLOBALS['system']->includeDBClass('person');
		$dummy = new Person();
		$view = new View_Families__Contact_List();
		$_REQUEST['groupid'] = MEMBER_DIRECTORY_GROUPID;
		$_REQUEST['all_member_details'] = FALSE;
		$_REQUEST['age_bracket'] = array(0);
		$_REQUEST['include_address'] = 1;
		$_REQUEST['congregationid'] = Array($_REQUEST['congregationid']);
		$view->processView();
		$view->printResults(true);

	}


}

?>
