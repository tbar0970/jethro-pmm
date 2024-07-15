<?php
include_once 'include/db_object.class.php';
class Person_Status extends DB_Object
{
	function __construct($sid=-1)
	{
		if ($sid == -1) { // get top ranked default status
			$sid = Person_Status::getDefault();
		}
		return parent::__construct($sid);			
	}

	public static function getStatusLabel($sid) 
	{
		static $res = NULL;
		if ($res === NULL) {
			$db = JethroDB::get();
			$res = $db->queryAll('SELECT id, label FROM person_status WHERE `id` = ' . $sid . ' ORDER BY `rank`', NULL, NULL, true);
		}
		if (count($res) > 0) {
			return reset($res);	
		} else {
			return 'Unkown #' . $sid;
		}
	}

	public static function getArchivedIDs()
	{
		static $res = NULL;
		if ($res === NULL) {
			$db = JethroDB::get();
			$res = $db->queryAll('SELECT id FROM person_status WHERE (`active` = 1 AND `is_archived` = 1) ORDER BY `rank`');
		}
		static $result = array();
		foreach ($res as $item) {
			$result[] = $item['id'];
		}
		return $result;	
	}

	public static function getActive($unkownoption = false)
	{
		static $res = NULL;
		if ($res === NULL) {
			$db = JethroDB::get();
			$res = $db->queryAll('SELECT id, label, rank, active, is_default, is_archived, require_congregation FROM person_status WHERE `active` = 1 ORDER BY `rank`', NULL, NULL, true);
		}
		return $res;
	}

	public static function getDefault()
	{
		static $res = NULL;
		if ($res === NULL) {
			$db = JethroDB::get();
			$res = $db->queryAll('SELECT id  FROM person_status WHERE `active` = 1 AND `is_archived` = 0 AND `is_default` = 1 ORDER BY `rank`');
		}
		if ((count($res) > 0) && (count($res[0]) > 0)) {
			return reset($res[0]);
		}
		else return 0;
	}

	function getInstancesQueryComps($params, $logic, $order)
	{
		$res = parent::getInstancesQueryComps($params, $logic, $order);
		$res['select'][] = 'person_status.id, person_status.label, person_status.rank, person_status.active, person_status.is_default, person_status.is_archived, person_status.require_congregation';
		return $res;
	}

	function toString()
	{
		return $this->values['label'];
	}

	protected static function _getFields()
	{
		return Array(
			'label'	=> Array(
									'type'		=> 'text',
									'width'		=> 40,
									'maxlength'	=> 255,
									'allow_empty'	=> FALSE,
									'initial_cap'	=> TRUE,
									'label'			=> 'Status',
									'note'		=> '',
								   ),
			'rank'	 => Array(
									'type'		=> 'int',
									'default'	=> 1,
									'label'		=> 'Rank',
								),
			'active'	 => Array(
									'type'		=> 'select',
									'options'	=> Array('No', 'Yes'),
									'default'	=> 1,
									'label'		=> 'Active',
								),
			'is_default'	 => Array(
									'type'		=> 'select',
									'options'	=> Array('No', 'Yes'),
									'default'	=> 1,
									'label'		=> 'Default',
								),
			'is_archived'	 => Array(
									'type'		=> 'select',
									'options'	=> Array('No', 'Yes'),
									'default'	=> 1,
									'label'		=> 'Archived',
								),																
			'require_congregation'	 => Array(
									'type'		=> 'select',
									'options'	=> Array('No', 'Yes'),
									'default'	=> 1,
									'label'		=> 'Requires Congregation',
								),		
		);
	}

}

