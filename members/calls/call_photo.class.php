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
		} else if (!empty($_REQUEST['familyid'])) {
			$obj = $GLOBALS['system']->getDBObject('family', (int)$_REQUEST['familyid']);

		}
		if (!$obj) return; // might be a person not visible in members inteface
		if (!empty($_REQUEST['personid'])) {
			$data = Photo_Handler::getPhotoData('person', (int)$_REQUEST['personid']);
			$fallback = 'unknown.jpg';
		} else if (!empty($_REQUEST['familyid'])) {
			$data = Photo_Handler::getPhotoData('family', (int)$_REQUEST['familyid']);
			$fallback = 'unknown_family.jpg';
		}
		header('Content-type: image/jpeg');
		if ($data) {
			echo $data;
			return;
		} else {
			readfile(JETHRO_ROOT.'/resources/img/'.$fallback);
		}
	}
}