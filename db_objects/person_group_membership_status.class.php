<?php
/**
 * Group membership status option — one row per selectable status
 * ("Member", "Visitor", ...). Managed from the system configuration page.
 *
 * Table: `person_group_membership_status` (created by
 * db_objects/person_group.class.php::getInitSQL()).
 */
class Person_Group_Membership_Status extends DB_Object
{
	protected $_load_permission_level = 0;
	protected $_save_permission_level = 0;

	protected static function _getFields()
	{
		return Array(
			'label' => Array(
				'type' => 'text',
				'width' => 25,
				'allow_empty' => false,
			),
			'rank' => Array(
				'type' => 'int',
			),
			'is_default' => Array(
				'type' => 'boolean',
				'note' => 'Is this the default status for new group members?',
				'label' => 'Is default?',
			),
		);
	}

	protected function _getUniqueKeys()
	{
		return Array('label' => Array('label'));
	}

	public function toString()
	{
		return (string)$this->getValue('label');
	}

	/**
	 * The table is created by Person_Group::getInitSQL(), together with its seed
	 * row and the FK from person_group_membership. The installer runs
	 * getInitSQL() for every DB_Object class, so this class must not emit DDL of
	 * its own or a fresh install would try to create the table twice.
	 *
	 * @return null
	 */
	public function getInitSQL($table_name=NULL)
	{
		return NULL;
	}

	/**
	 * Make the first status (by label) the default. Used when the submitted form
	 * marked no row as default — there must always be one for new memberships.
	 *
	 * @return void
	 */
	public static function ensureDefault(): void
	{
		$GLOBALS['db']->query('UPDATE person_group_membership_status SET is_default = 1 ORDER BY label LIMIT 1');
	}

	/**
	 * Delete statuses, repointing memberships that use them to the default status
	 * (the FK is ON DELETE RESTRICT). Both statements run in one transaction.
	 *
	 * @param int[] $ids Status ids to delete
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
			$db->query('UPDATE person_group_membership
					SET membership_status = (SELECT id FROM person_group_membership_status WHERE is_default = 1 AND id NOT IN ('.$idSet.'))
					WHERE membership_status IN ('.$idSet.')');
			$db->query('DELETE FROM person_group_membership_status
					WHERE id IN ('.$idSet.')');
			$system->doTransaction('COMMIT');
		} catch (Throwable $e) {
			$system->doTransaction('ROLLBACK');
			throw $e;
		}
	}
}
