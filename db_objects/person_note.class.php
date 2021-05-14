<?php
include_once 'db_objects/abstract_note.class.php';
class Person_Note extends Abstract_Note
{
	// A note template being used to populate this note
	private $_template = NULL;

	protected static function _getFields()
	{
		return Array(
				'personid'	=> Array(
								'type'			=> 'int',
								'references'	=> 'person',
								'editable'		=> false,
								'label'			=> 'Person',
							   ),
			   );

	}


	function getInitSQL($table_name=NULL)
	{
		return "
			CREATE TABLE `person_note` (
			  `personid` int(11) NOT NULL default '0',
			  `id` int(11) NOT NULL default '0',
			  PRIMARY KEY  (`personid`,`id`)
			) ENGINE=InnoDB ;
		";
	}

	function getForeignKeys() {
		return Array(
			'personid' => '_person(id) ON DELETE CASCADE',
			'id' => '_abstract_note(id) ON DELETE CASCADE',
		);
	}

	function printFieldValue($name, $value=NULL)
	{
		if (is_null($value)) $value = $this->values[$name];
		if ($name == 'personid') {
			if (!empty($value)) {
				$person = $GLOBALS['system']->getDBObject('person', $value);
				?>
				<a href="?view=persons&personid=<?php echo $value; ?>"><?php echo $person->toString(); ?></a> (#<?php echo $value; ?>)
				<?php
				return;
			}
		}
		return parent::printFieldValue($name, $value);
	}

	function getInstancesQueryComps($params, $logic, $order)
	{
		$res = parent::getInstancesQueryComps($params, $logic, $order);
		$res['from'] = '('.$res['from'].') JOIN person subject ON person_note.personid = subject.id';
		$res['select'][] = 'subject.first_name as person_fn';
		$res['select'][] = 'subject.last_name as person_ln';
		return $res;
	}

	function printFieldInterface($name, $prefix = '') {
		parent::printFieldInterface($name, $prefix);
		if ($name == 'subject') {
			?>
			<div id="note-field-widgets">
				<?php
				if ($this->_template) {
					$this->_template->printNoteFieldWidgets();
				}
				?>
			</div>
			<?php
		}
	}

	function setTemplate($template)
	{
		if (!$this->id) $this->setValue('subject', $template->getValue('subject'));
		$this->_template = $template;
	}

	function printForm($prefix = '', $fields = NULL)
	{
		Note_Template::printTemplateChooserRow($this->_template ? $this->_template->id : NULL);
		parent::printForm($prefix, $fields);
	}


	/**
	 * Save this (new) note to the database IF there isn't already a note with the same subject and body.
	 */
	public function createIfNew()
	{
		$db = $GLOBALS['db'];
		$SQL = 'SELECT an.id
			FROM abstract_note an
			JOIN person_note pn ON pn.id = an.id
			WHERE subject = '.$db->quote($this->getValue('subject')).'
			AND details = '.$db->quote($this->getValue('details')).'
			AND pn.personid = '.(int)$this->getValue('personid');
		if (!(int)$db->queryOne($SQL)) {
			return $this->create();
		} else {
			return FALSE;
		}
	}

}