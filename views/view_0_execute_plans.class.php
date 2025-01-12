<?php
class View__Execute_Plans extends View
{
	function processView()
	{
		if (empty($_REQUEST['planid'])) {
			add_message('No plans selected for execution', 'error');
			return;
		}
		if (empty($_REQUEST['personid'])) {
			add_message('No persons selected for plan execution', 'error');
			return;
		}

		$plans = Array();
		foreach ($_REQUEST['planid'] as $planid) {
			$plans[$planid] = $GLOBALS['system']->getDBObject('action_plan', $planid);
		}

		$refdate = process_widget('plan_reference_date', Array('type' => 'date'));

		$success = Array();
		foreach ($plans as $planid => $plan) {
			$success[$planid] = 0;
			foreach ($_REQUEST['personid'] as $personid) {
				$success[$planid] += (int)$plan->execute('person', (int)$personid, $refdate);
			}
		}

		foreach ($plans as $planid => $plan) {
			if ($success[$planid] > 0) {
				add_message('"'.$plan->getValue('name').'" plan executed for '.$success[$planid].' person(s)', 'success');
			} else {
				add_message('"'.$plan->getValue('name').'" plan was not executed for any persons', 'failure');
			}
		}
		if (count($_REQUEST['personid']) == 1) {
			redirect('persons', Array('personid' => (int)reset($_REQUEST['personid'])));
		}
	}
	
	function getTitle()
	{
		return 'Execute plans';
	}


	function printView()
	{
		

	}
}