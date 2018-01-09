<?php
class Call_spreadsheet_merge extends Call
{
	function run()
	{
		$file_info = array_get($_FILES, 'source_document');
		$content = null;
		if (empty($file_info['tmp_name'])) {
			trigger_error('Template file does not seem to have been uploaded');
			return;
		}
		$extension = @strtolower(end(explode('.', $file_info['name'])));
		$source_file = $file_info['tmp_name'];
		rename ($source_file, $source_file.'.'.$extension);
		$source_file = $source_file.'.'.$extension;
//print_r(ifdef('SYSTEM_NAME', ''));
//print_r($_SESSION['user']);
//print_r($GLOBALS);
		switch ($extension) {
			case 'ods':
			case 'xlsx':
				require_once 'include/tbs.class.php';
				include_once 'include/tbs_plugin_opentbs.php';
				if (ini_get('date.timezone')=='') {
					date_default_timezone_set('UTC');
				}
				$TBS = new clsTinyButStrong;
				$TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN); 
				$TBS->SetOption('noerr', TRUE);
				$TBS->LoadTemplate($source_file, OPENTBS_ALREADY_UTF8);
				$TBS->ResetVarRef(false); 
				$TBS->VarRef['x_num'] = 3152.456;
				$TBS->VarRef['x_pc'] = 0.2567;
				$TBS->VarRef['x_dt'] = mktime(13,0,0,2,15,2010);
				$TBS->VarRef['x_bt'] = true;
				$TBS->VarRef['x_bf'] = false;
				$TBS->VarRef['x_delete'] = 1;
				$TBS->VarRef['yourname'] = 'Some name';
				$TBS->VarRef['system_name'] = ifdef('SYSTEM_NAME', '');
				$TBS->VarRef['timezone'] = ifdef('TIMEZONE', '');
				$TBS->VarRef['username'] = $_SESSION['user']['username'];
				$TBS->VarRef['first_name'] = $_SESSION['user']['first_name'];
				$TBS->VarRef['last_name'] = $_SESSION['user']['last_name'];
				$TBS->VarRef['email'] = $_SESSION['user']['email'];

				$merge_type = array_get($_REQUEST, 'merge_type', 'person');		
				$i = 0;
				if (isset($_REQUEST['dates'])) {
					$dates = (array)$_REQUEST['dates'];
					foreach ($dates as $date) {
						$i += 1;
						$TBS->VarRef['date'.$i] = $date;
					}
				} 
				$TBS->VarRef['dates'] = $i;
				while ($i < 60) {
					$i += 1;
					$TBS->VarRef['date'.$i] = '';
				}
				$i = 0;
				if (isset($_REQUEST['groups'])) {
					$groups = (array)$_REQUEST['groups'];
					foreach ($groups as $group) {
						$i += 1;
						$TBS->VarRef['group'.$i] = $group;
					}
				}
				$TBS->VarRef['groups'] = $i;
				while ($i < 60) {
					$i += 1;
					$TBS->VarRef['group'.$i] = '';
				}
				if ($merge_type != 'family') { $merge_type = 'person'; }
				$data = $this->getMergeData();
//				print_r($data);
				$TBS->MergeBlock($merge_type, $data);
				$filename = basename($file_info['name']);
				$TBS->Show(OPENTBS_DOWNLOAD, $filename);
				break;

			default:
				trigger_error("Format $extension not yet supported");
				return;
		}

	}
	/* person
			[id] => 1
            [first_name] => Ivan
            [last_name] => McLean
            [gender] => Male
            [age_bracket] => Adult
            [congregation] => RCOC
            [status] => Member
            [email] => ivan@our-eden.com
            [mobile_tel] => 
            [work_tel] => 
            [contact_remarks] => A random comment
            [date_of_last_status_change] => 
            [created_date] => 30 Nov 2017 3:19pm
            [creator] => 
            [family_name] => McLean
            [address_street] => 
            [address_suburb] => 
            [address_state] => WA
            [address_postcode] => 
            [home_tel] => 
            
            family
            [id] => 3
            [family_name] => Pearce
            [street_address] => 
            [suburb] => 
            [state] => WA
            [postcode] => 
            [home_tel] => 
            [status] => Current
            [created_date] => 7 Dec 2017 6:54pm
            [creator] => Ivan McLean (#1)
            [members] => Tim, Diana
            [adult_members] => Tim, Diana
            [selected_firstnames] => Diana,Tim
            [selected_lastnames] => Pearce,Pearce
            */

	protected function getMergeData()
	{
		$return_data = array();
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

		$headerrow = Array('id' => 'id');
		foreach (array_keys(reset($merge_data)) as $header) {
			if ($header == 'familyid') continue;
			if ($header == 'history') continue;
			if ($header == 'feed_uuid') continue;
			$headerrow[$header] = str_replace(' ', '_', strtolower($dummy->getFieldLabel($header)));
		}
		switch ($data_type) {
			case 'attendance_tabular':
				$lastrow[] = '';
				foreach ($headerrow as $Hash) { $lastrow[$Hash] = ''; }
				$lastrow['id'] = 0;
				$lastrow['first_name'] = 'Extras';
				$dat = 1;
				foreach ($dates as $date) {
					$grp = 1;
					foreach ($groups as $group) {
						$headerrow[$date.$group.'l'] = 'date'.$dat.'_group'.$grp.'_letter';
						$headerrow[$date.$group.'n'] = 'date'.$dat.'_group'.$grp.'_number';
						$grp += 1;
					}
					$headerrow[$date] = 'date'.$dat;
					$dat += 1;
				}
				break;
		}

		foreach ($merge_data as $id => $row) {
			@$dummy->populate($id, $row);
			$outputrow = Array('id' => $id);
			foreach ($row as $k => $v) {
				if ($k == 'history') continue;
				if ($k == 'familyid') continue;
				if ($k == 'feed_uuid') continue;
				if ($dummy->hasField($k)) {
					$outputrow[$headerrow[$k]] = $dummy->getFormattedValue($k, $v); // pass value to work around read-only fields
				} else if ($dummy_family && $dummy_family->hasField($k)) {
					$outputrow[$headerrow[$k]] = $dummy_family->getFormattedValue($k, $v);
				} else {
					$outputrow[$k] = $v;
				}
			}
			switch ($data_type) {
				case 'attendance_tabular':
					$sumval = 0;
					$extras = FALSE;
					foreach ($dates as $date) {
						$sum = 0;
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
							$outputrow[$headerrow[$date.$group.'l']] = $letter;
							$outputrow[$headerrow[$date.$group.'n']] = $val;
							$sum += $val;
						}
						if ($extras) {
							$val = $sum;
						} else {
							$val = ($sum > 0) ? 1:0;
						}
						$outputrow[$headerrow[$date]] = $val;
					}
					break;
			}
			$return_data[] = $outputrow;
		}
		switch ($data_type) {
			case 'attendance_tabular':
				$sumval = 0;
				foreach ($dates as $date) {
					$sum = 0;
					foreach ($groups as $group) {
						$val = $data[-1][$sumval];
						$sumval += 1;
						$lastrow[$headerrow[$date.$group.'n']] = $val;
						$sum += $val;
					}
					$lastrow[$headerrow[$date]] = $sum;
				}
				$return_data[] = $lastrow;
				break;
		}
		return $return_data;
	}
}
