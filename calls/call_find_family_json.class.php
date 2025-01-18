<?php
class Call_Find_Family_JSON extends Call
{
	function run()
	{
		$results = Array();
		if (!empty($_REQUEST['search'])) {
			$name = $_REQUEST['search'];
			$results = $GLOBALS['system']->getDBObjectData('family', Array('family_name' => '%'.$_REQUEST['search'].'%'));
			header ("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
			header ("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
			header ("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
			header ("Pragma: no-cache"); // HTTP/1.0
			//header ("Content-Type: application/json");
			echo "{\"results\": [";
			$arr = array();
			$GLOBALS['system']->includeDBClass('family');
			foreach ($results as $i => $details) {
				$arr[] = '
					{
						id: '.$i.',
						value: "'.addcslashes(ents($details['family_name']), '"').'",
						info: "'.addcslashes(ents($details['members']), '"').'"
					}
				';
			}
			echo implode(", ", $arr);
			echo "]}";
		}
	}
}