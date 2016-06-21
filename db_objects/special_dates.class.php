<?php
include_once 'include/db_object.class.php';
class Special_Dates extends db_object
{
	// NB This class only exists for the following SQL
	// It has no ID

	static function getUpcomingDates($personid, $timeframe='4 weeks')
	{
		$end_date = date('Y-m-d', strtotime('+'.$timeframe));
		$current_year = date('Y');
		$next_year = intval($current_year) + 1;

		$sql = "select _person.id, CONCAT(first_name, ' ', last_name) as person_name,mobile_tel,email,status,value_date,
                            IF(INSTR(custom_field_value.value_date, '-') = 1,STR_TO_DATE(custom_field_value.value_date, '-%m-%d'), custom_field_value.value_date) as occassion_date, value_text,custom_field.name as occassion
                            FROM custom_field_value, custom_field,_person
                            WHERE personid=_person.id
                                AND custom_field_value.fieldid=custom_field.id
				AND value_date IS NOT NULL 
				AND status != 'archived' ";
		if (!empty($timeframe)) {
			$sql .= '
			HAVING (
                            CONCAT('.$GLOBALS['db']->quote($current_year).', "-", MONTH(occassion_date), "-", DAY(occassion_date)) BETWEEN DATE(NOW()) AND '.$GLOBALS['db']->quote($end_date) . '
                            OR
                            CONCAT('.$GLOBALS['db']->quote($next_year).', "-", MONTH(occassion_date), "-", DAY(occassion_date)) BETWEEN DATE(NOW()) AND '.$GLOBALS['db']->quote($end_date) . '
                            )';

		} else {
			$sql .= '
			AND (
                            CONCAT('.$GLOBALS['db']->quote($current_year).',"-", MONTH(occassion_date), "-", DAY(occassion_date)) >= DATE(NOW())
                            OR
                            CONCAT('.$GLOBALS['db']->quote($next_year).',"-", MONTH(occassion_date), "-", DAY(occassion_date)) >= DATE(NOW())
                            )';
		}

		$sql .= '
			ORDER BY MONTH(occassion_date) ASC, DAY(occassion_date) ASC';
		$res = $GLOBALS['db']->queryAll($sql, NULL, NULL, true, false, true);
		check_db_result($res);
		return $res;
	}

}
?>
