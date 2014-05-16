<?php
include_once 'include/db_object.class.php';
class Congregation extends db_object
{
	function _getFields()
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
									'note'		=> 'Used for filenames and sorting - must be filled in to use services and rosters for this congregation',
								   ),
			'print_quantity' => Array(
									'type'		=> 'int',
									'hidden'	=> true
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
		$restrictions = $GLOBALS['user_system']->getCurrentRestrictions();
		if (!empty($restrictions['congregation'])) {
			$oldwhere = $res['where'];
			$res['where'] = 'congregation.id IN ('.implode(',', array_map(Array($GLOBALS['db'], 'quote'), $restrictions['congregation'])).')
							';
			if ($oldwhere) $res['where'] .= ' AND ('.$res['where'].')';
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
		if (!isset($congs[$name])) trigger_error('No congregation with name "'.$name.'"');
		return array_get($congs, $name);
	}



}
?>
