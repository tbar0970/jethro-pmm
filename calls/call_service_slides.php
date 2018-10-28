<?php

class Call_Service_slides extends Call
{	
	function run()
	{
		//get service data from database
		$service = $GLOBALS['system']->getDBObject('service', (int)$_REQUEST['serviceid']);
		$serviceContent = $service->getServiceContent();

		//options
		$songsOnly = true; //print only songs, or all service components
		$templateFilename = 'files/template.odp';
		$fileToModify = 'content.xml';
		$slidesFilename = 'files/slides.odp';	

		
		//open template file & copy template to a new file
		$zip = new ZipArchive;
		if(!copy($templateFilename, $slidesFilename)) {
			die("Could not copy '$templateFilename' to '$slidesFilename'");
		}

		if ($zip->open($slidesFilename) === TRUE) {
			//Read contents into memory
			$xml = $zip->getFromName($fileToModify);
			$dom = new DOMDocument('1.0', "UTF-8");
			$dom->loadXML($xml);
			
			//Set up DOM & Namespaces
			$xpath = new DomXPath($dom);
			$xpath->registerNamespace("draw","urn:oasis:names:tc:opendocument:xmlns:drawing:1.0");
			$xpath->registerNamespace("presentation","urn:oasis:names:tc:opendocument:xmlns:office:1.0");
			
			//Get pages in template
			$pages = $xpath->query("//office:presentation/node()");
			$last = count($pages); //used later to remove templates
			
			//Define Template slides in file:
			$blank = $pages->item(0);
			$template = $pages->item(1);
			$alternate = $pages->item(2);  // alternate for identical slides (eg italic)
			
			//starting slideid
			$slideid = 10;
			
			//Parser to strip html
	//	$Parser = new SimpleHtmlToText\Parser();
			
			//Count service items to deal with
			$numitems = count($serviceContent);
			//loop for each 
			for($x = 0; $x < $numitems; $x++) {
				//Split off content type (eg song, prayer, etc)
				$title = explode(": ",$serviceContent[$x][0],2);
				
				//Skip non-songs if option is set
				if ($songsOnly and ($title[0] !== 'Song')) { 
					continue;
				}
				
				//Break song into verses (<p> tag)
				$verses = explode ("</p>",$serviceContent[$x][1]);
 				//reset variable for checking if slides are repeated
				$previousVerse = null;
				$altFlag = false;
				$numVerses = count($verses)-1; //extra </p> tag at end
				
				for ($a=0; $a <= $numVerses; $a++) {
					if (trim($verses[$a]) === trim($previousVerse)) {
						$altFlag = !$altFlag;
					}
					
					//Select what sort of template slide to use
					if ($a == $numVerses) {
						$newpage = $blank->cloneNode(true);
					} elseif ($altFlag) {
						$newpage = $alternate->cloneNode(true);
					} else {
						$newpage = $template->cloneNode(true);
					}
					
					$newslide = $template->parentNode->insertBefore($newpage,$pages->item($last-1));
					$newslide->setAttribute('draw:name',('Slide'.($slideid)));//Set slide ID
					$slideid++;
					
					//Get all text lines on slide & loop
					$textelements = $xpath->query(".//text:span",$newslide);
					$numtextelements = count($textelements);
					
					
					
					for($y = 0; $y < $numtextelements; $y++) {
						if ($textelements->item($y)->nodeValue === "title") { //title textbox
							if (count($title) == 2) {
								$textelements->item($y)->nodeValue = $title[1];
							} else {
								$textelements->item($y)->nodeValue = "";
							}
						} elseif ($textelements->item($y)->nodeValue === "contents") { //contents textbox

							//deal with multiline text
							$line = $textelements->item($y)->parentNode->cloneNode(true);
							$lines = array();
							$lines = explode('<br />',$verses[$a]);
							$numlines = count($lines);
							
							//clone nodes for each line of text
							for ($z = 1; $z < ($numlines); $z++) {	
								$line = $textelements->item($y)->parentNode->cloneNode(true);
								$newline = $textelements->item($y)->parentNode->parentNode->appendChild($line);								
							}
							//find text elements within cloned nodes above
							$textlines = $xpath->query(".//*[text()[contains(., 'contents')]]",$textelements->item($y)->parentNode->parentNode);
							//populate text elements							
							for ($z = 0; $z < ($numlines); $z++) {
								$textlines->item($z)->nodeValue = html_entity_decode(strip_tags($lines[$z]));//$Parser->parseString($lines[$z]);			
							}	
							
						} elseif ($textelements->item($y)->nodeValue === "credit") { //credits textbox

							$slideNumText = 'Slide '.($a+1).' of '.($numVerses);
							
							if ($a < $numVerses) {
								$textelements->item($y)->nodeValue = $slideNumText;
							} else {
								//deal with multiline text
								$line = $textelements->item($y)->parentNode->cloneNode(true);
								$lines = explode('<br />',($slideNumText.'<br />'.$serviceContent[$x][2]));
								$numlines = count($lines);
							
								//clone nodes for each line of text
								for ($z = 1; $z < ($numlines); $z++) {	
									$line = $textelements->item($y)->parentNode->cloneNode(true);
									$newline = $textelements->item($y)->parentNode->parentNode->appendChild($line);								
								}
								//find text elements within cloned nodes above
								$textlines = $xpath->query(".//*[text()[contains(., 'credit')]]",$textelements->item($y)->parentNode->parentNode);

								//populate text elements							
								for ($z = 0; $z < ($numlines); $z++) {
									$textlines->item($z)->nodeValue = $lines[$z];						
								}
							}
						} 
					}
			
					$dom->saveXML();
					$previousVerse = $verses[$a];
				}
			}
			
			//remove original template slides
			for ($z = 0; $z < ($last-1); $z++) {
				$pages->item($z)->parentNode->removeChild($pages->item($z));
			}
			
			//save xml & zip
			$result = $dom->saveXML();
			$zip->deleteName($fileToModify);
			$zip->addFromString($fileToModify, $result);
			$zip->close();
			
			header('Content-Type: application/zip');
			header('Content-disposition: attachment; filename='.$slidesFilename);
			header('Content-Length: ' . filesize($slidesFilename));
			readfile($slidesFilename);
			echo 'ok';
		} else {
			echo 'failed';
		}

	}
}
?>
