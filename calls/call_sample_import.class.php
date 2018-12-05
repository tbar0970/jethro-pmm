<?php
class Call_Sample_Import extends Call
{
	function run()
	{
		$fp = fopen('php://output', 'w');
		header('Content-type: application/force-download');
		header("Content-Type: application/download");
		header('Content-type: text/csv');
		header('Content-Disposition: attachment; filename="jethro_sample_import.csv"');
		$GLOBALS['system']->includeDBClass('family');
		$GLOBALS['system']->includeDBClass('person');

		$header = View_Admin__Import::getSampleHeader();
		fputcsv($fp, $header);

		$congs = $GLOBALS['system']->getDBObjectData('congregation');
		foreach ($congs as $id => $detail) {
			$congs[$id] = $detail['name'];
		}
		$statuses = Person::getStatusOptions();
		array_pop($statuses); // remove archived
		$age_brackets = $GLOBALS['system']->getDBObjectData('age_bracket', Array(), 'OR', 'rank');
		$default_age_bracket = '';
		$child_age_bracket = '';
		foreach ($age_brackets as $id => $details) {
			if ($details['is_default']) $default_age_bracket = $details['label'];
			if (!$details['is_adult']) $child_age_bracket = $details['label'];
		}
		$mobile_length = @reset(get_valid_phone_number_lengths(MOBILE_TEL_FORMATS));
		$home_length = @reset(get_valid_phone_number_lengths(HOME_TEL_FORMATS));
		$work_length = @reset(get_valid_phone_number_lengths(WORK_TEL_FORMATS));


		$data = Array(
			Array(
				'family_name' => 'Luther',
				'last_name' => 'Luther',
				'first_name' => 'Martin',
				'congregation' => reset($congs),
				'status' => reset($statuses),
				'gender' => 'male',
				'age_bracket' => $default_age_bracket,
				'email' => 'mluther@wittenberg.edu.de',
				'mobile_tel' => '04'.str_repeat(rand(0,9), $mobile_length-2),
				'work_tel' => str_repeat(rand(1,9),$work_length),
				'home_tel' => str_repeat(rand(1,9),$home_length),
				'address_street' => 'Collegienstraße 54',
				'address_suburb' => 'Wittenberg',
				'address_state' => ifdef('ADDRESS_STATE_DEFAULT', ''),
				'address_postcode' => '1234',
				'note' => 'Founding member',
				'groups' => Array('Preaching volunteers', 'Gutter cleaning roster', 'Beer Appreciation Group'),
			),
			Array(
				'family_name' => 'Luther',
				'last_name' => 'von Bora',
				'first_name' => 'Katherine',
				'congregation' => reset($congs),
				'status' => reset($statuses),
				'gender' => 'female',
				'age_bracket' => $default_age_bracket,
				'email' => 'katievb1517@gmail.com',
				'mobile_tel' => '04'.str_repeat(rand(0,9), $mobile_length-2),
				'work_tel' => '',
				'home_tel' => '',
				'address_street' => '',
				'address_suburb' => '',
				'address_state' => '',
				'address_postcode' => '',
				'note' => 'NB ex-nun',
				'groups' => Array('Hospitality team', 'Womens Guild')
			),
			Array(
				'family_name' => 'Luther',
				'last_name' => 'Luther',
				'first_name' => 'Hans',
				'congregation' => reset($congs),
				'status' => reset($statuses),
				'gender' => 'male',
				'age_bracket' => $child_age_bracket,
				'email' => '',
				'mobile_tel' => '',
				'work_tel' => '',
				'home_tel' => '',
				'address_street' => '',
				'address_suburb' => '',
				'address_state' => '',
				'address_postcode' => '',
				'note' => '',
			),
			Array(
				'family_name' => 'Luther',
				'last_name' => 'Luther',
				'first_name' => 'Elizabeth',
				'congregation' => reset($congs),
				'status' => reset($statuses),
				'gender' => 'female',
				'age_bracket' => $child_age_bracket,
				'email' => '',
				'mobile_tel' => '',
				'work_tel' => '',
				'home_tel' => '',
				'address_street' => '',
				'address_suburb' => '',
				'address_state' => '',
				'address_postcode' => '',
				'note' => '',
			),
			Array(
				'family_name' => 'Calvin',
				'last_name' => 'Calvin',
				'first_name' => 'John',
				'congregation' => end($congs),
				'status' => end($statuses),
				'gender' => 'male',
				'age_bracket' => $default_age_bracket,
				'email' => 'jcalvin@geneva.gov.ch',
				'mobile_tel' => '04'.str_repeat(rand(0,9), $mobile_length-2),
				'work_tel' => str_repeat(rand(0,9),$work_length),
				'home_tel' => str_repeat(rand(0,9),$home_length),
				'address_street' => 'Rue des Chanoines 1',
				'address_suburb' => 'Geneva',
				'address_state' => ifdef('ADDRESS_STATE_DEFAULT', ''),
				'address_postcode' => '1234',
				'note' => 'Not as reformed as you think',
			),
			Array(
				'family_name' => 'Calvin',
				'last_name' => 'de Bure',
				'first_name' => 'Idelette',
				'congregation' => end($congs),
				'status' => end($statuses),
				'gender' => 'female',
				'age_bracket' => $default_age_bracket,
				'email' => 'idelette1540@hotmail.com',
				'mobile_tel' => '04'.str_repeat(rand(0,9), $mobile_length-2),
				'work_tel' => '',
				'home_tel' => '',
				'address_street' => '',
				'address_suburb' => '',
				'address_state' => '',
				'address_postcode' => '',
				'note' => 'Kids from previous marriage',
			)
		);
		foreach ($custom_fields as $field) {
			switch ($field['type']) {
				case 'select':
					foreach ($data as $k => &$row) {
						$row[$field['name']] = $field['options'][array_rand($field['options'])];
					}
					break;
				case 'date':
					foreach ($data as $k => &$row) {
						$row[$field['name']] = date('Y-m-d', strtotime('-'.rand(1,100).' month'));
					}
					break;
				default:
					foreach ($data as $k => &$row) {
						$row[$field['name']] = '';
					}
			}
		}
		unset($row);
		foreach ($data as $row) {
			$out = Array();
			foreach ($map as $key => $i) {
				$out[] = array_get($row, $key, '');
			}
			foreach (Array_get($row, 'groups', Array()) as $g) {
				$out[] = $g;
			}
			fputcsv($fp, $out);
		}




	}
}


?>
