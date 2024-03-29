<?php
include_once JETHRO_ROOT.'/include/db_object.class.php';
include_once JETHRO_ROOT.'/db_objects/person.class.php';
include_once JETHRO_ROOT.'/db_objects/family.class.php';
class Member extends DB_Object
{

	function toString()
	{
		return $this->values['first_name'].' '.$this->values['last_name'];
	}

	protected static function _getFields()
	{
		$memberFields = Array('first_name', 'last_name', 'gender', 'age_bracketid', 'congregationid', 'email', 'mobile_tel', 'work_tel', 'familyid', 'family_name', 'address_street', 'address_suburb', 'address_state', 'address_postcode', 'home_tel');
		
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

	static function getCongregations()
	{
		$SQL = 'SELECT c.id, c.name
				from congregation c
				join member m on m.congregationid = c.id
				group by c.id';
		$res = $GLOBALS['db']->queryAll($SQL, null, null, true, false);
		return $res;

	}

	static function getList($search=NULL, $congregationid=NULL)
	{
		$t = new Member();
		$order = 'family_name, member.familyid, ab.`rank` ASC, gender DESC';
		$conds = Array();
		if (!empty($search)) {
			$conds['first_name'] = $search.'%';
			$conds['last_name'] = $search.'%';
			$conds['family_name'] = $search.'%';
		}
		$query_bits = $t->getInstancesQueryComps($conds, 'OR', $order);
		if (!empty($congregationid)) {
			if (strlen(trim($query_bits['where']))) {
				$query_bits['where'] = "(\n".$query_bits['where'].")\n AND congregationid = ".(int)$congregationid;
			} else {
				$query_bits['where'] = "congregationid = ".(int)$congregationid;
			}
		}
		$query_bits['from'] .= "\n  LEFT JOIN family_photo fp ON fp.familyid = member.familyid";
		$query_bits['select'][] = 'IF(fp.familyid IS NULL, 0, 1) as has_family_photo';
		return $t->_getInstancesData($query_bits);
	}
	
	function getInstancesQueryComps($params, $logic, $order)
	{
		$res = parent::getInstancesQueryComps($params, $logic, $order);
		$res['select'][] = 'c.name as congregation, ab.label as age_bracket';
		$res['from'] = '('.$res['from'].')
						LEFT JOIN congregation c ON member.congregationid = c.id
						JOIN age_bracket ab on ab.id = member.age_bracketid ';
		return $res;
	}	

}