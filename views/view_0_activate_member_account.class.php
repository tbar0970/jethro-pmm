<?php
require_once 'members/include/member_user_system.class.php';
class View__Activate_Member_Account extends View
{
	static function getMenuPermissionLevel()
	{
		return PERM_SYSADMIN;
	}

	function processView()
	{
		$person = $GLOBALS['system']->getDBObject('person', (int)$_POST['personid']);
		$mus = new Member_User_System();
		$member = $mus->findCandidateMember($person->getValue('email'));
		if (-1 == $member) {
			add_message("Could not create member account - this person's email address is shared by others", 'error');
			redirect(-1);
		} else if ($member) {
			if ($mus->sendActivationEmail($member)) {
				add_message('Member-account activation email has been sent to '.$person->toString(), 'success');
				redirect(-1);
			} else {
				add_message('An error occurred sending email to '.$person->toString(), 'error');
				// stay on this page to get more details.
			}
		} else {
			add_message($person->toString(). " not qualified to register a member account.");
			redirect(-1);
		}
	}

	function getTitle()
	{
		return 'Activate User Account';
	}


	function printView()
	{

	}
}