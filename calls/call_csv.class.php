<?php
require_once 'include/odf_tools.class.php';
class Call_csv extends Call
{
	function run() 
	{
		
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
				$merge_data = $GLOBALS['system']->getDBObjectData('person', Array('id' => $_POST['personid']));
				$dummy = new Person();
				$dummy_family = new Family();
				break;
		}
		$headerrow = Array('ID');
		foreach (array_keys(reset($merge_data)) as $header) {
			$headerrow[] = strtoupper($header);
		}
		echo get_csv_row($headerrow);
		
		foreach ($merge_data as $id => $row) {
			@$dummy->populate($id, $row);
			$outputrow = Array($id);
			foreach ($row as $k => $v) {
				if ($k == 'history') continue;
				if ($dummy->hasField($k)) {
					$outputrow[] = $dummy->getFormattedValue($k, $v); // pass value to work around read-only fields
				} else if ($dummy_family && $dummy_family->hasField($k)) {
					$outputrow[] = $dummy_family->getFormattedValue($k, $v);
				} else {
					$outputrow[] = $v;
				}
			}
			echo get_csv_row($outputrow);
		}
	}
}


?>
