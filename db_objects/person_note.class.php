<?php
include_once 'db_objects/abstract_note.class.php';
class Person_Note extends Abstract_Note
{
	function _getFields()
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


	function getInitSQL()
	{
		return "
			CREATE TABLE `person_note` (
			  `personid` int(11) NOT NULL default '0',
			  `id` int(11) NOT NULL default '0',
			  PRIMARY KEY  (`personid`,`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;
		";
	}

	function printFieldValue($name, $value=NULL)
	{
		if (is_null($value)) $value = $this->values[$name];
		if ($name == 'personid') {
			if (!empty($value)) {
				$person =& $GLOBALS['system']->getDBObject('person', $value);
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
		$res['from'] = '('.$res['from'].') LEFT OUTER JOIN person person ON person_note.personid = person.id';
		$res['select'][] = 'person.first_name as person_fn';
		$res['select'][] = 'person.last_name as person_ln';
		return $res;
	}

}
?>