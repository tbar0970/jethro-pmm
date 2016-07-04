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
			$obj = $GLOBALS['system']->getDBObject('person', (int)$_REQUEST['personid']);
			$table = 'person_photo';
			$column = 'personid';
		} else if (!empty($_REQUEST['familyid'])) {
			$obj = $GLOBALS['system']->getDBObject('family', (int)$_REQUEST['familyid']);
			$table = 'family_photo';
			$column = 'familyid';
		}
		if ($obj) {
			$sql = 'SELECT * FROM '.$table.' WHERE '.$column.' = '.(int)$obj->id;
			$res = $db->queryRow($sql);
			check_db_result($res);
			if ($res) {
				header('Content-type: image/jpeg'); // FIXME
				echo $res['photodata'];
				return;
			}
		} 
		header('Content-type: image/gif');
		readfile(dirname(dirname(__FILE__)).'/resources/img/unknown.gif');
	}
}
?>
