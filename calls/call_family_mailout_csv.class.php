<?php
class Call_Family_Mailout_CSV extends Call
{
        function run()
        {
		header('Content-type: text/plain');
		$group = $GLOBALS['system']->getDBObject('person_group', (int)$_REQUEST['groupid']);
		header('Content-disposition: attachment; filename="'.str_replace('"', '\\"', $group->getValue('name')).'.csv"');
                if (!empty($_REQUEST['groupid'])) {
			$families = $GLOBALS['system']->getDBObjectData('family', Array('(family.id' => 'SELECT familyid FROM person JOIN person_group_membership pgm ON person.id = pgm.personid WHERE pgm.groupid = '.(int)$_REQUEST['groupid'], '!status' => 'archived'), 'AND', 'address_street');
			echo '"id",';
			foreach (reset($families) as $key => $val) {
				echo '"'.str_replace('"', '\\"', $key).'",';
			}
			echo '"family_title",';
			echo "\r\n";
			foreach ($families as $id => $details) {
				if (empty($details['address_street'])) continue;
				$member_bits = explode(',', $details['members']);
				if (count($member_bits) > 1) {
					$last_member = array_pop($member_bits);
					$details['members'] = implode(',', $member_bits).' &'.$last_member;
					$details['family_title'] = $details['family_name'].' Family';
				} else {
					$details['family_title'] = $details['members'].' '.$details['family_name'];
				}
				echo '"'.$id.'",';
				foreach ($details as $d) {
					echo '"'.str_replace('"', '\\"', $d).'",';
				}
				echo "\r\n";
			}	
		}
        }
}

