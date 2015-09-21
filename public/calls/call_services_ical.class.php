<?php
class Call_Services_Ical extends Call
{
	function run()
	{
		$congregationid = array_get($_REQUEST, 'congregationid');
		$services = Service::findAllAfterDate(date('Y-m-d'), $congregationid);
		
		header('Content-type: text/calendar');
		header('Content-Disposition: inline; filename=services.ics'); 
		//header('content-type: text/plain');

		require_once 'templates/service_ical.template.php';
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
