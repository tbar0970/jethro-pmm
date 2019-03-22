<?php
include_once 'include/db_object.class.php';
class Congregation extends db_object
{
	protected static function _getFields()
	{
		return Array(
			'long_name'	=> Array(
									'type'		=> 'text',
									'width'		=> 40,
									'maxlength'	=> 128,
									'allow_empty'	=> FALSE,
									'initial_cap'	=> TRUE,
									'label'			=> 'Long Name',
									'note'		=> 'Used on printed material',
								   ),
			'name'		=> Array(
									'type'		=> 'text',
									'width'		=> 40,
									'maxlength'	=> 128,
									'allow_empty'	=> FALSE,
									'initial_cap'	=> TRUE,
									'label'			=> 'Short Name',
									'note'			=> 'For general use within Jethro',
								   ),
			'meeting_time'	=> Array(
									'type'		=> 'text',
									'width'		=> 10,
									'maxlength'	=> 255,
									'label'		=> 'Code Name',
									'regex'		=> '/^[^0-9]*[0-2]\d[0-5]\d[^0-9]*$/',
									'note'		=> 'Used for filenames and sorting.  Fill this field in to enable service planning and rosters for this congregation.  An HHMM time must be present within the value (eg ash_0930_late).',
								   ),
			'attendance_recording_days'	=> Array(
									'type'		=> 'bitmask',
									'options'	=> Array(
													1	=> 'Sunday',
													2	=> 'Monday',
													4	=> 'Tuesday',
													8	=> 'Wednesday',
													16	=> 'Thursday',
													32	=> 'Friday',
													64	=> 'Saturday',
									),
									'default'	=> 127,
									'label'		=> 'Attendance Recording Days',
									'cols'		=> 2,
									'note'		=> 'Jethro will only allow you to record attendance for this congregation on the selected days.  Select nothing if you do not plan to record attendance for this congregation.',
									'show_unselected' => FALSE,
						   ),
			'print_quantity' => Array(
									'type'		=> 'int',
									'hidden'	=> true,
									'editable' => false,
								   ),
		);
	}


	public function toString()
	{
		return $this->values['name'];
	}

	public function getInstancesQueryComps($params, $logic, $order)
	{
		$res = parent::getInstancesQueryComps($params, $logic, $order);
		$res['select'][] = 'COUNT(p.id) AS member_count';
		$res['from'] .= ' LEFT JOIN person p ON p.status <> "archived" AND p.congregationid = congregation.id ';
		$res['group_by'] = 'congregation.id';
		$restrictions = Array();
		if (!empty($GLOBALS['user_system'])) {
			$restrictions = $GLOBALS['user_system']->getCurrentRestrictions();
		}
		if (!empty($restrictions['congregation'])) {
			$oldwhere = $res['where'];
			$res['where'] = 'congregation.id IN ('.implode(',', array_map(Array($GLOBALS['db'], 'quote'), $restrictions['congregation'])).')
							';
			if ($oldwhere) $res['where'] .= ' AND ('.$oldwhere.')';
		}
		return $res;

	}

	public static function findByName($name) {
		$name = strtolower($name);
		static $congs = Array();
		if (!isset($congs[$name])) {
			$matches = $GLOBALS['system']->getDBObjectData('congregation', Array('name' => $name, 'long_name' => $name), 'OR');
			if (count($matches) == 1) {
				$congs[$name] = key($matches);
			}
		}
		if (!isset($congs[$name])) trigger_error('Could not find a unique congregation with name "'.$name.'"');
		return array_get($congs, $name);
	}

	public function canRecordAttendanceOn($date)
	{
		$testIndex = array_search(date('l', strtotime($date)), $this->fields['attendance_recording_days']['options']);
		return $testIndex & $this->getValue('attendance_recording_days');
	}

}
?>
