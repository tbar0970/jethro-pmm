<?php
Class Photo_Handler {

	const MAX_PHOTO_WIDTH = 200;
	const MAX_PHOTO_HEIGHT = 200;
	
	public static function getUploadedPhotoData($fieldName)
	{
		if (!empty($_FILES[$fieldName]) && !empty($_FILES[$fieldName]['name'])) {
			if (!empty($_FILES[$fieldName]['error'])) {
				$err = $_FILES[$fieldName]['error'];
				if (in_array($err, Array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE))) {
					add_message("Your photo could not be saved because the file is too big. Please try a smaller image.", 'error');
					return NULL;
				} else {
					trigger_error("Technical error uploading photo file: Error #".$err, E_USER_ERROR);
				}
			}

			if (!in_array($_FILES[$fieldName]['type'], Array('image/jpeg', 'image/gif', 'image/png', 'image/jpg'))) {
				add_message("The uploaded photo was not of a permitted type and has not been saved.  Photos must be JPEG, GIF or PNG", 'error');
				return NULL;
			} else if (!is_uploaded_file($_FILES[$fieldName]['tmp_name'])) {
				trigger_error("Security error with file upload", E_USER_ERROR);
				return NULL;
			} else {
				$bits = explode('.', $_FILES[$fieldName]['name']);
				$ext = strtolower(end($bits));
				if ($ext == 'jpg') $ext = 'jpeg';
				if (!in_array($ext, Array('jpeg', 'gif', 'png'))) {
					add_message("The uploaded photo was not of a permitted type and has not been saved.  Photos must be JPEG, GIF or PNG", 'error');
					return NULL;
				}
				if (function_exists('imagepng')) {
					$fn = 'imagecreatefrom'.$ext;
					list($orig_width, $orig_height) = getimagesize($_FILES[$fieldName]['tmp_name']);
					$input_img = $fn($_FILES[$fieldName]['tmp_name']);
					if (!$input_img) exit;
					$orig_ratio = $orig_width / $orig_height;
					if (($orig_width > self::MAX_PHOTO_WIDTH) || ($orig_height > self::MAX_PHOTO_HEIGHT)) {
						if (self::MAX_PHOTO_WIDTH > self::MAX_PHOTO_HEIGHT) {
							// resize to fit width then crop to fit height
							$new_width = self::MAX_PHOTO_WIDTH;
							$new_height = min(self::MAX_PHOTO_HEIGHT, $new_width / $orig_ratio);
							$src_x = 0;
							$src_w = $orig_width;
							$src_h = $new_height * ($orig_width / $new_width);
							$src_y = (int)max(0, ($orig_height - $src_h) / 2);
						} else {
							// resize to fit height then crop to fit width
							$new_height = self::MAX_PHOTO_HEIGHT;
							$new_width = min(self::MAX_PHOTO_WIDTH, $new_height * $orig_ratio);
							$src_y = 0;
							$src_h = $orig_height;
							$src_w = $new_width * ($orig_height / $new_height);
							$src_x = (int)max(0, ($orig_width - $src_w) / 2);
						}
						$output_img = imagecreatetruecolor($new_width, $new_height);
						imagecopyresampled($output_img, $input_img, 0, 0, $src_x, $src_y, $new_width, $new_height, $src_w, $src_h);
						imagedestroy($input_img);
					} else {
						$output_img = $input_img;
					}
					imagejpeg($output_img, $_FILES[$fieldName]['tmp_name'], 90);
				}
				$res = file_get_contents($_FILES[$fieldName]['tmp_name']);
				unlink($_FILES[$fieldName]['tmp_name']);
				return $res;
			}
		}
	}

	public static function getDataURL($type, $id)
	{
		if ($res = self::getPhotoData($type, $id)) {
			return 'data:image/jpg;base64,'.base64_encode($res);
		} else {
			return 'data:image/gif;base64,'.base64_encode(file_get_contents(JETHRO_ROOT.'/resources/img/unknown_family.gif'));
		}
	}

	public static function getPhotoData($type, $id)
	{
		$db = $GLOBALS['db'];
		$SQL = $obj = NULL;
		if ($type == 'person') {
			$obj = $GLOBALS['system']->getDBObject('person', (int)$id);
			if ($obj) $SQL = 'SELECT photodata FROM person_photo WHERE personid = '.$obj->id;
		} else if ($type == 'family') {
			$obj = $GLOBALS['system']->getDBObject('family', (int)$id);
			if ($obj) {
				// for single-member families, treat person photo as family photo
				$SQL = 'SELECT COALESCE(fp.photodata, IF(count(p.id) = 1, pp.photodata, NULL)) as photodata
						FROM family f
						LEFT JOIN family_photo fp ON fp.familyid = f.id
						LEFT JOIN person p ON p.familyid = f.id
						LEFT JOIN person_photo pp ON pp.personid = p.id
						WHERE f.id = '.(int)$obj->id.'
						GROUP BY f.id';
			}

		}
		if ($obj) {
			$res = $GLOBALS['db']->queryOne($SQL);
			if ($res) {
				return $res;
			}
		}
	}

}