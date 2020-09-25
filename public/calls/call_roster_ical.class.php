<?php
class Call_Roster_Ical extends Call
{
	function run()
	{
		if (empty($_REQUEST['uuid'])) {
			header("HTTP/1.0 400 Bad request");
			?><p>UUID not specified</p><?php
			exit;
		}
		
		$personid = $this->_getPersonID($_REQUEST['uuid']);
		if (empty($personid)) {
			header("HTTP/1.0 404 Not Found");
			?><p>Not registered</p><?php
			exit;
		}
		
		$assignments = Roster_Role_Assignment::getUpcomingAssignments($personid, "8 weeks");
		
		header('Content-type: text/calendar');
		header('Content-Disposition: inline; filename=roster.ics');
		//header('content-type: text/plain');

		require_once 'templates/roster_ical.template.php';
	}

	/**
	 * Find a person record to which we could attach a member account
	 * @param string $uuid The UUID of the member
	 * @return mixed.
	 */
	private function _getPersonID($uuid)
	{
		$res = $GLOBALS['system']->getDBObjectData('person', Array('feed_uuid' => $uuid));
		return key($res);
	}
}
