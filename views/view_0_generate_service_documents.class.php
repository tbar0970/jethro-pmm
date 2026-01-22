<?php
require_once 'include/bible_ref.class.php';
require_once 'include/odf_tools.class.php';
class View__Generate_Service_Documents extends View
{
	private $_congregations = Array();
	private $_service_date = '';
	private $_filename = '';
	private $_generated_files = Array();
	private $_replacements = Array();
	private $_keywords = Array();

	private $_dirs = Array();

	const EXTENSIONS = 'odt,odp,ott,otp,docx,pptx';

	static function getMenuPermissionLevel()
	{
		return PERM_SERVICEDOC;
	}

	static function getTemplates($op)
	{
		$dirs['populate'] = explode('|', ifdef('SERVICE_DOCS_TO_POPULATE_DIRS', 'Templates/To_Populate'));
		$dirs['expand'] = explode('|', ifdef('SERVICE_DOCS_TO_EXPAND_DIRS', 'Templates/To_Expand'));
		$opDirs = $dirs[$op];
		$found_files = Array();


		$rootpath = Documents_Manager::getRootPath();
		foreach ($opDirs as $i => $dir) {
			if (!is_dir($dir)) {
				if (is_dir($rootpath.'/'.$dir)) {
					$opDirs[$i] = $rootpath.'/'.$dir;
				}
			}
			if (!is_dir($opDirs[$i])) {
				//trigger_error("Bad config: ".self::_cleanDirName($dir)." does not exist");
				unset($opDirs[$i]);
				continue;
			}
		}
		if ($op == 'populate') {
			$res = Array();
			foreach ($opDirs as $dir) {
				$di = new DirectoryIterator($dir);
				foreach ($di as $fileinfo) {
					if ($fileinfo->isDir() && !$fileinfo->isDot()) {
						$found_files[$fileinfo->getFilename()] = $fileinfo->getPathname();
					}
				}
			}
		} else {
			foreach ($opDirs as $dir) {
				$di = new DirectoryIterator($dir);
				foreach ($di as $fileinfo) {
					if (!$fileinfo->isFile()) continue;
					$pathinfo = pathinfo($fileinfo->getFilename());
					if (in_array($pathinfo['extension'], explode(',', self::EXTENSIONS))) {
						$found_files[$fileinfo->getFilename()] = $fileinfo->getPathname();
					}
				}
			}
		}
		return $found_files;
	}

	static function resolveFilename($action, $basename)
	{
		$templates = self::getTemplates($action);
		return array_get($templates, $basename);
	}

	private function _processExpand()
	{
		$generated_files = Array();
		$pathinfo = pathinfo($this->_filename);
		$new_dirname = $pathinfo['dirname'].'/'.$this->_service_date;
		if (!file_exists($new_dirname)) {
			mkdir($new_dirname);
		}
		if (is_writable($new_dirname)) {
			chdir($new_dirname);
			foreach (self::getCongregations() as $congid => $cong) {
				$new_filename = substr($pathinfo['basename'], 0, -(strlen($pathinfo['extension'])+1)).'_'.$cong['meeting_time'].'.'.$pathinfo['extension'];
				if (file_exists($new_filename)) {
					if (!unlink($new_filename)) {
						trigger_error("Could not overwrite ".$new_filename.' - file open?');
						continue;
					}
				}
				copy($this->_filename, $new_filename);
				if ($p = fileperms($this->_filename)) chmod($new_filename, $p);
				ODF_Tools::replaceKeywords($new_filename, array_get($_POST['replacements'], $congid, Array()));
				$this->_generated_files[$new_dirname.'/'.$new_filename] = basename($pathinfo['dirname']).' / '.basename($new_dirname).' / '.$new_filename;
			}
		}
	}

	static function getCongregations()
	{
		static $congs = NULL;
		if (is_null($congs)) {
			$congs = $GLOBALS['system']->getDBObjectData('congregation', Array('!meeting_time' => ''));
		}
		return $congs;
	}

	public function processView()
	{
		if (!count(self::getCongregations())) {
			add_message("You need to enable services for some of your congregations before using this feature", 'failure');
			return;
		}

		$this->_service_date = process_widget('date', Array('type' => 'date'));
		if (empty($this->_service_date)) {
			add_message("No date supplied");
			return;
		}

		if (!in_array(array_get($_REQUEST, 'action'), Array('populate', 'expand'))) {
			add_message("Invalid action specified");
			return;
		}
		$this->_action = $_REQUEST['action'];

		if (empty($_REQUEST['filename'])) {
			add_message("no filename supplied");
			return;
		}
		$this->_filename = self::resolveFilename($this->_action, $_REQUEST['filename']);
		if (!$this->_filename) {
			add_message("Unkown template ".$_REQUEST['filename']);
			return;
		}
		
		if (!empty($_REQUEST['replacements'])) {
			$method = '_process'.ucfirst($this->_action).'';
			$this->$method();
		} else {
			$this->loadReplacements();
		}
	}

	public function printView()
	{
	
		$selfCongregations = self::getCongregations();
		if (empty($selfCongregations) || empty($this->_action) || empty($this->_service_date) || empty($this->_filename)) return;
		$selfCongregations = null;//Finished with temporary variable.

		if (!empty($this->_generated_files)) {
			echo "The following files were generated: <ul>";
			foreach ($this->_generated_files as $path => $label) {
				echo '<li><a href="?call=documents&dir='.self::_cleanDirName(dirname($path)).'&getfile='.basename($path).'">';
				echo ents($label);
				echo '</a></li>';
			}
			echo '</ul>';
			$fn_bits = explode('.', basename($this->_filename));
			$zipname = reset($fn_bits).'_'.$this->_service_date;
			$allHref = '?call=documents&zipname='.$zipname;
			foreach ($this->_generated_files as $path => $label) {
				$allHref .= '&zipfile[]='.self::_cleanDirName($path);
			}
			?>
			<script>
				document.location.href = '<?php echo $allHref; ?>';
			</script>
			<?php

		} else {
			$this->_printReplacementsForm();
		}
	}

	private function _printReplacementsForm()
	{
		$congs = self::getCongregations();
		$exampleCong = reset($congs);
		switch ($this->_action) {
			case 'populate':
				?>
				<p>This will open each congregation's template file (eg. <?php echo $exampleCong['meeting_time']; ?>.odt),<br />
					replace the keywords within it (eg. %SERVICE_DATE% or %NAME_OF_PREACHER%) <br />
					and save it as a new file named by service date (eg. <?php echo $this->_service_date.'_'.$exampleCong['meeting_time']; ?>.odt)
				</p>
				<?php
				break;
			case 'expand':
				$bits = explode('.', basename($this->_filename));
				$ext = array_pop($bits);
				$base = implode('.', $bits);
				$newFilename = $base.'_'.$exampleCong['meeting_time'].'.'.$ext;
				?>
				<p>This will make a new folder called "<?php echo $this->_service_date; ?>",<br />
					save into it a separate copy of <?php echo basename($this->_filename); ?> for each congregation
					(eg. <?php echo $newFilename; ?>)<br />
					and replace the keywords in each new file according to the applicable congregation.
				</p>
				<?php
				break;
		}
		?>
		Please confirm or correct the following keyword replacements:
			<form method="post">
			<input type="hidden" name="action" value="<?php echo $this->_action; ?>" />
			<input type="hidden" name="service_date" value="<?php echo $this->_service_date; ?>" />
			<table class="table table-condensed table-bordered table-auto-width">
				<thead>
				<tr>
					<th>Keyword</th>
			<?php
			foreach ($congs as $congid => $congregation) {
				?>
				<th>
					<?php
					echo ents($congregation['name'].' ('.$congregation['meeting_time'].')');
					?>
				</th>
				<?php
			}
			?>
				</tr>
				</thead>
				<tbody>
			<?php
			foreach ($this->_keywords as $keyword) {
				?>
				<tr>
					<td><?php echo ents($keyword); ?></td>
					<?php
				foreach (self::getCongregations() as $congid => $congregation) {
					?>
					<td>
						<?php
						if (!empty($this->_replacements[$congid])) {
							if (isset($this->_replacements[$congid]) && isset($this->_replacements[$congid][$keyword])) {
								?>
								<input type="text"
									   name="replacements[<?php echo (int)$congid; ?>][<?php echo ents($keyword); ?>]"
									   value="<?php echo ents($this->_replacements[$congid][$keyword]); ?>" />
								<?php
							}
						}
						?>
					</td>
					<?php
				}
				?>
				</tr>
			<?php
			}
			?>
				</tbody>
			</table>
			<input type="submit" class="btn" value="Go" />
			<a href="<?php echo build_url(Array()); ?>" class="btn">Cancel</a>
			</form>
			<?php
	}


	
	function _processPopulate()
	{
		$newDir = $this->_filename.'/'.$this->_service_date;
		if (!is_dir($newDir)) mkdir($newDir);
		chdir($this->_filename);

		$this->_replacements = $_POST['replacements'];
		foreach (self::getCongregations() as $congid => $cong) {
			foreach (explode(',', self::EXTENSIONS) as $ext) {
				$thisFile = $this->_filename.'/'.$cong['meeting_time'].'.'.$ext;
				if (is_file($thisFile)) {
					$newFile = $newDir.'/'.basename($thisFile);
					if (file_exists($newFile)) {
						if (!unlink($newFile)) {
							trigger_error('Cannot write to '.$newFile.' - is the file in use?');
							continue;
						}
					}
					copy($thisFile, $newFile);
					if ($p = fileperms($thisFile)) chmod($newFile, $p);
					//if (in_array('SERVICE_CONTENT', $this->_keywords)) {
						$service = Service::findByDateAndCong($this->_service_date, $congid);
						if ($service) {
							ob_start();
							$service->printServiceContent();
							$html = ob_get_clean();
							if ($html) {
								ODF_Tools::insertHTML($newFile, $html, '%SERVICE_CONTENT%');
							}
						}
					//}

					ODF_Tools::replaceKeywords($newFile, $this->_replacements[$congid]);
					$this->_generated_files[$newFile] = self::_cleanDirName($newDir).' / '.basename($newFile);
				}
			}
		}
	}

	function getTitle()
	{
		switch (array_get($_REQUEST, 'action')) {
			case 'populate':
				return 'Populate service documents';
			case 'expand':
				return 'Expand service documents';
		}
		return 'Generate service documents';
	}


	function loadReplacements()
	{
		$congs = self::getCongregations();
		$this->_keywords = $this->_cong_keywords = Array();
		if (is_file($this->_filename)) {
			$this->_keywords = ODF_Tools::getKeywords($this->_filename);
		} else if (is_dir($this->_filename)) {
			foreach (self::getCongregations() as $congid => $cong) {
				foreach (explode(',', self::EXTENSIONS) as $extn) {
					$filename = $this->_filename.'/'.$cong['meeting_time'].'.'.$extn;
					if (file_exists($filename)) {
						$this->_cong_keywords[$congid] = array_merge(array_get($this->_cong_keywords, $congid, Array()), ODF_Tools::getKeywords($filename));
						$this->_keywords = array_merge($this->_keywords, $this->_cong_keywords[$congid]);
					}
				}
			}
		} else {
			add_message("Could not find file ".$this->_filename, 'error');
		}
		$this->_keywords = array_unique($this->_keywords);

		$congs = self::getCongregations();
		foreach ($congs as $congid => $cong) {
			$service = Service::findByDateAndCong($this->_service_date, $congid);
			if (empty($service)) {
				add_message("Could not find service for ".$cong['name']." on ".$this->_service_date, 'failure');
				unset($congs[$congid]);
				continue;
			}
			$next_service = Service::findByDateAndCong(date('Y-m-d', strtotime($this->_service_date.' +1 week')), $congid);
			$list = is_file($this->_filename) ? $this->_keywords : array_get($this->_cong_keywords, $congid, Array());
			foreach ($list as $keyword) {
				$keyword = strtoupper($keyword);
				if (0 === strpos($keyword, 'NEXT_SERVICE_')) {
					if (!empty($next_service)) {
						$service_field = strtolower(substr($keyword, strlen('NEXT_SERVICE_')));
						$this->_replacements[$congid][$keyword] = $next_service->getValue($service_field);
						if ($service_field == 'date') {
							// make a short friendly date
							$this->_replacements[$congid][$keyword] = date('j M', strtotime($this->_replacements[$congid][$keyword]));
						}
					} else {
						$this->_replacements[$congid][$keyword] = '';
						add_message('NEXT_SERVICE keyword could not be replaced because no next service was found for '.$cong['name'], 'warning');
					}
				} else if (0 === strpos($keyword, 'CONGREGATION_')) {
					$cong_field = strtolower(substr($keyword, strlen('CONGREGATION_')));
					$this->_replacements[$congid][$keyword] = $cong[$cong_field];
				} else {
					$this->_replacements[$congid][$keyword] = $service->getKeywordReplacement($keyword);
				}
			}
		}
	}

	private static function _cleanDirName($dirname) {
		$dirname = str_replace('\\', '/', $dirname);
		$rootpath = Documents_Manager::getRootPath();
		$rootpath = str_replace('\\', '/', $rootpath);
		if (0 === strpos($dirname, $rootpath)) {
			return substr($dirname, strlen($rootpath));
		}
		return $dirname;
	}
	
}

