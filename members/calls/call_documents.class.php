<?php
class Call_Documents extends Call
{
	function run()
	{
		$dirs = explode('|', MEMBER_FILES_DIRS);
		
		$dirOK = FALSE;
		$filepath = realpath(Documents_Manager::getRootPath().'/'.$_REQUEST['getfile']);
		foreach ($dirs as $dir) {
			$fulldir = Documents_Manager::getRootPath().'/'.$dir;
			if (0 === strpos($filepath, $fulldir)) {
				$dirOK = TRUE;
			}
		}

		if (!$dirOK) {
			trigger_error("Illegal file directory requested");
			exit;
		}

		Documents_Manager::serveFile(Documents_Manager::getRootPath().'/'.$_REQUEST['getfile']);
	}
}