<?php

class Call_Downsize_Photos extends Call
{
	const MAX_PHOTO_WIDTH = 500;
	const MAX_PHOTO_HEIGHT = 500;

	function msg(string $msg) {
		echo $msg.PHP_EOL;
	}

	function run()
	{
		header('Content-type: text/html');
		echo "<html><textarea cols='120' rows='60'>";
		$db =& $GLOBALS['db'];
		$res = $db->queryCol("select id from family where exists (select * from family_photo where familyid=family.id);");
		$this->msg("Processing ".count($res). " families with photos");
		foreach ($res as $id) {
//			$this->msg("Considering family ".$id);
//			$id = (int)$_REQUEST['personid'];
//			$data = Photo_Handler::getPhotoData('person', (int)$_REQUEST['personid']);
//			$id = (int)$_REQUEST['familyid'];
			$data = $this->getAllPhotoData('family', $id);
			$newdata = $this->resize($data, 'family', $id);
			if ($newdata) {
				$SQL = 'REPLACE INTO family_photo (familyid, photodata) VALUES ('.$id.', '.$db->quote($newdata).')';
				$res = $db->query($SQL);
				$data = Photo_Handler::getPhotoData('family', $id);
			} else {
//				error_log("      No resize needed for family image ".$id."      ");
			}
		}

		$data = null;

		$res = $db->queryCol("select id from _person where exists (select * from person_photo where personid=_person.id);");
		$this->msg("Processing ".count($res). " persons with photos");
		foreach ($res as $id) {
//			$this->msg("Considering family ".$id);
//			$id = (int)$_REQUEST['personid'];
//			$data = Photo_Handler::getPhotoData('person', (int)$_REQUEST['personid']);
//			$id = (int)$_REQUEST['familyid'];
			$data = $this->getAllPhotoData('person', $id);
			$newdata = $this->resize($data, 'person', $id);
			if ($newdata) {
				$SQL = 'REPLACE INTO person_photo (personid, photodata) VALUES ('.$id.', '.$db->quote($newdata).')';
				$res = $db->query($SQL);
				$data = Photo_Handler::getPhotoData('person', $id);
			} else {
//				error_log("      No resize needed for person image ".$id."      ");
			}
		}
		echo("</textarea></html>");
	}

	public function getAllPhotoData($type, $id)
	{
		$db = $GLOBALS['db'];
		$SQL = $obj = NULL;
		if ($type == 'person') {
			$obj = $GLOBALS['system']->getDBObject('person', (int)$id);
			if ($obj) {
				$SQL = 'SELECT pp.photodata FROM _person p JOIN person_photo pp ON pp.personid = p.id WHERE p.id='.(int)$obj->id;
			}
		} else if ($type == 'family') {
			$obj = $GLOBALS['system']->getDBObject('family', (int)$id);
			if ($obj) {
				$SQL = 'SELECT fp.photodata FROM family_photo fp JOIN family f ON f.id = fp.familyid WHERE f.id='.(int)$obj->id;
			}
		}
		if ($obj) {
			$res = $GLOBALS['db']->queryOne($SQL);
			if ($res) {
				return $res;
			}
		}
	}

	function resize($olddata, string $type, int $id)
	{
		if (!$olddata) {
			throw new Exception("nul");
		}
		$input_img = imagecreatefromstring($olddata);
		$orig_width = imagesx($input_img);
		$orig_height = imagesy($input_img);
		if ($orig_width <= self::MAX_PHOTO_WIDTH) {
			$this->msg("No change: ".$type." ".$id);
			return null;
		}
		$orig_ratio = $orig_width / $orig_height;

		// Just resize, no cropping
		$new_width = self::MAX_PHOTO_WIDTH;
		$new_height = $new_width / $orig_ratio;
		$src_x = $src_y = 0;
		$src_w = $orig_width;
		$src_h = $orig_height;

		$output_img = imagecreatetruecolor($new_width, $new_height);
		imagecopyresampled($output_img, $input_img, 0, 0, $src_x, $src_y, $new_width, $new_height, $src_w, $src_h);
		imagedestroy($input_img);
		ob_start();
		imagejpeg($output_img, null, 90);
		$newdata = ob_get_contents();
		ob_end_clean();
		$this->msg("Resizing ".$type." ".$id." from ".$orig_width."x".$orig_height);
		return $newdata;
	}
}