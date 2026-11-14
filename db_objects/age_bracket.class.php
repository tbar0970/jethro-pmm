<?php
class Age_Bracket extends DB_Object
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
			'is_adult' => Array(
									'type' => 'boolean',
									'note' => 'Should this age bracket be treated as a family contact etc?',
									'label' => 'Is adult?',
								),
			'is_default' => Array(
									'type' => 'boolean',
									'note' => 'Is this the default age bracket for new persons?',
									'label' => 'Is default?',
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
		$res = Array(parent::getInitSQL());
		$res[] = 'INSERT INTO age_bracket (`rank`, label, is_adult, is_default)
					VALUES
					(0, "Adult", 1, 1),
					(1, "High school", 0, 0),
					(2, "Primary school", 0, 0),
					(3, "Infants school", 0, 0),
					(4, "Preschool", 0, 0),
					(5, "Toddler", 0, 0),
					(6, "Baby", 0, 0)';
		return $res;
	}

	function toString()
	{
		return $this->values['label'];
	}

	static function getAdults()
	{
		$res = $GLOBALS['system']->getDBObjectData('age_bracket', Array('is_adult' => 1));
		return array_keys($res);
	}

	static function getMap()
	{
		static $res = NULL;
		if ($res === NULL) {
			$res = Array();
			$x = $GLOBALS['system']->getDBObjectData('age_bracket', Array(), 'OR', 'rank');
			foreach ($x as $i => $v) {
				$res[$i] = $v['label'];
			}
		}
		return $res;
	}

	public function getInstancesQueryComps($params, $logic, $order)
	{
		if (empty($order)) $order = 'rank';
		return parent::getInstancesQueryComps($params, $logic, $order);
	}

	/**
	 * Make the first age bracket (by rank) the default. Used when the submitted
	 * form marked no row as default — there must always be one for new persons.
	 *
	 * @return void
	 */
	public static function ensureDefault(): void
	{
		$GLOBALS['db']->query('UPDATE age_bracket SET is_default = 1 ORDER BY `rank` LIMIT 1');
	}

	/**
	 * Delete age brackets, repointing persons that use them to the default age
	 * bracket. Both statements run in one transaction.
	 *
	 * @param int[] $ids Age bracket ids to delete
	 * @return void
	 */
	public static function deleteAndReassign(array $ids): void
	{
		$keep = array_filter(array_map('intval', $ids));
		if (empty($keep)) return;
		$idSet = implode(',', $keep);
		$db = $GLOBALS['db'];
		$system = $GLOBALS['system'];
		$system->doTransaction('BEGIN');
		try {
			$db->query('UPDATE person
					SET age_bracketid = (SELECT id FROM age_bracket WHERE is_default = 1 AND id NOT IN ('.$idSet.'))
					WHERE age_bracketid IN ('.$idSet.')');
			$db->query('DELETE FROM age_bracket
					WHERE id IN ('.$idSet.')');
			$system->doTransaction('COMMIT');
		} catch (Throwable $e) {
			$system->doTransaction('ROLLBACK');
			throw $e;
		}
	}

}