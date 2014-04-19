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
			$plans[] = $GLOBALS['system']->getDBObject('action_plan', $planid);
		}

		$refdate = process_widget('plan_reference_date', Array('type' => 'date'));

		
		foreach ($_REQUEST['personid'] as $personid) {
			foreach ($plans as $plan) {
				$plan->execute('person', (int)$personid, $refdate);
			}
		}

		foreach ($plans as $plan) {
			add_message('"'.$plan->getValue('name').'" plan executed for '.count($_REQUEST['personid']).' person(s)', 'success');
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
?>
