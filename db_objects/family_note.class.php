<?php
include_once 'db_objects/abstract_note.class.php';
class Family_Note extends Abstract_Note
{
	protected static function _getFields()
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

	function getInitSQL($table_name=NULL)
	{
		return "
			CREATE TABLE `family_note` (
			  `familyid` int(11) NOT NULL default '0',
			  `id` int(11) NOT NULL default '0',
			  PRIMARY KEY  (`familyid`,`id`)
			) ENGINE=InnoDB;
		";
	}

	function getForeignKeys() {
		return Array(
			'familyid' => 'family(id) ON DELETE CASCADE',
			'id' => '_abstract_note(id) ON DELETE CASCADE',
		);
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
				$family = $GLOBALS['system']->getDBObject('family', $value);
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
		$res['from'] = '('.$res['from'].') JOIN family subject ON family_note.familyid = subject.id ';

		if ($GLOBALS['user_system']->getCurrentRestrictions()) {
			// eliminate any notes linked to families with no visible members
			$res['from'] .= ' JOIN person fmember ON fmember.familyid = subject.id ';
		}	
		$res['select'][] = 'subject.family_name as family_name';
		return $res;
	}

}