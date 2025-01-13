<?php
class Call_Document_merge_rosters extends Call
{
	function run()
	{
		$roster_id = (int)array_get($_REQUEST, 'roster_view');
		if (empty($roster_id)) return;
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
		switch ($extension) {
			case 'odt':
			case 'odg':
			case 'ods':
			case 'odf':
			case 'odp':
			case 'odm':
			case 'docx':
			case 'xlsx':
			case 'ppt':
				$view = $GLOBALS['system']->getDBObject('roster_view', $roster_id);
				$start_date = substr(array_get($_REQUEST, 'start_date', ''), 0, 10);
				$end_date = substr(array_get($_REQUEST, 'end_date', ''), 0, 10);
				$data = $view->printCSV($start_date, $end_date, TRUE);
				$labels = array();
				$roster = array();
				$people = array();
				$rowno = 0;
				foreach ($data as $row) {
					switch ($rowno) {
					case 0:
						$rowno++;
						break;
					case 1:
						$rowno++;
						$itemno = 0;
						foreach ($row as $item) {
							$labels['label'.$itemno] = $item;
							$itemno++;
						}
						for ($i = 0; $i > 20; $i++) {
							$labels['label'.$itemno] = '';
							$itemno++;
						}
						break;
					default:
						$rowno++;
						$itemno = 0;
						$roster_row = array();
						$persons = array();
						foreach ($row as $item) {
							$roster_row['role'.$itemno] = str_replace("\n", ', ', $item);
							if ($itemno == 0) {
								$roster_row['date'] = $item;
								$date = $item;
							} else {
								$hashes = explode("\n", $item);
								foreach ($hashes as $hash) {
									$persons[$hash] = 1;
								}
							}
							$itemno++;
						}
						for ($i = 0; $i > 20; $i++) {
							$labels['role'.$itemno] = '';
							$itemno++;
						}
						$roster[] = $roster_row;
						$peoples = array();
						foreach ($persons as $key => $value) {
							$peoples[] = $key;
						}
						asort($peoples);
						foreach ($peoples as $value) {
							$people[] = array('date' => $date, 'name' => $value);
						}
						break;
					}	
				}
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
				$TBS->VarRef['system_name'] = ifdef('SYSTEM_NAME', '');
				$TBS->VarRef['timezone'] = ifdef('TIMEZONE', '');
				$TBS->VarRef['username'] = $_SESSION['user']['username'];
				$TBS->VarRef['first_name'] = $_SESSION['user']['first_name'];
				$TBS->VarRef['last_name'] = $_SESSION['user']['last_name'];
				$TBS->VarRef['email'] = $_SESSION['user']['email'];
				$TBS->VarRef['roster_view_name'] = $_REQUEST['roster_view_name'];
				$TBS->VarRef['date'] = $date;
				$TBS->MergeBlock('labels', array($labels));
				$TBS->MergeBlock('roster', $roster);
				$TBS->MergeBlock('people', $people);
				$filename = basename($file_info['name']);
				$TBS->Show(OPENTBS_DOWNLOAD, $filename);
				break;
			default:
				trigger_error("Format $extension not yet supported");
				return;
		}
	}
}