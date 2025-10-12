<?php
/** Deletes a song, if possible (e.g. it is not used in service items)
 * Called via AJAX from the 'Delete' link on the song details panel.
 */
class Call_Service_Comp_Delete extends Call
{
	function run()
	{
		$GLOBALS['system']->includeDBClass('service_component');
		$GLOBALS['system']->initErrorHandler();
		$service_componentid = (int)$_POST['service_componentid'];
		/** @var Service_Component $comp */
		$comp = $GLOBALS['system']->getDBObject('service_component', $service_componentid);
		if ($comp) {
			if ($comp->delete()) {
				echo "Service Component ".$service_componentid." deleted."; //
			} else {
				// delete() called Service_Component#canDelete() which returned false, and added some reasons via add_message()
				// This should not normally happen. The UI should only show a 'Delete' link if canDelete() returns true.
				// However it could happen if another user / tab deleted the song, and the current user didn't refresh the browser.
				http_response_code(409);
				dump_messages(); // Print the reason
			}
		} else {
			http_response_code(404);
			echo "Service component ".$service_componentid." does not exist.";
		}
	}

}