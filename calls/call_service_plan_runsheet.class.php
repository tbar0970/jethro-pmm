<?php

/**
 * Returns a service's items as [categoryid, title] JSON. Used by the service editor 'Copy from previous' preview.
 */
class Call_Service_Plan_Runsheet extends Call
{
	function run()
	{
		if (!$GLOBALS['user_system']->havePerm(PERM_VIEWSERVICE)) $this->failReturningJson("You don't have permission to view services");

		$service_id = (int)array_get($_REQUEST, 'serviceid');
		$service = new Service((int)$service_id);
		if (!$service || !$service->id) $this->failReturningJson('Service not found');

		// Get items for the service (including headings as separate rows)
		$items = $service->getItems();

		$out = array();
		foreach ($items as $item) {
			// If this is a heading row, render the heading text
			if (!empty($item['heading_text'])) {
				$rendered = $item['heading_text'];
			} else {
				// Normal item: render the runsheet title with keywords substituted
				$titleTemplate = $item['runsheet_title_format'];
				if (strlen($titleTemplate) > 0) {
					$rendered = $service->replaceItemKeywords($titleTemplate, $item);
					$rendered = $service->replaceKeywords($rendered);
				} else {
					// Fallback to the stored title if no template is provided
					$rendered = $item['title'];
				}
			}

			$out[] = array(
				'categoryid' => $item['categoryid'],
				'title' => $rendered
			);
		}

		header('Content-Type: application/json');
		echo json_encode($out);
	}

	public
	function failReturningJson($errMsg): void
	{
		header('Content-Type: application/json');
		echo json_encode(array('error' => $errMsg));
		exit();
	}
}