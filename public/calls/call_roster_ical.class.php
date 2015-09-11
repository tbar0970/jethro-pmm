<?php
class Call_Roster_Ical extends Call
{
	function run()
	{
		if (empty($_REQUEST['uuid'])) {
			http_response_code(400);
			?><p>UUID not specified</p><?php
			exit;			
		}
		
		$personid = $this->_getPersonID($_REQUEST['uuid']);
		if (empty($personid)) {
			http_response_code(404);
			?><p>Not registered</p><?php
			exit;
		}
		
		$assignments = Roster_Role_Assignment::getUpcomingAssignments($personid, NULL);

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
