<?php
/** Disables a song by removing all Congregation relations from it.
 * Called via AJAX from the 'Disable' link on the song details panel.
 */
class Call_Service_Comp_Disable extends Call
{
	function run()
	{
		$GLOBALS['system']->includeDBClass('service_component');
		$GLOBALS['system']->initErrorHandler();
		if (!isset($_POST['service_componentid'])) error_response(400, "Missing POST parameter: service_componentid");
		if (filter_var($_POST['service_componentid'], FILTER_VALIDATE_INT) === false) error_response(400, "Invalid POST parameter: service_componentid");
		$service_componentid = (int)$_POST['service_componentid'];
		/** @var Service_Component $comp */
		$comp = $GLOBALS['system']->getDBObject('service_component', $service_componentid);
		if ($comp) {
			if ($comp->disable()) {
				echo "Service Component ".$service_componentid." disabled.";
			} else {
				// Saving the component failed. Print any messages explaining why.
				http_response_code(409);
				echo "Disabling service component failed";
				dump_messages();
			}

		} else {
			http_response_code(404);
			echo "Service component ".$service_componentid." does not exist.";
		}
	}

}