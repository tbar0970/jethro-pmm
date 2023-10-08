<?php
include_once 'calls/abstract_call_document_merge.class.php';
class Call_Document_Merge_Rosters extends Abstract_Call_Document_Merge
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
		
		$this->GetTemplate();

		switch ($this->extension) {
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
						$itemno = 0;
						$roleno = 1;
						$roster_row = array();
						$persons = array();
						foreach ($row as $item) {
							switch ($itemno) {
								case 0:
									$roster_row['date'] = $item;
									$date = $item;
									$itemno++;
									break;
								case 1:
									$roster_row['format'] = $item;
									$itemno++;
									break;
								case 2:
									$roster_row['topic'] = trim($item);
									$title = $roster_row['topic'];
									$itemno++;
									break;
								case 3:
									$roster_row['notes'] = $item;
									$roster_row['notes_cr'] = $this->NewLines($this->extension, $item);
									$itemno++;
									break;
								case 4:
									$roster_row['comment'] = $item;
									$itemno++;
									break;
								default:
									$roster_row['role'.$roleno] = str_replace('&','&amp;',str_replace("\n", ', ', $item));
									$roster_row['role_cr'.$roleno] = $this->NewLines($this->extension, $item);
									$hashes = explode("\n", $item);
									foreach ($hashes as $hash) {
										$persons[$hash] = 1;
									}
									$itemno++;
									$roleno++;
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

				if ($this->ShowKeywords) {
				    $this->Keyword('roster_view_name', $_REQUEST['roster_view_name']);
				    $this->Keyword('date', $date);
				    $this->Keyword('title', $title);
					$this->Keyword();
					$this->KeywordSection('person');
					foreach ($person as $line) {
						foreach ($line as $k => $v) {
							$this->Keyword($k, $v);
						}	
					}
					$this->Keyword();
					$this->KeywordSection('labels');
					foreach ($labels as $k => $v) {
						$this->Keyword($k, $v);
					}
					$this->Keyword();
					$this->KeywordSection('roster');
					foreach ($roster as $line) {
						foreach ($line as $k => $v) {
							$this->Keyword($k, $v);
						}	
						$this->Keyword();
					}
					$this->Keyword();
					$this->KeywordSection('people');
					foreach ($people as $line) {
						foreach ($line as $k => $v) {
							$this->Keyword($k, $v);
						}	
						$this->Keyword();
					}

                    $this->_printKeywordList();
                    return;
                }

				$TBS = $this->newTBS();
				$TBS->VarRef['roster_view_name'] = $_REQUEST['roster_view_name'];
				$TBS->VarRef['date'] = $date;
				$TBS->VarRef['title'] = $title;
				$TBS->MergeBlock('labels', array($labels));
				$TBS->MergeBlock('roster', $roster);
				$TBS->MergeBlock('people', $people);
                		$TBS->MergeBlock('person', $person);
				$this->downloadTBS($TBS);
				break;
			default:
				trigger_error("Format $this->extension not yet supported");
				return;
		}
	}
}
?>
