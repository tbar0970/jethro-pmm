<?php
class Documents_Manager {

	public static function getRootPath()
	{
		if (ifdef('DOCUMENTS_ROOT_PATH') && is_dir(DOCUMENTS_ROOT_PATH)) {
			return DOCUMENTS_ROOT_PATH;
		} else {
			return JETHRO_ROOT.'/files';
		}
	}

	// If $filename is an acceptable filename (extension not prohibited, no leading dot, no slashes)
	// returns it intact; else triggers an error and returns blank string
	public static function validateFileName($filename) {
		$ext = self::getExtension($filename);
		if (in_array($ext, Array('php', 'php3', 'php4', 'inc', 'act'))) {
			trigger_error('File extension "'.$ext.'" is not allowed');
			return '';
		}
		if ($filename[0] == '.') {
			trigger_error('Files beginning with dot are not allowed');
			return '';
		}
		if (FALSE !== strpos($filename, '/') || FALSE !== strpos($filename, '\\')) {
			trigger_error('Files containing slashes are not allowed');
			return '';
		}
		return $filename;
	}

	// Checks $path doesn't contain invalid parameters and converts it to a full filename path
	public static function validateDirPath($path)
	{
		$bits = explode('/', $path);
		if (in_array('.', $bits) || in_array('..', $bits)) {
			trigger_error('Dot or double-dot not allowed in directory parameter', E_USER_ERROR); //exits
		}
		$res = self::getRootPath().implode('/', $bits);
		if (!is_dir($res)) {
			trigger_error("Specified folder does not exist", E_USER_ERROR); // exits
		}
		return $res;
	}

	// If $name is a valid dir name, returns a cleaned version of it (spaces to underscores)
	// Else triggers an error and returns empty string
	public static function validateDirName($name) {
		$name = str_replace(' ', '_', $name);
		if (!preg_match('/[-_A-Za-z0-9&]+/', $name)) {
			trigger_error("Invalid folder name");
			return '';
		}
		return $name;
	}
	
	
	public static function serveFile($filename)
	{
		$filename = realpath($filename); // resolve any trickiness like ../..
		if (0 !== strpos($filename, self::getRootPath())) {
			trigger_error("Illegal file path requested: $filename");
			exit;
		}
		$mime = function_exists('mime_content_type') ? mime_content_type($filename) : '';
		if (self::isImage($filename)) {
			if (empty($_REQUEST['bin'])) {
				?>
				<html>
					<head>
						<title><?php echo ents($filename); ?></title>
					</head>
					<body>
						<img src="<?php echo build_url(Array('bin'=>1)); ?>" style="max-width: 100%" />
					</body>
				</html>
				<?php
			} else {
				if (empty($mime)) $mime = 'image/'.self::getExtension($filename);
				header('Content-type: '.$mime);
				readfile($filename);
			}
		} else if (self::isHTML($filename)) {
			// No extra headers needed for HTML docs
			readfile($filename);
		} else if (self::isPDF($filename)) {
			// PDFs can often be displayed inline
			if (empty($mime)) $mime = 'application/pdf';
			header('Content-type: '.$mime);
			readfile($filename);
		} else {
			// download
			if (empty($mime)) $mime = self::_guessContentType($filename);
			header("Pragma: public"); // required
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Cache-Control: private",false); // required for certain browsers
			header("Content-Transfer-Encoding: binary");
			header('Content-type: '.$mime);
			header('Content-Disposition: attachment; filename="'.basename($filename).'"');
			readfile($filename);
		}
	}

	private static function _guessContentType($filename)
	{
		switch (self::getExtension($filename)) {
			case "pdf": $ctype="application/pdf"; break;
			case "exe": $ctype="application/octet-stream"; break;
			case "zip": $ctype="application/zip"; break;
			case "doc":
			case "docx": $ctype="application/msword"; break;
			case "xls": $ctype="application/vnd.ms-excel"; break;
			case "ppt": $ctype="application/vnd.ms-powerpoint"; break;
			case "gif": $ctype="image/gif"; break;
			case "png": $ctype="image/png"; break;
			case "jpeg":
			case "jpg": $ctype="image/jpg"; break;
			default: $ctype="application/force-download";
		}
		return $ctype;
	}

	private static function getExtension($filename)
	{
		return strtolower(substr($filename, strrpos($filename, '.')+1));
	}

	public static function isHTML($filename)
	{
		return in_array(self::getExtension($filename), Array('html', 'htm'));
	}

	public static function isImage($filename)
	{
		return in_array(self::getExtension($filename), Array('png', 'jpg', 'jpeg', 'gif'));
	}

	public static function isPDF($filename) {
		return self::getExtension($filename) == 'pdf';
	}



}