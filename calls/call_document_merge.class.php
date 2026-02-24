<?php
class Call_Document_Merge extends Call
{
	const SHOWKEYWORDS = '@@DUMP_KEYWORDS@@';

	public static function getSavedTemplatesDir()
	{
		return Documents_Manager::getRootPath().'/Templates/To_Merge/';
	}

	function run()
	{
		if (array_get($_REQUEST, 'template_format') == 'legacy') {
			$this->runLegacy();
			exit;
		}

		$file_info = array_get($_FILES, 'source_document');
		$content = null;
		if (isset($_REQUEST['preview_keywords'])) {
			$extension = self::SHOWKEYWORDS;
		} else if (!empty($file_info['tmp_name'])) {
			if (!empty($_REQUEST['save_template'])) {
				$ok = TRUE;
				if (!is_dir(self::getSavedTemplatesDir())) {
					$ok = mkdir(self::getSavedTemplatesDir(), 0770, TRUE);
				}
				if ($ok) $ok = copy($file_info['tmp_name'], self::getSavedTemplatesDir().basename($file_info['name']));
				if (!$ok) throw new \RuntimeException("Problem saving template");

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
		
		$data_type = array_get($_REQUEST, 'data_type', 'none');

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
            case self::SHOWKEYWORDS:
				$merge_type = array_get($_REQUEST, 'merge_type', 'person');
				if ($merge_type != 'family') { $merge_type = 'person'; }
				$data = $this->getMergeData();
				if ($extension == self::SHOWKEYWORDS) {
					ob_start();
				    echo '[onshow.system_name] = '.ifdef('SYSTEM_NAME', '')."\n";
				    echo '[onshow.timezone] = '.ifdef('TIMEZONE', '')."\n";
				    echo '[onshow.username] = '.$_SESSION['user']['username']."\n";
				    echo '[onshow.first_name] = '.$_SESSION['user']['first_name']."\n";
				    echo '[onshow.last_name] = '.$_SESSION['user']['last_name']."\n";
				    echo '[onshow.email] = '.$_SESSION['user']['email']."\n";
                }

				require_once 'include/tbs.class.php';
				include_once 'include/tbs_plugin_opentbs.php';
				if (ini_get('date.timezone')=='') {
					date_default_timezone_set('UTC');
				}
				$TBS = new clsTinyButStrong;
				$TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN);
				$TBS->SetOption('noerr', TRUE);
				$TBS->ResetVarRef(false);
				$TBS->VarRef['x_num'] = 3152.456;
				$TBS->VarRef['x_pc'] = 0.2567;
				$TBS->VarRef['x_dt'] = mktime(13,0,0,2,15,2010);
				$TBS->VarRef['x_bt'] = true;
				$TBS->VarRef['x_bf'] = false;
				$TBS->VarRef['x_delete'] = 1;
				$TBS->VarRef['yourname'] = $_SESSION['user']['username'];
				$TBS->VarRef['system_name'] = ifdef('SYSTEM_NAME', '');
				$TBS->VarRef['timezone'] = ifdef('TIMEZONE', '');
				$TBS->VarRef['username'] = $_SESSION['user']['username'];
				$TBS->VarRef['first_name'] = $_SESSION['user']['first_name'];
				$TBS->VarRef['last_name'] = $_SESSION['user']['last_name'];
				$TBS->VarRef['email'] = $_SESSION['user']['email'];
				$i = $this->TabulatedData($extension, $TBS, 'dates', 60, 'date');
				if ($extension == self::SHOWKEYWORDS) {
				    echo '[onshow.dates] = '.$i."\n";
				}
				$TBS->VarRef['dates'] = $i;
				$i = $this->TabulatedData($extension, $TBS, 'groups', 20, 'group');
				if ($extension == self::SHOWKEYWORDS) {
				    echo '[onshow.groups] = '.$i."\n";
				}
				$TBS->VarRef['groups'] = $i;
				if (isset($_REQUEST['tables'])) {
					$tables = (array)$_REQUEST['tables'];
					foreach ($tables as $table) {
						$i = $this->TabulatedData($extension, $TBS, $table);
						if ($extension == self::SHOWKEYWORDS) {
							echo '[onshow.'.$table.'s] = '.$i."\n";
						}
						$TBS->VarRef[$table.'s'] = $i;
					}
				}
				
				if ($merge_type == 'person') {
					// Replicate to status and group tables
					// Create extra arrays
					$GLOBALS['system']->includeDBClass('person_group');
					$group_status_list = Person_Group::getMembershipStatusOptionsAndDefault();
					$GLOBALS['system']->includeDBClass('person');
					$member_status_list = Person::getStatusOptions();
					$List_of_lists = array();
					foreach ($group_status_list[0] as $status_item) {
						$List_of_lists[$status_item] = array();
						foreach ($data as $line) {
							if (isset($line['Membership Status'])) {
								if ($line['Membership Status'] == trim($status_item)) {
									$List_of_lists[trim($status_item)][] = $line;
								}
							}
						}
					}
					foreach ($member_status_list as $status_item) {
						$List_of_lists[$status_item] = array();
						foreach ($data as $line) {
							if ($line['status'] == trim($status_item)) {
								$List_of_lists[trim($status_item)][] = $line;
							}
						}
					}
				}
				
				if ($extension == self::SHOWKEYWORDS) {
					echo "\n";
					if ($merge_type == 'person') {
						foreach ($data as $line) {
							foreach ($line as $k => $v) {
								echo '[person.'.$k.'] = '.$v."\n";
							}
							echo "\n";
						}
						foreach ($List_of_lists as $i => $a) {
							echo "\n";
							foreach ($a as $line) {
								foreach ($line as $k => $v) {
									echo '['.$i.'.'.$k.'] = '.$v."\n";
								}
								echo "\n";
							}
						}
					}
					if ($merge_type == 'family') {
						foreach ($data as $line) {
							foreach ($line as $k => $v) {
								echo '[family.'.$k.'] = '.$v."\n";
							}
							echo "\n";
						}
					}
                    $this->_printKeywordList(ob_get_clean());
					return;
                }
				$TBS->LoadTemplate($source_file, OPENTBS_ALREADY_UTF8);
				$TBS->MergeBlock($merge_type, $data);
				if ($merge_type == 'person') {
					foreach ($List_of_lists as $i => $a) {
						$TBS->MergeBlock($i, $a);
					}
				}
				$filename = basename($template_filename);
				$bits = explode('.', $filename);
				$ext = array_pop($bits);
				$filename = implode('_', $bits).'_merged_'.date('Y-m-d').'.'.$ext;

				header('Content-type: application/force-download');
				header("Content-Type: application/octet-stream");
				header("Content-Type: application/download");
				header('Content-Disposition: attachment; filename="'.urlencode('MERGED_'.basename($source_file)).'"');

				$TBS->Show(OPENTBS_DOWNLOAD, $filename);
				break;

			default:
				trigger_error("Format $extension not yet supported");
				return;
		}

	}

	protected function TabulatedData($extension, $TBS, $table, $max = 60, $base = '')
	{
		$i = 0;
		if (isset($_REQUEST[$table])) {
			if ($base == '') { $base = $table; }
			$data = (array)$_REQUEST[$table];
			foreach ($data as $bit) {
				$i++;
				$TBS->VarRef[$base.$i] = $bit;
				if ($extension == self::SHOWKEYWORDS) {
					echo '[onshow.'.$base.$i.'] = '.$bit."\n";
				}
			}
		}
		$j = $i;
		while ($i <= $max) {
			$i++;
			$TBS->VarRef[$base.$i] = '';
		}
		return $j;
	}

	protected function getMergeData()
	{
		$return_data = array();
		$GLOBALS['system']->includeDBClass('family');
		$GLOBALS['system']->includeDBClass('person');
		$GLOBALS['system']->includeDBClass('person_query');
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
				// Get details about each person
				$merge_data = $GLOBALS['system']->getDBObjectData('person', Array('id' => (array)$_POST['personid']));
				if (!empty($_REQUEST['queryid'])) {
					// If our merge has originated from a person report, add the report's columns
					// (eg "selected groups", "other family members" etc) to the merge data.
					// Make sure the report returns a 'flat' array by removing grouping.
					$query = $GLOBALS['system']->getDBObject('person_query',$_REQUEST['queryid']);
					$params = $query->getValue('params');
					$params['group_by'] = NULL;
					$query->setValue('params', $params);
					$merge_data2 = $query->printResults('array');
					foreach ($merge_data2 as $data) {
						$personid = $data['Person ID'];
						if (isset($merge_data[$personid])) $merge_data[$personid] += $data;
					}
				}
                foreach (Person::getCustomMergeData($_POST['personid'], FALSE) as $personid => $data) {
					$merge_data[$personid] += $data;
				}
				$dummy = new Person();
				$dummy_family = new Family();
				break;
		}
		
		// We have a switch instead of an if because we may add more, such as rosters
		switch ($data_type) {
			case 'attendance_tabular':
				if (isset($_REQUEST['dates'])) {
					$dates = (array)$_REQUEST['dates'];
				} else {
					$dates = array();
				}
				if (isset($_REQUEST['groups'])) {
					$groups = (array)$_REQUEST['groups'];
				} else {
					$groups = array();
				}
				if (isset($_REQUEST['data2'])) {
					$data2 = (array)$_REQUEST['data2'];
				} else {
					$data2 = array();
				}
				$data = array();
				$group_status = array();
				foreach ($data2 as $k => $dat) {
					$dat2 = explode(';', $dat[0]);
					$data[$k] = explode(',', $dat2[0]);
					$group_status[$k] = $dat2[1];
				}
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
				foreach ($headerrow as $Hash) {
					$lastrow[$Hash] = '';
				}
				$dat = 1;
				foreach ($dates as $date) {
					$grp = 1;
					foreach ($groups as $group) {
						$headerrow[$date.$group.'l'] = 'date'.$dat.'_group'.$grp.'_letter';
						$headerrow[$date.$group.'n'] = 'date'.$dat.'_group'.$grp.'_number';
						$grp++;
					}
					$headerrow[$date] = 'date'.$dat;
					$dat++;
				}
				break;
		}

		if (array_get($_REQUEST, 'merge_type') == 'family') {
			foreach ($merge_data as $id => $row) {
				$order_array[] = $id;
			}
		} else {
			$order_array = (array)$_POST['personid'];
		}
		foreach ($order_array as $id) {
			$row = $merge_data[$id];
			@$dummy->populate($id, $row);
			$outputrow = Array('id' => $id);
			foreach ($row as $k => $v) {
				if ($k == 'history') continue;
				if ($k == 'familyid') continue;
				if ($k == 'feed_uuid') continue;

				// NB below we use keys from $headerrow, such as "suburb"
				// AND raw keys such as "address_suburb", to support both new and legacy formats.

				if ($dummy->hasField($k)) {
					$outputrow[$headerrow[$k]] = $outputrow[$k] = str_replace("\n", ' ',$dummy->getFormattedValue($k, $v)); // pass value to work around read-only fields
				} else if ($dummy_family && $dummy_family->hasField($k)) {
					$outputrow[$headerrow[$k]] = $outputrow[$k] = $dummy_family->getFormattedValue($k, $v);
				} else if ($k == 'selected_firstnames') {
					$outputrow['selected_members'] = strval($v);
					$outputrow[$k] = strval($v);
				} else {
					$outputrow[$k] = str_replace("\n", '; ', strval($v));
				}
			}
			switch ($data_type) {
				case 'attendance_tabular':
					$outputrow['Membership Status'] = $group_status[$id];
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
							$sumval++;
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
		return $return_data;
	}


	function runLegacy()
	{
		$source_file = array_get($_FILES, 'source_document');
		$content = null;
		if (empty($source_file['tmp_name'])) {
			trigger_error('Template file does not seem to have been uploaded');
			return;
		}
		$extension = strtolower(pathinfo($source_file['name'], PATHINFO_EXTENSION));
		$source_file = $source_file['tmp_name'];
		$merged_file = dirname($source_file).'/jethro_merged_'.time().session_id();

		switch ($extension) {
			case 'odt':
				$this->mergeLegacyODT($source_file, $merged_file);
				break;

			case 'docx':
				$this->mergeLegacyDOCX($source_file, $merged_file);
				break;

			default:
				trigger_error("Format $extension not yet supported");
				return;
		}

		if (file_exists($merged_file)) {

			header('Content-type: application/force-download');
			header("Content-Type: application/octet-stream");
			header("Content-Type: application/download");
			header('Content-Disposition: attachment; filename="'.urlencode('MERGED_'.basename($_FILES['source_document']['name'])).'"');
			header('Content-Length: '. filesize($merged_file));

			readfile($merged_file);
			unlink($merged_file);
			flush();
		}
	}


	function mergeLegacyDOCX($source_file, $merged_file)
	{
		// Important: we get the merge data first, because the phpWord
		// autoloader included below stuffs up the Jethro autoloader
		// and causes errors.
		$data = array_values($this->getMergeData());

		require_once 'vendor/autoload.php';
		\PhpOffice\PhpWord\Settings::setTempDir(sys_get_temp_dir());
		\PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(TRUE);
		$templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($source_file);

		$vars = $templateProcessor->getVariables();
		if (!$templateProcessor->cloneBlock('MERGEBLOCK', count($data))) {
			if (empty($vars)) {
				trigger_error("You don't seem to have included any \${keywords} in your file; cannot merge");
				return;
			}
			$templateProcessor->cloneRow(reset($vars), count($data));

		}
		foreach ($data as $num => $row) {
			$setVars = Array();
			foreach ($row as $k => $v) {
				$templateProcessor->setValue(strtoupper($k).'#'.($num+1), $this->xmlEntities($v));
				$setVars[strtoupper($k)] = TRUE;
			}
			// Set blank value for any remaining keywords
			foreach ($vars as $v) {
				if (!isset($setVars[strtoupper($v)])) {
					$templateProcessor->setValue(strtoupper($v).'#'.($num+1), '');
				}
			}
		}
		$templateProcessor->saveAs($merged_file);
	}

	function mergeLegacyODT($source_file, $merged_file)
	{
		$xml_filename = 'content.xml';
		require_once 'include/odf_tools.class.php';

		$content = ODF_Tools::getXML($source_file, $xml_filename);
		$keywords = ODF_Tools::getKeywords($source_file, $xml_filename);
		if (empty($content)) {
			trigger_error('Could not find content within this '.$extension.' file');
			return;
		}
		$HEADER_END = '</text:sequence-decls>';
		$FOOTER_START = '</office:text>';

		$middle_start_pos = strpos($content, $HEADER_END)+strlen($HEADER_END);
		$middle_end_pos = strpos($content, $FOOTER_START);
		if ((NULL === $middle_start_pos) || (NULL === $middle_end_pos)) {
			trigger_error('Cannot locate body content of the file');
			return;
		}
		$middle_template = substr($content, $middle_start_pos, ($middle_end_pos - $middle_start_pos));
		$header = substr($content, 0, $middle_start_pos);
		$footer = substr($content, $middle_end_pos);

		$merged_middle = '';

		foreach ($this->getMergeData() as $id => $row) {
			if (empty($row)) continue;
			$this_middle = $middle_template;
			$replaced = Array();
			foreach ($row as $k => $v) {
				$this_middle = str_replace('%'.strtoupper($k).'%', ODF_Tools::odfEntities(trim($v)), $this_middle);
				$replaced[strtoupper($k)] = TRUE;
			}
			// replace keywords without values with blanks
			foreach ($keywords as $k) {
				if (!isset($replaced[strtoupper($k)])) {
					$this_middle = str_replace('%'.strtoupper($k).'%', '', $this_middle);
				}
			}
			$merged_middle .= $this_middle;
		}

		$merged_file = dirname($source_file).'/jethro_merged_'.time().session_id();
		copy($source_file, $merged_file);

		ODF_Tools::setXML($merged_file, $header.$merged_middle.$footer, $xml_filename);
	}

	protected function xmlEntities($x)
	{
		$res = str_replace("&", '&amp;', $x);
		$res = str_replace("'", '&apos;', $res);
		$res = str_replace('"', '&quot;', $res);
		$res = str_replace('>', '&gt;', $res);
		$res = str_replace('<', '&lt;', $res);
		return $res;
	}

	private function _printKeywordList($text)
	{
		?>
		<!DOCTYPE html>
		<head>
			<?php include 'templates/head.template.php'; ?>
		</head>
		<body id="body">
			<p>The following is a preview of all the tags that can be used, and the values they would be replaced with.<br />
				Note that the first keyword in the template must define a new TBS block by adding <code>;block=tbs:row</code> to the field, eg <code>[person.first_name;block=tbs:row]</code><br />
				For full details see the <a href="?call=document_merge_help">Mail Merge Help page</a>.</p>
		<table class="table table-bordered table-condensed">
			<thead>
				<tr>
					<th class="narrow">Keyword</th>
					<th>First value</th>
				</tr>
			<?php
			$i = 0;
			foreach (explode("\n", $text) as $line) {
				$i++;
				?>
				<tr>
				<?php
				if (preg_match('/([^=]+)=(.*)/', $line, $matches)) {
					//echo '<tr><td data-action="copy" data-target="#keyword'.$i.'"><code id="keyword'.$i.'">'.ents($matches[1]).'</code></td><td>'.ents($matches[2]).'</td></tr>';
					//echo '<tr><td><input type="text" readonly="readonly" id="keyword'.$i.'" value="'.ents($matches[1]).'" /></td><td>'.ents($matches[2]).'</td></tr>';
					echo '<tr><td><code id="keyword'.$i.'">'.ents($matches[1]).'</code></td><td>'.ents($matches[2]).'</td></tr>';
				} else {
					?>
					<th colspan="2"><?php echo ents($line); ?></th>
					<?php
				}
				?>
				</tr>
				<?php
			}
			?>
			</thead>
		</table>
		<script>
			$('code').click(function() {
				TBLib.selectElementText(this);
			})
		</script>
		</body>
		<?php
	}

}
