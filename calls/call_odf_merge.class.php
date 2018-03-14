<?php
class Call_ODF_Merge extends Call
{
	function run()
	{
		$source_file = array_get($_FILES, 'source_document');
		$content = null;
		if (empty($source_file['tmp_name'])) {
			trigger_error('Template file does not seem to have been uploaded');
			return;
		}
		$extension = @strtolower(end(explode('.', $source_file['name'])));
		$source_file = $source_file['tmp_name'];
		$merged_file = dirname($source_file).'/jethro_merged_'.time().session_id();

		switch ($extension) {
			case 'odt':
				$this->mergeODT($source_file, $merged_file);
				break;

			case 'docx':
				$this->mergeDOCX($source_file, $merged_file);
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



	function mergeDOCX($source_file, $merged_file)
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

	function mergeODT($source_file, $merged_file)
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

	protected function getMergeData()
	{
		$merge_type = array_get($_REQUEST, 'merge_type', 'person');
		switch ($merge_type) {
			case 'family':
				$GLOBALS['system']->includeDBClass('family');
				$merge_data = Family::getFamilyDataByMemberIDs($_POST['personid']);
				$dummy = new Family();
				break;
			case 'person':
			default:
				$temp_merge_data = $GLOBALS['system']->getDBObjectData('person', Array('id' => $_POST['personid']));
				foreach (Person::getCustomMergeData($_POST['personid']) as $personid => $data) {
					$temp_merge_data[$personid] += $data;
				}

				$merge_data = Array();
				foreach ($_REQUEST['personid'] as $id) {
					$merge_data[$id] = $temp_merge_data[$id];
				}
				$GLOBALS['system']->includeDBClass('person');
				$dummy = new Person();
				break;
		}
		foreach ($merge_data as $id => $row) {
			@$dummy->populate($id, $row);
			foreach ($row as $k => $v) {
				if ($k == 'history') unset($merge_data[$id][$k]);
				if ($dummy->hasField($k)) {
					$merge_data[$id][$k] = $dummy->getFormattedValue($k);
				}
				if ($k == 'selected_firstnames') $merge_data[$id]['selected_members'] = $v;
			}
		}
		return $merge_data;
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
}