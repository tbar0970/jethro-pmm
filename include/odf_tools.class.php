<?php
Class ODF_Tools
{

	// Replace special chars in $x with their ODF equivalents
	static function odfEntities($x)
	{
		$res = str_replace("&", '&amp;', $x);
		$res = str_replace("'", '&apos;', $res);
		$res = str_replace('"', '&quot;', $res);
		$res = str_replace('>', '&gt;', $res);
		$res = str_replace('<', '&lt;', $res);
		return $res;
	}

	// get the content of the XML file within the ODF
	static function getXML($filename, $xml_filename='content.xml')
	{
		if (!file_exists($filename)) {
			trigger_error("ODT file does not exist $filename");
			return;
		}
		$inzip = new ZipArchive;
		if (TRUE !== $inzip->open($filename)) {
			trigger_error("Could not open ODT file $filename");
			return;
		}
		$content = $inzip->getFromName($xml_filename);
		if (!$content) {
			trigger_error("Could not find content.xml inside $filename");
			return;
		}
		$inzip->close();
		return $content;
	}

	// Set the content of the XML file within the ODF
	static function setXML($filename, $content, $xml_filename='content.xml')
	{
		if (!is_writeable($filename)) {
			trigger_error("ODT file is not writeable $filename");
			return;
		}
		$outzip = new ZipArchive;
		if (TRUE !== $outzip->open($filename, ZipArchive::CREATE)) {
			trigger_error("Could not open ODT file $filename");
			return;
		}

		if (!$outzip->addFromString($xml_filename, $content)) {
			trigger_error("Could not write content.xml back to file");
			return;
		}

		if (!$outzip->close()) {
			trigger_error("Could not write content back to $filename");
			return;
		}

		return TRUE;
	}

	// Replace keywords within the XML files within the ODF
	static function replaceKeywords($filename, $replacements, $xml_filenames=NULL)
	{
		if (is_null($xml_filenames)) {
			$xml_filenames = Array('content.xml', 'styles.xml');
		}
		foreach ($xml_filenames as $xml_filename) {
			$xml = ODF_Tools::getXML($filename, $xml_filename);
			foreach ($replacements as $k => $v) {
				$xml = str_replace('%'.strtoupper($k).'%', ODF_Tools::odfEntities(trim($v)), $xml);
			}
			ODF_TOOLS::setXML($filename, $xml, $xml_filename);
		}
	}

	// Find the keywords that exist within the XML files within the ODF
	static function getKeywords($filename, $xml_filenames=NULL)
	{
		$res = Array();
		if (is_null($xml_filenames)) {
			$xml_filenames = Array('content.xml', 'styles.xml');
		}
		foreach ($xml_filenames as $xml_filename) {
			$txt = ODF_Tools::getXml($filename, $xml_filename);
			$matches = Array();
			preg_match_all('/%([a-zA-Z0-9_]*)%/', $txt, $matches);
			$res = array_unique(array_merge($res, $matches[1]));
		}
		return $res;
	}
}
