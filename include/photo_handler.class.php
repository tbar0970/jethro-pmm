<?php
Class Photo_Handler {

	const MAX_PHOTO_WIDTH = 500;
	const MAX_PHOTO_HEIGHT = 500;

	const CROP_WIDTH = 1;
	const CROP_HEIGHT = 2;
	const CROP_NONE = 3;

	public static function getUploadedPhotoData($fieldName, $crop=NULL)
	{
		if ($crop === NULL) $crop = self::CROP_WIDTH;
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
					$input_img = $fn($_FILES[$fieldName]['tmp_name']);
					if (!$input_img) exit;
					// Rotate the image as necessary - thanks http://php.net/manual/en/function.exif-read-data.php#110894
					$exif = @exif_read_data($_FILES[$fieldName]['tmp_name']);
					if (!empty($exif['Orientation'])) {
						switch($exif['Orientation']) {
							case 8:
								$input_img = imagerotate($input_img,90,0);
								break;
							case 3:
								$input_img = imagerotate($input_img,180,0);
								break;
							case 6:
								$input_img = imagerotate($input_img,-90,0);
								break;
						}
					}
					$orig_width = imagesx($input_img);
					$orig_height = imagesy($input_img);

					$orig_ratio = $orig_width / $orig_height;
					if (($orig_width > self::MAX_PHOTO_WIDTH) || ($orig_height > self::MAX_PHOTO_HEIGHT)) {
						if ($crop == self::CROP_HEIGHT) {
							// resize to fit width then crop to fit height
							$new_width = self::MAX_PHOTO_WIDTH;
							$new_height = min(self::MAX_PHOTO_HEIGHT, $new_width / $orig_ratio);
							$src_x = 0;
							$src_w = $orig_width;
							$src_h = $new_height * ($orig_width / $new_width);
							$src_y = (int)max(0, ($orig_height - $src_h) / 2);
						} else if ($crop == self::CROP_WIDTH) {
							// resize to fit height then crop to fit width
							$new_height = self::MAX_PHOTO_HEIGHT;
							$new_width = min(self::MAX_PHOTO_WIDTH, $new_height * $orig_ratio);
							$src_y = 0;
							$src_h = $orig_height;
							$src_w = $new_width * ($orig_height / $new_height);
							$src_x = (int)max(0, ($orig_width - $src_w) / 2);
						} else {
							// Just resize, no cropping
							$new_width = self::MAX_PHOTO_WIDTH;
							$new_height = $new_width / $orig_ratio;
							$src_x = $src_y = 0;
							$src_w = $orig_width;
							$src_h = $orig_height;
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
			if ($obj) {
				// for single-member families, show family photo as individual photo if individual photo is missing
				$SQL = 'SELECT COALESCE(pp.photodata, IF(count(member.id) = 1, fp.photodata, NULL)) as photodata
						FROM person p
						JOIN family f ON p.familyid = f.id
						JOIN person member ON member.familyid = f.id AND member.status <> "archived"
						LEFT JOIN person_photo pp ON pp.personid = p.id
						LEFT JOIN family_photo fp ON fp.familyid = f.id
						WHERE p.id = '.(int)$obj->id.'
						GROUP BY p.id';
			}
		} else if ($type == 'family') {
			$obj = $GLOBALS['system']->getDBObject('family', (int)$id);
			if ($obj) {
				// for single-member families, treat person photo as family photo if family photo is missing
				$SQL = 'SELECT COALESCE(fp.photodata, IF(count(p.id) = 1, pp.photodata, NULL)) as photodata
						FROM family f
						LEFT JOIN family_photo fp ON fp.familyid = f.id
						LEFT JOIN person p ON p.familyid = f.id AND p.status <> "archived"
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