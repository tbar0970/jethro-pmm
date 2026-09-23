<?php
class Process_Roster_Swap
{
	static function process()
	{
		if (!MEMBER_SWAP_ENABLED) {
			return;
		}

		self::processAsk();
		self::processAccept();
	}

	protected static function processAsk() {
		if (!empty($_POST['assignees'])) {
			$currentMemberId = $GLOBALS['user_system']->getCurrentMember('id');
			foreach ($_POST['assignees'] as $roleid => $dates) {
				foreach ($dates as $date => $assignee) {
					if (!$assignee || $assignee == $currentMemberId) {
						// No new person, or new person is the same as the current person
						continue;
					}
					if (!Roster_Role_Assignment::currentMemberHasAssignment($roleid, $date)) {
						continue;
					}

					$person = new Person($assignee);
					$personEmail = $person->getValue('email');
					if (!$personEmail) {
						// No e-mail
						// This should have been filtered out by printChooserForMember()
						continue;
					}

					$url = baseurl_absolute().'/members?accept='.(int)$roleid.'&on='.$date.'&from='.$currentMemberId;

					$body = "Hi %s,

%s has asked you to cover %s on %s. To accept, %s";

					$memberName = $GLOBALS['user_system']->getCurrentMember('first_name');
					$formattedDate = date('F jS Y', strtotime($date));
					$role = new Roster_Role($roleid);
					$roleTitle = $role->getValue('title');
					$text = sprintf($body, $person->getValue('first_name'), $memberName, $roleTitle, $formattedDate, 'go to '.$url);
					$html = sprintf(nl2br($body), $person->getValue('first_name'), $memberName, $roleTitle, $formattedDate, '<a href="'.$url.'">click here</a>.');
					$message = Emailer::newMessage()
									  ->setSubject('Cover '.$roleTitle)
									  ->setFrom(['church@hurstvillepresbyterian.org' => 'Hurstville Presbyterian Roster'])
									  ->setTo([$personEmail => $person->getValue('first_name').' '.$person->getValue('last_name')])
									  ->setBody($text)
									  ->addPart($html, 'text/html');
					Emailer::send($message);

					add_message('Swap requested', 'info');
				}
			}
		}
	}

	protected static function processAccept() {
		if (!empty($_GET['accept']) && !empty($_GET['on']) && !empty($_GET['from'])) {
			// Verify that the role is currently assigned to the original person on that date
			if (Roster_Role_Assignment::swapToCurrentMember($_GET['accept'], $_GET['on'], $_GET['from'])) {
				add_message('Swap accepted', 'success');
			}
		}
	}
}