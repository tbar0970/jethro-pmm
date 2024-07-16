<?php
class Person_Status extends db_object
{
	protected $_load_permission_level = 0;
	protected $_save_permission_level = 0;

	protected static function _getFields()
	{

		$fields = Array(
			'label'		=> Array(
									'type'		=> 'text',
									'width'		=> 25,
								   ),
			'rank'		=> Array(
									'type'		=> 'int',
								   ),
			'active' => Array(
									'type' => 'boolean',
									'note' => 'Is this status currently in use?',
									'label' => 'Is active?',
								),
			'is_default' => Array(
									'type' => 'boolean',
									'note' => 'Is this the default status for new persons?',
									'label' => 'Is default?',
								),
			'is_archived' => Array(
									'type' => 'boolean',
									'note' => 'Should persons with this status be considered archived?',
									'label' => 'Is archived?',
								),
			'require_congregation' => Array(
									'type' => 'boolean',
									'note' => 'Must persons with this status be in a congregation?',
									'label' => 'Require congregation?',
								),

		);
		return $fields;
	}

	protected function _getUniqueKeys()
	{
		return Array('label' => Array('label'));
	}

	public function getInitSQL($tableName=FALSE)
	{
		$res = Array();
		$res[] =
			'CREATE TABLE `person_status` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `label` varchar(255) NOT NULL,
			  `rank` int(11) NOT NULL DEFAULT 0,
			  `active` tinyint(1) unsigned DEFAULT 1,
			  `is_default` tinyint(1) unsigned DEFAULT 0,
			  `is_archived` tinyint(1) unsigned DEFAULT 0,
			  `require_congregation` tinyint(1) unsigned DEFAULT 1,
			  PRIMARY KEY (`id`),
			  UNIQUE KEY `label` (`label`)
			) ENGINE=InnoDB;';
		$res[] = '
			INSERT INTO person_status (`rank`, label, is_default, is_archived, require_congregation)
					VALUES
					(0, "Core", 0, 0, 1),
					(1, "Crowd", 0, 0, 1),
					(2, "Contact", 1, 0, 0),
					(3, "Archived, 0, 1, 0)';
		return $res;
	}

	function toString()
	{
		return $this->values['label'];
	}

	/**
	 * Get details of all statuses that are currently in use
	 * @param bool $include_archived Whether to include statuses that denote archived persons.
	 * @return array
	 */
	static function getActive($include_archived=TRUE)
	{
		$params = Array('active' => 1);
		if (!$include_archived) $params['is_archived'] = 0;
		// The system controller caches this result
		return $GLOBALS['system']->getDBObjectData('person_status', $params);
	}

	static function getArchivedIDs()
	{
		// The system controller caches this result
		$res = $GLOBALS['system']->getDBObjectData('person_status', Array('is_archived' => 1));
		return array_keys($res);

	}


	public function getInstancesQueryComps($params, $logic, $order)
	{
		if (empty($order)) $order = 'rank';
		return parent::getInstancesQueryComps($params, $logic, $order);
	}

}