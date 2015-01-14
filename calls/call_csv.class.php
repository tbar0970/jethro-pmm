<?php
require_once 'include/odf_tools.class.php';
class Call_csv extends Call
{
	function run() 
	{
		$fp = fopen('php://output', 'w');
		header('Content-type: application/force-download');
		header("Content-Type: application/download");
		header('Content-type: text/csv');
		header('Content-Disposition: attachment; filename="jethro_'.date('Y-m-d_h:i').'.csv"');
		$GLOBALS['system']->includeDBClass('family');
		$GLOBALS['system']->includeDBClass('person');
		switch (array_get($_REQUEST, 'merge_type')) {
			case 'family':
				$merge_data = Family::getFamilyDataByMemberIDs($_POST['personid']);
				$dummy = new Family();
				$dummy_family = NULL;
				break;
			case 'person':
			default:
				$merge_data = $GLOBALS['system']->getDBObjectData('person', Array('id' => (int)$_POST['personid']));
				$dummy = new Person();
				$dummy_family = new Family();
				break;
		}
		$headerrow = Array('ID');
		foreach (array_keys(reset($merge_data)) as $header) {
			if ($header == 'familyid') continue;
			$headerrow[] = strtoupper($dummy->getFieldLabel($header));
		}
		fputcsv($fp, $headerrow);
		
		foreach ($merge_data as $id => $row) {
			@$dummy->populate($id, $row);
			$outputrow = Array($id);
			foreach ($row as $k => $v) {
				if ($k == 'history') continue;
				if ($k == 'familyid') continue;
				if ($dummy->hasField($k)) {
					$outputrow[] = $dummy->getFormattedValue($k, $v); // pass value to work around read-only fields
				} else if ($dummy_family && $dummy_family->hasField($k)) {
					$outputrow[] = $dummy_family->getFormattedValue($k, $v);
				} else {
					$outputrow[] = $v;
				}
			}
			fputcsv($fp, $outputrow);
		}
		fclose($fp);
	}
}


?>
