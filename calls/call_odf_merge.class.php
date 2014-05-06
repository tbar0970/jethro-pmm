<?php
require_once 'include/odf_tools.class.php';
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
		$source_file = $source_file['tmp_name'];
		$content = ODF_Tools::getXML($source_file);
		
		if (empty($content)) {
			trigger_error('File does not seem to be an ODT file');
			return;
		}
		define('HEADER_END', '</text:sequence-decls>');
		define('FOOTER_START', '</office:text>');
		$middle_start_pos = strpos($content, HEADER_END)+strlen(HEADER_END);
		$middle_end_pos = strpos($content, FOOTER_START);
		if ((NULL === $middle_start_pos) || (NULL === $middle_end_pos)) {
			trigger_error('Cannot locate body content of the file');
			return;
		}
		$middle_template = substr($content, $middle_start_pos, ($middle_end_pos - $middle_start_pos));
		$header = substr($content, 0, $middle_start_pos);
		$footer = substr($content, $middle_end_pos);

		$merged_middle = '';
		switch (array_get($_REQUEST, 'merge_type')) {
			case 'family':
				$GLOBALS['system']->includeDBClass('family');
				$merge_data = Family::getFamilyDataByMemberIDs($_POST['personid']);
				$dummy = new Family();
				break;
			case 'person':
			default:
				$merge_data = $GLOBALS['system']->getDBObjectData('person', Array('id' => $_POST['personid']));
				$GLOBALS['system']->includeDBClass('person');
				$dummy = new Person();
				break;
		}
		foreach ($merge_data as $id => $row) {
			$this_middle = $middle_template;
			@$dummy->populate($id, $row);
			foreach ($row as $k => $v) {
				if ($k == 'history') continue;
				if ($dummy->hasField($k)) {
					$v = $dummy->getFormattedValue($k);
				}
				$this_middle = str_replace('%'.strtoupper($k).'%', ODF_Tools::odfEntities(trim($v)), $this_middle);
			}
			$merged_middle .= $this_middle;
		}

		$merged_file = dirname($source_file).'/jethro_merged_'.time().session_id();
		copy($source_file, $merged_file);
		ODF_Tools::setXML($merged_file, $header.$merged_middle.$footer);

		header('Content-type: application/force-download');
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");
		header('Content-Disposition: attachment; filename="'.urlencode('MERGED_'.basename($_FILES['source_document']['name'])).'"');
		//header('Content-Length: '. filesize($merged_file));
		readfile($merged_file);
		flush();

	}
}


?>
