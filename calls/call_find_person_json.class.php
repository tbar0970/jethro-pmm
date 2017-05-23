<?php
class Call_Find_Person_JSON extends Call
{
	function run()
	{
		$results = Array();
		if (!empty($_REQUEST['search'])) {
			$name = $_REQUEST['search'];
			$GLOBALS['system']->includeDBClass('person');
			$results = Person::getPersonsBySearch($name, array_get($_REQUEST, 'include_archived', false));
			header ("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
			header ("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
			header ("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
			header ("Pragma: no-cache"); // HTTP/1.0
			header ("Content-Type: application/json");
			echo "{\"results\": [";
			$arr = array();
			$GLOBALS['system']->includeDBClass('person');
			$dummy = new Person();
			$count = 0;
			foreach ($results as $i => $details) {
				if ($count++ > 12) break;
				$dummy->populate($i, $details);
				$arr[] = '
					{
						id: '.$i.',
						value: "'.addcslashes(ents($details['first_name'].' '.$details['last_name']), '"').'",
						info: "'.addcslashes(ents($dummy->getFormattedValue('status').', '.$dummy->getFormattedValue('congregationid')), '"').'"
					}
				';
			}
			echo implode(", ", $arr);
			echo "]}";
		}
	}
}
?>
