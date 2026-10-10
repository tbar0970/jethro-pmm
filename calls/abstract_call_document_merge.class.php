<?php
require_once 'include/tbs.class.php';
include_once 'include/tbs_plugin_opentbs.php';

abstract class Abstract_Call_Document_Merge extends Call
{
	const SHOWKEYWORDS = '@@DUMP_KEYWORDS@@';
	
	protected $extension;
	protected $source_file;
	protected $filename;
	protected $ShowKeywords;
	protected $KeyWordList = array();
	protected $LastKeyWordSection = 'onshow';
	protected $FirstShow = true;
	
	public static function getSavedTemplatesDir()
	{
		return Documents_Manager::getRootPath().'/Templates/To_Merge/';
	}

	function run()
	{

	}

	protected function GetTemplate()
	{
		if (ini_get('date.timezone')=='') {
			date_default_timezone_set('UTC');
		}
		
		$file_info = array_get($_FILES, 'source_document');
		
		$this->ShowKeywords = isset($_REQUEST['preview_keywords']);
		
		if ($this->ShowKeywords) {
			$this->extension = self::SHOWKEYWORDS;
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
			$this->extension = strtolower(end($bits));
			$template_filename = basename($file_info['name']);
			$source_file = $file_info['tmp_name'];
			rename ($source_file, $source_file.'.'.$this->extension);
			$this->source_file = $source_file.'.'.$this->extension;

		} else if (!empty($_REQUEST['source_doc_select'])) {
			// NB basename for security to avoid path injections.
			$this->source_file = $template_filename = self::getSavedTemplatesDir().basename($_REQUEST['source_doc_select']);
			$bits = explode('.', $this->source_file);
			$this->extension = strtolower(end($bits));
		} else {
			trigger_error('Template file does not seem to have been uploaded or selected');
			return;
		}
		
		if (isset($template_filename)) {
			$filename = basename($template_filename);
			$bits = explode('.', $filename);
			$this->extension = array_pop($bits);
			$this->filename = implode('_', $bits).'_merged_'.date('Y-m-d').'.'.$this->extension;
			$this->filename = urlencode(str_replace(' ','_',$this->filename));
		}
	}	

	protected function newTBS() 
	{
		$TBS = new clsTinyButStrong;
		$TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN);
		$TBS->SetOption('noerr', TRUE);
		$TBS->ResetVarRef(false);
		$TBS->VarRef['system_name'] = ifdef('SYSTEM_NAME', '');
		$TBS->VarRef['timezone'] = ifdef('TIMEZONE', '');
		$TBS->VarRef['username'] = $_SESSION['user']['username'];
		$TBS->VarRef['first_name'] = $_SESSION['user']['first_name'];
		$TBS->VarRef['last_name'] = $_SESSION['user']['last_name'];
		$TBS->VarRef['email'] = $_SESSION['user']['email'];
		$TBS->LoadTemplate($this->source_file, OPENTBS_ALREADY_UTF8);
		return $TBS;
	}
	
	protected function downloadTBS($TBS)
	{
		header('Content-type: application/force-download');
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");
		header('Content-Disposition: attachment; filename="'.$this->filename.'"');
		$TBS->Show(OPENTBS_DOWNLOAD, $this->filename);
	}
	
	protected function TabulatedData($TBS, $table, $max = 60, $base = '')
	{
		$i = 0;
		if (isset($_REQUEST[$table])) {
			if ($base == '') { $base = $table; }
			$data = (array)$_REQUEST[$table];
			$this->KeywordSection('onshow');
			foreach ($data as $bit) {
				$i++;
				$TBS->VarRef[$base.$i] = $bit;
				$this->Keyword($base.$i, $bit);
			}
		}
		$j = $i;
		while ($i <= $max) {
			$i++;
			$TBS->VarRef[$base.$i] = '';
		}
		return $j;
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

	protected function KeywordSection($Section)
	{
		$this->LastKeyWordSection = $Section;
	}
	protected function Keyword($Word = '', $Value = '')
	{
		if ($this->ShowKeywords) {
			if ($this->FirstShow) {
				$this->KeyWordList[] = array('onshow.system_name', ifdef('SYSTEM_NAME', ''));
				$this->KeyWordList[] = array('onshow.timezone', ifdef('TIMEZONE', ''));
				$this->KeyWordList[] = array('onshow.username', $_SESSION['user']['username']);
				$this->KeyWordList[] = array('onshow.first_name', $_SESSION['user']['first_name']);
				$this->KeyWordList[] = array('onshow.last_name', $_SESSION['user']['last_name']);
				$this->KeyWordList[] = array('onshow.email', $_SESSION['user']['email']);
				$this->FirstShow = false;
            }

			if ($Word == '') {
				$this->KeyWordList[] = array('', '');
			} else {	
				$this->KeyWordList[] = array($this->LastKeyWordSection.'.'.$Word, $Value);
			}
		}
	}
	protected function _printKeywordListHeader()
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
					<th>Value</th>
				</tr>
			<?php
	}
	protected function _printKeywordListFooter()
	{
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
	protected function _printKeywordList()
	{
		$prev_blank = false;
		$this->_printKeywordListHeader();
		$i = 0;
		foreach ($this->KeyWordList as $KeyList) {
			$i++;
			echo "<tr>";
			if ($KeyList[0] == '') {
				if (! $prev_blank) {
					echo '<th colspan="2"> </th>';
					$prev_blank = true;
				}
			} else {
				echo '<tr><td><code id="keyword'.$i.'">['.ents($KeyList[0]).']</code></td><td>'.ents($KeyList[1]).'</td></tr>';
				$prev_blank = false;
			}
			echo "</tr>\n";
		}

		$this->_printKeywordListFooter();
	}

}
