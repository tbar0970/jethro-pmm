<?php
require_once 'calls/call_service_comp_help_runsheet_format.class.php';

/**
 * Help page for the 'Handout Title' field when editing a Service Component.
 */
class Call_Service_Comp_Help_Handout_Title_Format extends Call_Service_Comp_Help_Runsheet_Format
{
	protected function getField() {
		return 'Handout Title Format';
	}
	protected function getPurpose()
	{
		return "specifies how the Title of this component should appear in the service handout";
	}
}