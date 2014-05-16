<?php
include_once 'db_objects/abstract_note.class.php';
class Family_Note extends Abstract_Note
{
	function _getFields()
	{
		return Array(
				'familyid'	=> Array(
								'type'			=> 'int',
								'references'	=> 'family',
								'editable'		=> false,
								'allow_empty'	=> false,
								'label'			=> 'Family',
							   ),
			   );

	}

	function getInitSQL()
	{
		return "
			CREATE TABLE `family_note` (
			  `familyid` int(11) NOT NULL default '0',
			  `id` int(11) NOT NULL default '0',
			  PRIMARY KEY  (`familyid`,`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;
		";
	}

	function readyToCreate()
	{
		$res = parent::readyToCreate();
		if ($this->values['familyid']) {
			$members = $GLOBALS['system']->getDBObjectData('person', Array('familyid' => $this->values['familyid']));
			if (count($members) == 1) {
				trigger_error('Family notes can only be added to families with at least two members.  Add a person note instead.');
				$res = FALSE;
			}
		}
		return $res;

	}


	function printFieldValue($name, $value=NULL)
	{
		if (is_null($value)) $value = $this->values[$name];
		if ($name == 'familyid') {
			if (!empty($value)) {
				$family =& $GLOBALS['system']->getDBObject('family', $value);
				?>
				<a href="?view=families&familyid=<?php echo $value; ?>"><?php echo $family->toString(); ?></a> (#<?php echo $value; ?>)
				<?php
				return;
			}
		}
		return parent::printFieldValue($name, $value);
	}

	function getInstancesQueryComps($params, $logic, $order)
	{
		$res = parent::getInstancesQueryComps($params, $logic, $order);
		$res['from'] = '('.$res['from'].') LEFT OUTER JOIN family family ON family_note.familyid = family.id';
		$res['select'][] = 'family.family_name as family_name';
		return $res;
	}

}
?>
