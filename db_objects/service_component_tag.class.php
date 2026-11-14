<?php
class Service_Component_Tag extends DB_Object
{
	protected $_load_permission_level = 0; // want PERM_VIEWSERVICE | PERM_VIEWROSTER
	protected $_save_permission_level = 0; // FUTURE: PERM_EDITSERVICE;

	protected static function _getFields()
	{

		$fields = Array(
			'tag'		=> Array(
									'type'		=> 'text',
									'width'		=> 80,
									'initial_cap'	=> TRUE,
								   ),
		);
		return $fields;
	}


	function toString()
	{
		return $this->values['tag'];
	}

	/**
	 * Persist the tag list edited in the tag-editor form: rename the tags whose
	 * ids are supplied, delete every other existing tag, and create the new
	 * names.
	 *
	 * All three steps run in one transaction, so a failure part-way cannot
	 * leave tags deleted without their replacements.
	 *
	 * @param array<int, string> $renamed Existing tag id => new tag name
	 * @param array<int, string> $new_names Names of tags to create
	 * @return void
	 */
	public static function replaceAll(array $renamed, array $new_names): void
	{
		$db = $GLOBALS['db'];
		$system = $GLOBALS['system'];
		$system->doTransaction('BEGIN');
		try {
			foreach ($renamed as $id => $name) {
				$db->query('UPDATE service_component_tag
						SET tag = '.$db->quote($name).'
						WHERE id = '.(int)$id);
			}
			$keep = array_map('intval', array_keys($renamed));
			$db->query('DELETE FROM service_component_tag'
				.($keep ? ' WHERE id NOT IN ('.implode(',', $keep).')' : ''));
			foreach ($new_names as $name) {
				$db->query('INSERT INTO service_component_tag (tag)
						VALUES ('.$db->quote($name).')');
			}
			$system->doTransaction('COMMIT');
		} catch (Throwable $e) {
			$system->doTransaction('ROLLBACK');
			throw $e;
		}
	}

	/**
	 * Save this (new) tag to the database IF there isn't already a tag with the same name.
	 * @return bool Whether it was newly created
	 */
	public function createIfNew()
	{
		$db = $GLOBALS['db'];
		$sql = 'SELECT id
			FROM service_component_tag
			WHERE tag = '.$db->quote($this->getValue('tag'));
		$id = $db->queryOne($sql);
		if (!(int)$id) {
			return $this->create();
		} else {
			$this->id = $id;
			return FALSE;
		}
	}


}