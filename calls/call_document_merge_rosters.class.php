<?php
class Call_Document_Merge_Rosters extends Call
{
	public static function getSavedTemplatesDir()
	{
		return Documents_Manager::getRootPath().'/Templates/To_Merge/';
	}
	
	public static function NewLines($extension, $item)
	{	
		if ($extension == 'ods') {
			$s = str_replace("\n", '</text:p><text:p>', trim($item));
		} elseif ($extension == 'odt'){
			$s = str_replace("\n", '<text:line-break/>', trim($item));
		} else {
			$s = trim($item);
		}
		$s = str_replace('&','&amp;',$s);
		return $s;
	
	}
	
	function run()
	{
		$roster_id = (int)array_get($_REQUEST, 'roster_view');
		if (empty($roster_id)) return;
		$file_info = array_get($_FILES, 'source_document');
		$content = null;
		$template_filename = null;
		if (array_get($_REQUEST, 'template_format') == 'dump') {
			$extension = '@@@debug@@@';
		} else if (!empty($file_info['tmp_name'])) {
			if (!empty($_REQUEST['save_template'])) {
				$ok = TRUE;
				if (!is_dir(self::getSavedTemplatesDir())) {
					$ok = mkdir(self::getSavedTemplatesDir(), 0770, TRUE);
				}
				if ($ok) $ok = copy($file_info['tmp_name'], self::getSavedTemplatesDir().basename($file_info['name']));
				if (!$ok) trigger_error("Problem saving template", E_USER_ERROR);

			}
			
						
			$bits = explode('.', $file_info['name']);
			$extension = strtolower(end($bits));
			$template_filename = basename($file_info['name']);
			$source_file = $file_info['tmp_name'];
			rename ($source_file, $source_file.'.'.$extension);
			$source_file = $source_file.'.'.$extension;

		} else if (!empty($_REQUEST['source_doc_select'])) {
			// NB basename for security to avoid path injections.
			$source_file = $template_filename = self::getSavedTemplatesDir().basename($_REQUEST['source_doc_select']);
			$bits = explode('.', $source_file);
			$extension = strtolower(end($bits));
		} else {
			trigger_error('Template file does not seem to have been uploaded or selected');
			return;
		}
		
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
			case '@@@debug@@@':	
				$view = $GLOBALS['system']->getDBObject('roster_view', $roster_id);
				$start_date = substr(array_get($_REQUEST, 'start_date', ''), 0, 10);
				$end_date = substr(array_get($_REQUEST, 'end_date', ''), 0, 10);
				$data = $view->printCSV($start_date, $end_date, TRUE);
				$labels = array();
				$roster = array();
				$people = array();
                $persons_unique = array();
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
							$labels['label'.$itemno] = str_replace('&','&amp;',$item);
							$itemno++;
						}
						for ($i = 0; $i > 20; $i++) {
							$labels['label'.$itemno] = '';
							$itemno++;
						}
						break;
					default:
						$rowno++;
						$roleno = 1;
						$roster_row = array();
						$persons = array();
						foreach ($row as $key => $item) {
							if ($key == 0) {
								$roster_row['date'] = $item;
								$date = $item;
							} elseif (is_numeric($key)) {
								$roster_row['role'.$roleno] = str_replace('&','&amp;',str_replace("\n", ', ', $item));
								$roster_row['role_cr'.$roleno] = $this->NewLines($extension, $item);
								$roster_row[str_replace(' ','_',$labels['label'.$roleno])] = $roster_row['role'.$roleno];
								$roster_row[str_replace(' ','_',$labels['label'.$roleno]).'_cr'] = $roster_row['role_cr'.$roleno];
								$hashes = explode("\n", $item);
								foreach ($hashes as $hash) {
									$persons[$hash] = 1;
								}
								$roleno++;
							} else {	
								$roster_row[$key] = trim($item);
								$roster_row[$key.'_cr'] = $this->NewLines($extension, trim($item));
								if ($key == 'topic') {
									$title = $roster_row['topic'];
								}
							}
						}
						for ($i = 0; $i > 20; $i++) {
							$labels['role'.$itemno] = '';
							$itemno++;
						}
						$roster[] = $roster_row;
						$peoples = array();
						foreach ($persons as $key => $value) {
							if (trim($key) <> '') {
								$peoples[] = trim($key);
							}
						}
						asort($peoples);
						foreach ($peoples as $value) {
							$people[] = array('date' => $date, 'name' => str_replace('&','&amp;',$value));
							$persons_unique[trim(str_replace('&','&amp;',$value))] = $value;
						}
						break;
					}	
				}
                $person = array();
                asort($persons_unique);
                foreach ($persons_unique as $key => $value) {
					$person[] = array('name' => $key);
                }

				if ($extension == '@@@debug@@@') {
					echo '<a href="javascript:history.go(-1)">Return</a>'."\n\n";
                    echo "<pre>\n";
				    echo '[onshow.system_name] = '.ifdef('SYSTEM_NAME', '')."\n";
				    echo '[onshow.timezone] = '.ifdef('TIMEZONE', '')."\n";
				    echo '[onshow.username] = '.$_SESSION['user']['username']."\n";
				    echo '[onshow.first_name] = '.$_SESSION['user']['first_name']."\n";
				    echo '[onshow.last_name] = '.$_SESSION['user']['last_name']."\n";
				    echo '[onshow.email] = '.$_SESSION['user']['email']."\n";
				    echo '[onshow.roster_view_name] = '.$_REQUEST['roster_view_name']."\n";
				    echo '[onshow.date] = '.$date."\n";
				    echo '[onshow.title] = '.$title."\n";
					echo "\n";
					foreach ($person as $line) {
						foreach ($line as $k => $v) {
							echo '[person.'.$k.'] = '.$v;
						}	
						echo "\n";
					}
					echo "\n";
					foreach ($labels as $k => $v) {
						echo '[labels.'.$k.'] = '.$v."\n";
					}
					echo "\n";
					foreach ($roster as $line) {
						foreach ($line as $k => $v) {
							echo '[roster.'.$k.'] = '.$v."\n";
						}	
						echo "\n";
					}
					foreach ($people as $line) {
						foreach ($line as $k => $v) {
							echo '[people.'.$k.'] = '.$v."\n";
						}	
						echo "\n";
					}

                    echo "</pre>\n";
					echo "\n\n".'<a href="javascript:history.go(-1)">Return</a>';
                    return;
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
				$TBS->VarRef['title'] = $title;
				$TBS->MergeBlock('labels', array($labels));
				$TBS->MergeBlock('roster', $roster);
				$TBS->MergeBlock('people', $people);
                $TBS->MergeBlock('person', $person);
				$filename = basename($template_filename);
				$bits = explode('.', $filename);
				$ext = array_pop($bits);
				$filename = implode('_', $bits).'_merged_'.date('Y-m-d').'.'.$ext;
				$TBS->Show(OPENTBS_DOWNLOAD, $filename);
				break;
			default:
				trigger_error("Format $extension not yet supported");
				return;
		}
	}
}
?>
