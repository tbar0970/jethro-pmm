<?php
include_once 'db_objects/abstract_note.class.php';
class Person_Comm extends Abstract_Note
{
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
			CREATE TABLE `person_comm` (
			  `personid` int(11) NOT NULL default '0',
			  `id` int(11) NOT NULL default '0',
			  PRIMARY KEY  (`personid`,`id`),
			  CONSTRAINT `pn_personid` FOREIGN KEY (personid) REFERENCES _person(id) ON DELETE CASCADE,
			  CONSTRAINT pn_id FOREIGN KEY (id) REFERENCES abstract_note(id) ON DELETE CASCADE
			) ENGINE=InnoDB ;
		";
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
		$res['from'] = '('.$res['from'].') LEFT OUTER JOIN person person ON person_comm.personid = person.id';
		$res['select'][] = 'person.first_name as person_fn';
		$res['select'][] = 'person.last_name as person_ln';
		return $res;
	}

	function printFieldInterface($name, $prefix = '') {
		parent::printFieldInterface($name, $prefix);
	}

	
}
?>
