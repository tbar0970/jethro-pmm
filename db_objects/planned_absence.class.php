<?php
class Planned_Absence extends db_object
{
	protected $_load_permission_level = PERM_VIEWROSTER;
	protected $_save_permission_level = PERM_EDITROSTER;

	function __construct($id=0)
	{
		parent::__construct($id);

	}

	protected static function _getFields()
	{
		$fields = Array(
			'personid'	=> Array(
									'type'				=> 'reference',
									'references'		=> 'person',
									'label'				=> 'Person',
									'show_id'			=> FALSE,
									'allow_empty'		=> FALSE,
									'show_in_summary'	=> true,
									'editable'			=> false,
								   ),
			'start_date'		=> Array(
									'type'		=> 'date',
									'allow_empty'	=> FALSE,
								   ),
			'end_date'		=> Array(
									'type'		=> 'date',
									'allow_empty'	=> FALSE,
								   ),
			'comment'			=> Array(
									'type'		=> 'text',
									'width'		=> 80,
									'height'	=> 3,
									'note'		=> '',
									'add_links'	=> TRUE,
									),
			'created'			=> Array(
									'type'			=> 'timestamp',
									'readonly'		=> true,
									'show_in_summary'	=> true,
									'editable'			=> false,
									'label' => 'Created Date',
								   ),
			'creator'			=> Array(
									'type'			=> 'reference',
									'editable'		=> false,
									'references'	=> 'person',
									'show_in_summary'	=> true,
								   ),
		);
		return $fields;
	}

	/**
	 *
	 * @return Array (columnName => referenceExpression) eg 'tagid' => 'tagoption(id) ON DELETE CASCADE'
	 */
	public function getForeignKeys()
	{
		return Array(
				'planned_absence.personid' => '`_person`(`id`) ON DELETE CASCADE',
				'planned_absence.creator' => '`staff_member`(`id`) ON DELETE RESTRICT',
		);
	}
	
	public function validateFields()
	{
		if ($this->getValue('start_date') > $this->getValue('end_date')) {
			trigger_error("End date must be after start date");
			return FALSE;
		}
		return parent::validateFields();
	}
	
	function getInstancesQueryComps($params, $logic, $order)
	{
		if (empty($order)) $order = 'start_date';
		$res = parent::getInstancesQueryComps($params, $logic, $order);
		$res['from'] .= ' JOIN person creator ON planned_absence.creator = creator.id ';
		$res['select'][] = 'CONCAT(creator.first_name, " ", creator.last_name) as creator_name';
		return $res;
	}
	
	public function hasRosterAssignments()
	{
		$db = $GLOBALS['db'];
		$SQL = 'SELECT count(*) 
				FROM roster_role_assignment
				WHERE personid = '.(int)$this->getValue('personid').'
				AND assignment_date BETWEEN '.$db->quote($this->getValue('start_date')).' AND '.$db->quote($this->getValue('end_date'));
		$res = $db->queryOne($SQL);
		return ($res > 0);
	}
	
	public static function getForPersonsAndDate($personids, $date)
	{
		$db = $GLOBALS['db'];
		if (empty($personids)) return Array();
		foreach ($personids as $pid) $personidSet[] = (int)$pid;
		$personidSet = implode(',', $personidSet);
		$SQL = 'SELECT personid, comment FROM planned_absence
				WHERE personid IN ('.$personidSet.')
				AND '.$db->quote($date).' BETWEEN start_date AND end_date';
		$res = $db->queryAll($SQL, NULL, NULL, TRUE);
		return $res;
	}
	

}
