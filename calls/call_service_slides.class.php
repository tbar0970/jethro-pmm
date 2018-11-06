<?php

class Call_Service_slides extends Call
{	
	function run()
	{
		//get service data from database
		$service = $GLOBALS['system']->getDBObject('service', (int)$_REQUEST['serviceid']);
		$serviceContent = $service->getServiceContent();

		//options
		$fileToModify = 'content.xml';
		$date = str_replace(' ', '',$service->getFormattedValue("date"));
		$cong = $service->getFormattedValue("congregationid");
		$outFileName = 'Slides_'.$date.'_'. $cong.'.odp';
		
		$templateFilename = Documents_Manager::getRootPath().'/Templates/slide_template.odp';
		$outname = Documents_Manager::getRootPath().'/'.$outFileName;
		if (!file_exists($templateFilename)) {
			// no custom template found - use the built-in template
			$templateFilename = JETHRO_ROOT.'/resources/slide_template.odp';
		}
		if (file_exists($templateFilename)) {
			//open template file
			$zip = new ZipArchive;

			if ($zip->open($templateFilename) === TRUE) {
				//Read contents into memory
				$xml = $zip->getFromName($fileToModify);
				$zip->close();
				} else {
					echo 'failed to open template';
				}
				
				$dom = new DOMDocument('1.0', "UTF-8");
				$dom->loadXML($xml);
				
				//Set up DOM & Namespaces
				$xpath = new DomXPath($dom);
				$xpath->registerNamespace("draw","urn:oasis:names:tc:opendocument:xmlns:drawing:1.0");
				$xpath->registerNamespace("presentation","urn:oasis:names:tc:opendocument:xmlns:office:1.0");
				
				//Get pages in template
				$pages = $xpath->query("//office:presentation/node()");
				$last = $pages->length; //used later to remove templates
				
				//Define Template slides in file:
				$blank = $pages->item(0);
				$template = $pages->item(1);
				$alternate = $pages->item(2);  // alternate for identical slides (eg italic)
				
				//starting slideid
				$slideid = 10;

				//Count service items to deal with
				$numitems = count($serviceContent);
				//loop for each 
				for($x = 0; $x < $numitems; $x++) {
					//Split off content type (eg song, prayer, etc)
					$title = explode(": ",$serviceContent[$x][0],2);
					
					//Skip non-songs if option is set
					if (SLIDES_ALL == 0 and ($title[0] !== 'Song')) { 
						continue;
					} elseif (SLIDES_ALL == 1 and ($title[0] !== 'Song')){
						$title[0] = ($serviceContent[$x][0]);
					}
					
					//Break song into verses (<p> tag)
					$verses = explode ("</p>",$serviceContent[$x][1]);
					//reset variable for checking if slides are repeated
					$previousVerse = null;
					$altFlag = false;
					$numVerses = max((count($verses)-1),1); // -1 for extra </p> tag at end, floor at 1 to make a single blank slide appear

					for ($a=0; $a < $numVerses; $a++) {
						if (trim($verses[$a]) === trim($previousVerse)) { //use alternate slide if content is same as previous slide
							$newpage = $alternate->cloneNode(true);
						} else {
							$newpage = $template->cloneNode(true);
						}
						
						$newslide = $template->parentNode->insertBefore($newpage,$pages->item($last-1));
						$newslide->setAttribute('draw:name',('Slide'.($slideid)));//Set slide ID
						$slideid++;
						
						//Get all text lines on slide & loop
						$textelements = $xpath->query(".//text:span",$newslide);
						$numtextelements = $textelements->length;
						
						
						
						for($y = 0; $y < $numtextelements; $y++) {
							if (strcmp($textelements->item($y)->nodeValue, "title") == 0) { //title textbox
								$textelements->item($y)->nodeValue = array_values(array_slice($title, -1))[0]; //get last element of title array
							} elseif (strcmp($textelements->item($y)->nodeValue, 'contents') == 0) { //contents textbox

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
									$textlines->item($z)->nodeValue = html_entity_decode(strip_tags($lines[$z]));
								}	
								
							} elseif (strcmp($textelements->item($y)->nodeValue, 'credit') == 0) { //credits textbox

								$slideNumText = 'Slide '.($a+1).' of '.($numVerses);
								
								if ($a < $numVerses-1) {
									$textelements->item($y)->nodeValue = $slideNumText;
								} else {
									//deal with multiline text
									$lines = array_filter(array_merge(array($slideNumText),explode('<br />',$serviceContent[$x][2])));
									$numlines = count($lines);
								
									//clone nodes for each line of text
									for ($z = 0; $z < ($numlines-1); $z++) {	
										$line = $textelements->item($y)->parentNode->cloneNode(true);
										$newline = $textelements->item($y)->parentNode->parentNode->appendChild($line);								
									}
									//find text elements within cloned nodes above
									$textlines = $xpath->query(".//*[text()[contains(., 'credit')]]",$textelements->item($y)->parentNode->parentNode);

									//populate text elements
									for ($z = 0; $z < ($numlines); $z++) {
										$textlines->item($z)->nodeValue = html_entity_decode(htmlspecialchars($lines[$z]));						
									}
								}
							} 
						}
				
						$dom->saveXML();
						$previousVerse = $verses[$a];
					}
					// Add blank slide after each item
					$newpage = $blank->cloneNode(true);
					$newslide = $template->parentNode->insertBefore($newpage,$pages->item($last-1));
					$newslide->setAttribute('draw:name',('Slide'.($slideid)));//Set slide ID
					$slideid++;
				}
				
				//remove original template slides
				for ($z = 0; $z < ($last-1); $z++) {
					$pages->item($z)->parentNode->removeChild($pages->item($z));
				}
				
				//save xml & zip
				$result = $dom->saveXML();
				
				if(!copy($templateFilename, $outname)) {
					die("Could not copy '$templateFilename' to '$outname'");
				}
								
				if ($zip->open($outname) === TRUE) {
					$zip->deleteName($fileToModify);
					$zip->addFromString($fileToModify, $result);
					$zip->close();

					header('Content-Type: application/zip');
					header('Content-disposition: attachment; filename='.$outFileName);
					header('Content-Length: ' . filesize($outname));
					readfile($outname);
					unlink($outname);
			} else {
				echo 'failed';
			}
		} else {
			// Couldn't find any template (!) - dump the temporary raw file.
				echo 'failed to open template';
		}
	}
}
?>
