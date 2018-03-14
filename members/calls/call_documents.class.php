<?php
class Call_Documents extends Call
{
	function run()
	{
		$dirs = explode('|', MEMBER_FILES_DIRS);
		$dirOK = FALSE;
		foreach ($dirs as $dir) {
			if (0 === strpos($_REQUEST['getfile'], $dir)) {
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