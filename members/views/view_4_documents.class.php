<?php
class View_Documents extends View
{
	public static function getMenuRequiredFeature()
	{
		// Only make it visible if the config is set
		return strlen(ifdef('MEMBER_FILES_DIRS', '')) ? 'DOCUMENTS' : '-1';
	}
	
	function processView()
	{
	}
	
	function getTitle()
	{
		return 'Documents';
	}

	function printView()
	{
		$dirs = explode('|', MEMBER_FILES_DIRS);
		chdir(Documents_Manager::getRootPath());
		if (count($dirs) > 1) {
			foreach ($dirs as $dir) {
				echo '<h3><i class="icon-folder-open"></i> '.$dir.'</h3>';
				echo '<div class="indent-left">';
				$this->_printDir($dir);
				echo '</div>';
			}
		} else {
			$this->_printDir(reset($dirs));
		}
	}

	private function _printDir($dir)
	{
		$files = glob($dir.'/*');
		foreach ($files as $f) {
			if (is_dir($f)) {
				?>
				<h3><i class="icon-folder-open"></i> <?php echo ents(basename($f)); ?></h3>
				<div class="indent-left">
				<?php
				$this->_printDir($f);
				?>
				</div>
				<?php
			} else {
				?>
				<p><a target="_new" href="?call=documents&getfile=<?php echo urlencode($f); ?>"><i class="icon-picture"></i><?php echo ents(basename($f)); ?></a></p>
				<?php
			}
		}
	}

}