<?php
require_once 'include/odf_tools.class.php';
class Call_csv extends Call
{
	function run()
	{
		if (empty($_REQUEST['personid'])) {
			trigger_error("You must select some persons");
			exit;
		}
		$fp = fopen('php://output', 'w');
		header('Content-type: application/force-download');
		header("Content-Type: application/download");
		header('Content-type: text/csv');
		header('Content-Disposition: attachment; filename="jethro_'.date('Y-m-d_h:i').'.csv"');
		$GLOBALS['system']->includeDBClass('family');
		$GLOBALS['system']->includeDBClass('person');
		$data_type = array_get($_REQUEST, 'data_type', 'none');
		switch (array_get($_REQUEST, 'merge_type')) {
			case 'family':
				$data_type = 'none'; // Does not make sense for families
				$merge_data = Family::getFamilyDataByMemberIDs($_POST['personid']);
				$dummy = new Family();
				$dummy_family = NULL;
				break;
			case 'person':
				$data_type = 'none';
			default:
				$merge_data = $GLOBALS['system']->getDBObjectData('person', Array('id' => (array)$_POST['personid']));
				foreach (Person::getCustomMergeData($_POST['personid']) as $personid => $data) {
					$merge_data[$personid] += $data;
				}
				$dummy = new Person();
				$dummy_family = new Family();
				break;
		}
		switch ($data_type) {
			case 'attendance_tabular':
				$dates = (array)$_REQUEST['dates'];
				$groups = (array)$_REQUEST['groups'];
				$data = (array)$_REQUEST['data'];
				break;
		}

		fputs($fp, '"ID",');// https://superuser.com/questions/210027/why-does-excel-think-csv-files-are-sylk
		$headerrow = Array();
		foreach (array_keys(reset($merge_data)) as $header) {
			if ($header == 'familyid') continue;
			if ($header == 'history') continue;
			if ($header == 'feed_uuid') continue;
			$headerrow[] = strtoupper($dummy->getFieldLabel($header));
		}
		switch ($data_type) {
			case 'attendance_tabular':
				$lastrow[] = '';
				foreach ($headerrow as $Hash) { $lastrow[] = ''; }
				$lastrow[1] = 'Extras';
				foreach ($dates as $date) {
					foreach ($groups as $group) {
						$headerrow[] = $group;
					}
					$headerrow[] = $date;
				}
				break;
		}
		fputcsv($fp, $headerrow);


		foreach ($merge_data as $id => $row) {
			@$dummy->populate($id, $row);
			$outputrow = Array($id);
			foreach ($row as $k => $v) {
				if ($k == 'history') continue;
				if ($k == 'familyid') continue;
				if ($k == 'feed_uuid') continue;
				if ($dummy->hasField($k)) {
					$outputrow[] = $dummy->getFormattedValue($k, $v); // pass value to work around read-only fields
				} else if ($dummy_family && $dummy_family->hasField($k)) {
					$outputrow[] = $dummy_family->getFormattedValue($k, $v);
				} else {
					$outputrow[] = $v;
				}
			}
			switch ($data_type) {
				case 'attendance_tabular':
					$sumval = 0;
					foreach ($dates as $date) {
						$sum = 0;
						$extras = FALSE;
						foreach ($groups as $group) {
							$letter = $data[$id][$sumval];
							switch ($letter) {
								case 'P':
									$val = 1;
									break;
								case 'A':
								case '?':
									$val = 0;
									break;
								default:
									$val = $letter;
									$extras = TRUE;
							}
							$sumval += 1;
							$outputrow[] = $val;
							$sum += $val;
						}
						if ($extras) {
							$val = $sum;
						} else {
							$val = ($sum > 0) ? 1:0;
						}
						$outputrow[] = $val;
					}
					break;
			}
			fputcsv($fp, $outputrow);
		}
		switch ($data_type) {
			case 'attendance_tabular':
				$sumval = 0;
				foreach ($dates as $date) {
					$sum = 0;
					foreach ($groups as $group) {
						$val = $data[-1][$sumval];
						$sumval += 1;
						$lastrow[] = $val;
						$sum += $val;
					}
					$lastrow[] = $sum;
				}
				fputcsv($fp, $lastrow);
				break;
		}
		fclose($fp);
	}
}


?>
