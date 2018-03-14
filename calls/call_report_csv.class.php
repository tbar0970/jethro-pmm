<?php
class Call_Report_CSV extends Call
{
	function run()
	{
		if (!empty($_REQUEST['queryid'])) {
			$queryid = $_REQUEST['queryid'];
			$queryid = ($queryid == 'TEMP') ? $queryid : (int)$queryid;
			if ($queryid) {
				$report = $GLOBALS['system']->getDBObject('person_query', $queryid);
				$reportname = $report->getValue('name');
				if (empty($reportname)) $reportname = 'Jethro-Report-'.date('Y-m-d_H:i');
				header('Content-type: application/force-download');
				header("Content-Type: application/download");
				header('Content-type: text/csv');
				header('Content-disposition: attachment; filename="'.str_replace('"', '\\"', $reportname).'.csv"');
				$report->printResults('csv');
			} else {
				trigger_error('Query not found');
			}
		}
	}
}
