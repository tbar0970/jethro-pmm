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
			trigger_error("Could not find ".$xml_filename." inside $filename");
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
			return FALSE;
		}
		$outzip = new ZipArchive;
		if (TRUE !== $outzip->open($filename, ZipArchive::CREATE)) {
			trigger_error("Could not open ODT file $filename");
			return FALSE;
		}

		if (!$outzip->addFromString($xml_filename, $content)) {
			trigger_error("Could not write content.xml back to file");
			return FALSE;
		}

		if (!$outzip->close()) {
			trigger_error("Could not write content back to $filename");
			return FALSE;
		}

		return TRUE;
	}

	// Replace keywords within the XML files within the ODF
	static function replaceKeywords($filename, $replacements, $xml_filenames=NULL)
	{
		if (is_null($xml_filenames)) {
			if (substr(strtolower($filename), -4) == 'docx') {
				$xml_filenames = Array('word/document.xml');
			} else {
				$xml_filenames = Array('content.xml', 'styles.xml');
			}
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
		foreach ((array)$xml_filenames as $xml_filename) {
			$txt = ODF_Tools::getXml($filename, $xml_filename);
			$matches = Array();
			preg_match_all('/%([a-zA-Z0-9_]*)%/', $txt, $matches);
			$res = array_unique(array_merge($res, $matches[1]));
		}
		return $res;
	}
	
	
	
	function renameTag( DOMElement $oldTag, $newTagName ) {
		$document = $oldTag->ownerDocument;

		$newTag = $document->createElement($newTagName);
		$oldTag->parentNode->replaceChild($newTag, $oldTag);

		foreach ($oldTag->attributes as $attribute) {
			$newTag->setAttribute($attribute->name, $attribute->value);
		}
		foreach (iterator_to_array($oldTag->childNodes) as $child) {
			$newTag->appendChild($oldTag->removeChild($child));
		}
		return $newTag;
	}
	
	/**
	 * @param $xml string Your XML
	 * @param $old string Name of the old tag
	 * @param $new string Name of the new tag
	 * @return string New XML
	 */
	static function renameTags($dom, $old, $new, $newAttrs, $namespace)
	{
		$nodes = $dom->getElementsByTagName($old);
		$toRemove = array();
		foreach ($nodes as $node) {
			$newNode = $dom->createElement($new);
			foreach ($newAttrs as $key => $val) {
				$newNode->setAttribute($key, $val);
			}
			foreach (iterator_to_array($node->childNodes) as $child) {
				$newNode->appendChild($node->removeChild($child));
			}

			$node->parentNode->insertBefore($newNode, $node);
			$toRemove[] = $node;
		}

		foreach ($toRemove as $node) {
			$node->parentNode->removeChild($node);
		}
	}
	
	static function insertFileIntoFile($sourceFile, $targetFile, $placeholder)
	{
		$xmlFilename = 'word/document.xml'; // todo: sniff filename to support ODT
		//$namespace = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0'; //  ODT
		$namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'; // DOCX
		$relsNamespace = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
		$imageNamespace = 'urn:schemas-microsoft-com:vml';
		$prefix = 'jethromerge-';
		
		$targetXML = self::getXML($targetFile, $xmlFilename);
		if (!$targetXML) {
			trigger_error("Cannot get target file XML");
			return FALSE;
		}
		$targetDOM = new DOMDocument();
		$targetDOM->loadXML($targetXML);
		$targetDOM->preserveWhiteSpace = true;
		$targetDOM->formatOutput = true;

		// Find where to insert into the document
		$insertPoint = NULL;
		foreach ($targetDOM->getElementsByTagNameNS($namespace, 'p') as $elt) {
			if (trim($elt->textContent) == $placeholder) {
				$insertPoint = $elt;
				break;
			}
		}
		if (NULL === $insertPoint) {
			trigger_error("Could not find $placeholder in a paragraph to insert the new content");
			self::bam($targetDOM->saveXML());
			return FALSE;
		}
		
		$sourceXML = self::getXML($sourceFile, $xmlFilename);

		if (!$sourceXML) {
			trigger_error("Cannot get source file XML");
			return FALSE;
		}
		$sourceDOM = new DomDocument();
		$sourceDOM->loadXML($sourceXML);
		$sourceDOM->preserveWhiteSpace = true;
		$sourceDOM->formatOutput = true;
		
		$bodies = $sourceDOM->getElementsByTagNameNS($namespace, 'body');
		if (empty($bodies)) {
			trigger_error("Could not get body of source document");
			return FALSE;
		}
		
		foreach ($bodies as $body) {
			// Add prefix to the imagedata relid attributes
			$imageDataElts = $body->getElementsByTagNameNS($imageNamespace, 'imagedata');
			foreach ($imageDataElts as $img) {
				$relID = $img->getAttributeNS($relsNamespace, 'id');
				$newRelID = str_replace('rId', 'rId10', $relID);
				$img->setAttributeNS($relsNamespace, 'id', $newRelID);
			}

			foreach ($body->childNodes as $newNode) {
				$newNew = $targetDOM->importNode($newNode, true);
				$insertPoint->parentNode->insertBefore($newNew, $insertPoint);
			}
		}
 
 		$insertPoint->parentNode->removeChild($insertPoint);
		
		if (!ODF_Tools::setXML($targetFile, $targetDOM->saveXML(), $xmlFilename)) {
			return FALSE;
		}

		$relsXML = self::getXML($sourceFile, 'word/_rels/document.xml.rels');
		if (empty($relsXML)) {
			trigger_error("Could not get XML from source word/rels/document.xml.rels");
			return FALSE;
		}
		$sourceRelsDOM = new DOMDocument();
		$sourceRelsDOM->loadXML($relsXML);
		$sourceRelsDOM->preserveWhiteSpace = true;
		$sourceRelsDOM->formatOutput = true;

		$relsXML = self::getXML($targetFile, 'word/_rels/document.xml.rels');
		if (empty($relsXML)) {
			trigger_error("Could not get XML from target word/rels/document.xml.rels");
			return FALSE;
		}
		$targetRelsDOM = new DOMDocument();
		$targetRelsDOM->loadXML($relsXML);
		$targetRelsDOM->preserveWhiteSpace = true;
		$targetRelsDOM->formatOutput = true;

		$rels = $sourceRelsDOM->getElementsByTagName('Relationship');
		$targetParent = $targetRelsDOM->getElementsByTagName('Relationships');
		// For each relationship, add the prefix to its relID and its target filename
		// then add it to the target relationships file
		foreach ($rels as $rel) {
			if ($rel->getAttribute('Type') != 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image') {
				continue;
			}
			$relID = $rel->getAttribute('Id');
			// incremeent the RID number by 100, eg ID="rId103"
			$newRelID = str_replace('rId', 'rId10', $relID);
			$rel->setAttribute('Id', $newRelID);
			$target = $rel->getAttribute('Target');
			$rel->setAttribute('Target', str_replace("media/", "media/".$prefix, $target));
			foreach ($targetParent as $parent) {
				$newNew = $targetRelsDOM->importNode($rel, true);
				$parent->appendChild($newNew);
			}
		}
		if (!self::setXML($targetFile, $targetRelsDOM->saveXML(), 'word/_rels/document.xml.rels')) {
			return FALSE;
		}

		// Copy the images from old to new DOCX archives, prepending names.
		$sourceZIP = new ZipArchive;
		$sourceZIP->open($sourceFile);
		$targetZIP = new ZipArchive;
		$targetZIP->open($targetFile, ZipArchive::CREATE);
		for ($i = 0; $i < $sourceZIP->numFiles; $i++) {
			$filename = $sourceZIP->getNameIndex($i);
			if (preg_match('#^word/media/(.*).jpg$#', $filename)) {
				$newFilename = str_replace('media/', 'media/'.$prefix, $filename);
				// Copy the file across.
				$content = $sourceZIP->getFromIndex($i);

				// REMOVE THIS TO GET RID OF ERROR
				$targetZIP->addFromString($newFilename, $content);
				unset($content);
			}
		}

		// Add the default extension jpg entry to [Content_Types].xml
		$cts = $targetZIP->getFromName('[Content_Types].xml');
		$tag = '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
		$cts = str_replace($tag, $tag.'<Default ContentType="image/jpeg" Extension="jpg"/>', $cts);

		// REMOVE THIS TO GET RID OF ERROR
		$targetZIP->addFromString('[Content_Types].xml', $cts);
		
		//$targetZIP->addFromString('foobar.txt', 'foobar');

		// THE ERROR GOES AWAY WHEN I REMOVE BOTH OF THE ABOVE
		//

		$sourceZIP->close();
		$targetZIP->close();
		return TRUE;








	}
	
	static function insertHTML($filename, $html, $placeholder='%CONTENT%')
	{
		if (!strlen($html)) return FALSE;
		$xml = self::getXML($filename);
		if (!$xml) {
			trigger_error("Couldn't get Content XML from $filename");
			return FALSE;
		}

		$dom = new DOMDocument();
		$dom->loadXML($xml);
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;

		// Find where to insert into the document
		$insertPoint = NULL;
		foreach ($dom->getElementsByTagNameNS('urn:oasis:names:tc:opendocument:xmlns:text:1.0', 'p') as $elt) {
			if (trim($elt->textContent) == $placeholder) {
				$insertPoint = $elt;
				break;
			}
		}
		if (NULL === $insertPoint) {
			trigger_error("Could not find $placeholder in a paragraph to insert the new content");
			return FALSE;
		}

		$paraStyle = $insertPoint->getAttribute('text:style-name');

		// ADD STYLES
		$styleData = Array(
			'JethroBold' => Array(
							'fo:font-weight' => 'bold',
							),
			'JethroItalic' => Array(
							'fo:font-style' => 'italic',
			),
			'JethroUnderline' => Array(
							'style:text-underline-style' => 'solid',
							'style:text-underline-width' => 'auto',
							'style:text-underline-color' => 'font-color',
			),
			'JethroSmall' => Array(
							'fo:font-size' => '6pt',
			),
		);
		$aStylesElt = $dom->getElementsByTagNameNS('urn:oasis:names:tc:opendocument:xmlns:office:1.0', 'automatic-styles')->item(0);
		foreach ($styleData as $styleName => $attrs) {
			$newStyle = $dom->createElement('style:style');
			$newStyle->setAttribute('style:name', $styleName);
			if ($styleName == 'JethroSmall') {
				$newStyle->setAttribute('style:family', 'paragraph');
				$newStyle->setAttribute('style:parent-style-name', $paraStyle);
			} else {
				$newStyle->setAttribute('style:family', 'text');
			}
			$aStylesElt->appendChild($newStyle);
			$newTp = $dom->createElement('style:text-properties');
			foreach ($attrs as $key => $val) {
				$newTp->setAttribute($key, $val);
				if (0 === strpos('fo:', $key)) {
					$newTp->setAttribute($key.'-asian', $val);
					$newTp->setAttribute($key.'-complex', $val);
				}
			}
			$newStyle->appendChild($newTp);
		}

		// ADD CONTENT
		$map = Array(
			'p'  => Array('text:p', Array('text:style-name' => $paraStyle)),
			'br' => Array('text:line-break', Array()),
			'b'  => Array('text:span', Array('text:style-name' => 'JethroBold')),
			'strong'  => Array('text:span', Array('text:style-name' => 'JethroBold')),
			'i'  => Array('text:span', Array('text:style-name' => 'JethroItalic')),
			'em'  => Array('text:span', Array('text:style-name' => 'JethroItalic')),
			'u'   => Array('text:span', Array('text:style-name' => 'JethroUnderline')),
			'small' => Array('text:p', Array('text:style-name' => 'JethroSmall')),
		);
		for ($i=1; $i <=6; $i++) {
			$map['h'.$i] = Array(
				'text:h',
				Array('text:outline-level' => $i),
			);
		}

		$input = strip_tags($html, '<'.implode('>,<', array_keys($map)).'>');
		$input = preg_replace('#[>]\s+#', '>', $input); // strip space after tags which would cause funny indents
		$input = '<body>'.$input.'</body>';
		$htmlDom = new DOMDocument();
		$htmlDom->preserveWhiteSpace = false;
		$htmlDom->formatOutput = true;
		$htmlDom->loadHTML($input);

		$newTags = Array();
		foreach ($map as $from => $to) {
			list($newTag, $newAttrs) = $to;
			ODF_Tools::renameTags($htmlDom, $from, $newTag, $newAttrs, 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');
			$newTags[] = $newTag;
		}
		
		// Now we need to get the result XML text and load it into a real XML DOM, not an HTML one
		$fragmentXML = $htmlDom->saveXML();
		$begin = strpos($fragmentXML, '<body>')+6;
		$length = strpos($fragmentXML, '</body>') - $begin;
		$fragmentXML = '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0" xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0" xmlns:number="urn:oasis:names:tc:opendocument:xmlns:datastyle:1.0" xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0" xmlns:chart="urn:oasis:names:tc:opendocument:xmlns:chart:1.0" xmlns:dr3d="urn:oasis:names:tc:opendocument:xmlns:dr3d:1.0" xmlns:math="http://www.w3.org/1998/Math/MathML" xmlns:form="urn:oasis:names:tc:opendocument:xmlns:form:1.0" xmlns:script="urn:oasis:names:tc:opendocument:xmlns:script:1.0" xmlns:ooo="http://openoffice.org/2004/office" xmlns:ooow="http://openoffice.org/2004/writer" xmlns:oooc="http://openoffice.org/2004/calc" xmlns:dom="http://www.w3.org/2001/xml-events" xmlns:xforms="http://www.w3.org/2002/xforms" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:rpt="http://openoffice.org/2005/report" xmlns:of="urn:oasis:names:tc:opendocument:xmlns:of:1.2" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:grddl="http://www.w3.org/2003/g/data-view#" xmlns:tableooo="http://openoffice.org/2009/table" xmlns:field="urn:openoffice:names:experimental:ooo-ms-interop:xmlns:field:1.0" office:version="1.2">
		'.substr($fragmentXML, $begin, $length).'</office:document-content>';
		$fragmentDom = new DOMDocument();
		$fragmentDom->loadXML($fragmentXML);

		foreach ($fragmentDom->firstChild->childNodes as $newNode) {
			$newNew = $dom->importNode($newNode, true);
			$insertPoint->parentNode->insertBefore($newNew, $insertPoint);
		}
		$insertPoint->parentNode->removeChild($insertPoint);

		return ODF_Tools::setXML($filename, $dom->saveXML());
	}

	static function bam($x)
	{
		if (!headers_sent()) {
			header('Content-disposition: inline');
			header('Content-type: text/html');
		}
		if (is_string($x)) $x = htmlentities($x);
		bam($x);
	}
	
}