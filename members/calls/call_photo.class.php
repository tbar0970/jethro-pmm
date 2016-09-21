<?php
/**
 * JETHRO PMM
 * 
 * 
 *
 * @author Tom Barrett <tom@tombarrett.id.au>
 * @version $Id: call_person_photo.class.php,v 1.2 2013/04/21 01:06:05 tbar0970 Exp $
 * @package jethro-pmm
 */
class Call_Photo extends Call
{
	/**
	 * Execute this call
	 *
	 * @return void
	 * @access public
	 */
	function run()
	{
		$db = $GLOBALS['db'];
		if (!empty($_REQUEST['personid'])) {
			$obj = $GLOBALS['system']->getDBObject('member', (int)$_REQUEST['personid']);
			$SQL = 'SELECT photodata FROM person_photo WHERE personid = '.(int)$obj->id;
		} else if (!empty($_REQUEST['familyid'])) {
			$obj = $GLOBALS['system']->getDBObject('family', (int)$_REQUEST['familyid']);
			// for single-member families, treat person photo as family photo
			$SQL = 'SELECT COALESCE(fp.photodata, IF(count(p.id) = 1, pp.photodata, NULL)) as photodata
					FROM family f
					LEFT JOIN family_photo fp ON fp.familyid = f.id
					LEFT JOIN member p ON p.familyid = f.id
					LEFT JOIN person_photo pp ON pp.personid = p.id
					WHERE f.id = '.(int)$obj->id.'
					GROUP BY f.id';

		}
		if ($obj) {
			$res = $db->queryRow($SQL);
			check_db_result($res);
			if ($res && $res['photodata']) {
				header('Content-type: image/jpeg');
				echo $res['photodata'];
				return;
			}
		}
		header('Content-type: image/gif');
		$placeholder = !empty($_REQUEST['personid']) ? 'unknown.gif' : 'unknown_family.gif';
		readfile(dirname(dirname(__FILE__)).'/resources/img/'.$placeholder);
	}
}
?>
