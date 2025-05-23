<?php
class Call_vcf extends Call
{
	function run()
	{
		// Ref: https://en.wikipedia.org/wiki/VCard
		header('Content-type: text/vcf');
		header('Content-Disposition: attachment; filename="contacts_'.date('Y-m-d_h:i').'.vcf"');
		$GLOBALS['system']->includeDBClass('person');
		$GLOBALS['system']->includeDBClass('family');
		$merge_data = $GLOBALS['system']->getDBObjectData('person', Array('id' => $_REQUEST['personid']));
		$dummy = new Person();
		$family = new Family();
	
		foreach ($merge_data as $id => $row) {
			$dummy->populate($id, $row);
			$family->setvalue('home_tel', $row['home_tel']);
			echo "BEGIN:VCARD\n";
			echo "VERSION:3.0\n";
			echo "N:" . $row['last_name'] . ";" . $row['first_name'] . ";;;\n";
			echo "FN:" . $row['first_name'] . " " . $row['last_name'] . "\n";
			echo "EMAIL;type=INTERNET;type=HOME:" . $row['email'] . "\n";
			echo "ADR;type=HOME:;;".$row['address_street'].";".$row['address_suburb'].";".$row['address_state'].";".$row['address_postcode'].";\n";
			echo "TEL;type=HOME:" . $family->getFormattedValue('home_tel') . "\n";
			echo "TEL;type=WORK:" . $dummy->getFormattedValue('work_tel'). "\n";
			echo "TEL;type=MOBILE:" . $dummy->getFormattedValue('mobile_tel') . "\n";
			echo "TEL;type=CELL:" . $dummy->getFormattedValue('mobile_tel') . "\n";
			if ($g = $dummy->getValue('Gender')) {
				echo "GENDER:".strtoupper($g[0])."\n";
			}
			echo "CATEGORIES:" . $row['congregation']."\n";
			echo "ORG:".$dummy->getFormattedValue('congregationid')."\n";
			echo "NOTES:".$dummy->getFormattedValue('congregationid')."\n";
			echo "ROLE:".$dummy->getFormattedValue('status')."\n";
			echo "TITLE:".$dummy->getFormattedValue('status')."\n";
			echo "SOURCE:".build_url(Array('personid' => Array($id)))."\n"; // where to get vcard from
			echo "URL:".build_url(Array('call' => NULL, 'view' => 'persons', 'personid' => $id))."\n"; // definitive location for full details
			echo "UID:".build_url(Array('call' => NULL, 'view' => 'persons', 'personid' => $id))."\n";
			echo "END:VCARD\n\n";

		}
	}
}