<?php
include_once JETHRO_ROOT.'/include/db_object.class.php';
include_once JETHRO_ROOT.'/db_objects/person.class.php';
include_once JETHRO_ROOT.'/db_objects/family.class.php';
class Member extends DB_Object
{


	function _getFields()
	{
		$memberFields = Array('first_name', 'last_name', 'gender', 'age_bracket', 'congregationid', 'email', 'mobile_tel', 'work_tel', 'familyid', 'family_name', 'address_street', 'address_suburb', 'address_state', 'address_postcode', 'home_tel');
		
		$p = new Person();
		foreach ($p->_getFields() as $k => $v) {
			if (in_array($k, $memberFields)) $res[$k] = $v;
		}
		$f = new Family();
		foreach ($f->_getFields() as $k => $v) {
			if (in_array($k, $memberFields)) $res[$k] = $v;
		}
		return $res;
	}


// TODO: this is likely to fail on an install because member will come before person etc
	function getInitSQL()
	{
		return Array(
			'CREATE VIEW member AS
SELECT mp.id, mp.first_name, mp.last_name, mp.gender, mp.age_bracket, mp.congregationid,
mp.email, mp.mobile_tel, mp.work_tel, mp.familyid,
mf.family_name, mf.address_street, mf.address_suburb, mf.address_state, mf.address_postcode, mf.home_tel
FROM _person mp
JOIN person_group_membership pgm1 ON pgm1.personid = mp.id
JOIN _person_group pg ON pg.id = pgm1.groupid AND pg.share_member_details = 1
JOIN person_group_membership pgm2 ON pgm2.groupid = pg.id
JOIN family mf ON mf.id = mp.familyid
WHERE pgm2.personid = getCurrentUserID()

UNION

SELECT mp.id, mp.first_name, mp.last_name, mp.gender, mp.age_bracket, mp.congregationid,
mp.email, mp.mobile_tel, mp.work_tel, mp.familyid,
mf.family_name, mf.address_street, mf.address_suburb, mf.address_state, mf.address_postcode, mf.home_tel
FROM _person mp
JOIN family mf ON mf.id = mp.familyid
JOIN _person self ON self.familyid = mp.familyid
WHERE self.id = getCurrentUserID()
;'
		);
	}
	


	

}
?>
