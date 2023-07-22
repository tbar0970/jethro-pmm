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
			'holds_persons'	 => Array(
									'type'		=> 'select',
									'options'	=> Array('No', 'Yes'),
									'default'	=> 1,
									'label'		=> 'Features',
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
									'note'		=> 'Jethro will only allow you to record attendance on the selected days.  ',
									'show_unselected' => FALSE,
						   ),
			'meeting_time'	=> Array(
									'type'		=> 'text',
									'width'		=> 10,
									'maxlength'	=> 255,
									'label'		=> 'Time code',
									'regex'		=> '/^[^0-9]*[0-2]\d[0-5]\d[^0-9]*$/',
									'note'		=> 'Enter the starting time, in HHMM format, with optional prefix/suffix (eg ash_0930_late). Also used for filenames and sorting.',
								   ),
		);
	}

	public function printFieldInterface($name, $prefix='')
	{
		switch ($name) {
			case 'holds_persons':
				$params = Array('type' => 'checkbox', 'attrs' => Array('data-toggle' => 'visible'));
				?>
				<label class="checkbox">
					<?php
					print_widget('holds_persons', $params, $this->getValue('holds_persons'));
					?>
					Persons can be added to this congregation
				</label>
				<label class="checkbox">
					<?php 
					$params['attrs']['data-target'] = '#field-attendance_recording_days';
					print_widget('holds_attendance', $params, empty($this->id) || $this->getValue('attendance_recording_days') > 0); ?>
					Attendance can be recorded for this congregation
				</label>
				<label class="checkbox">
					<?php
					$params['attrs']['data-target'] = '#field-meeting_time';
					print_widget('holds_services', $params, empty($this->id) || strlen($this->getValue('meeting_time')) > 0);
					?>
					This congregation has services/rosters
				</label>
				<?php
				break;
			default:
				return parent::printFieldInterface($name, $prefix);
		}
	}

	public function processFieldInterface($name, $prefix = '')
	{
		switch ($name) {
			case 'holds_persons':
				$this->setValue('holds_persons', array_get($_POST, 'holds_persons', 0));
				break;
			default:
				parent::processFieldInterface($name, $prefix);
		}
	}

	public function isActive()
	{
		return ($this->getValue('holds_persons') > 0)
				|| ($this->getValue('meeting_time') != '')
				|| ($this->getValue('attendance_recording_days') > 0);
	}


	public function toString()
	{
		return $this->values['name'];
	}

	public function getInstancesQueryComps($params, $logic, $order)
	{
		$res = parent::getInstancesQueryComps($params, $logic, $order);
		$res['select'][] = 'COUNT(p.id) AS member_count';
		$res['from'] .= ' LEFT JOIN person p ON p.congregationid = congregation.id ';
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

	public function canDelete($trigger_messages=FALSE)
	{
		$members = $GLOBALS['system']->getDBObjectData('person', Array('congregationid' => $this->id));
		if (count($members)) {
			if ($trigger_messages) add_message(_("Cannot delete congregation because it is not empty"), "error");
			return FALSE;
		}
		
		$usernames = Staff_Member::getUsernamesByCongregationRestriction($this->id);
		if (count($usernames)) {
			if ($trigger_messages) add_message(_("Cannot delete congregation because there are user accounts restricted to it: ").implode(',', $usernames), 'error');
			return FALSE;
		}

		$services = $GLOBALS['system']->getDBObjectData('service', Array('congregationid' => $this->id));
		if ($services) {
			if ($trigger_messages) add_message("This congregation cannot be deleted because there are historical services that belong to it", 'error');
			return FALSE;
		}
		return TRUE;
	}

}