<?php
include_once 'include/db_object.class.php';
class Roster_Role extends db_object
{
	protected $_load_permission_level = NULL;
	protected $_save_permission_level = PERM_MANAGEROSTERS;
	var $_volunteers = NULL;

	function __construct($id=NULL) {
		parent::__construct($id);

		if (!$this->id) {
			$this->fields['active']['editable'] = false;
		}

		if (!empty($_REQUEST['congregationid'])) {
			$_SESSION['role_congregationid'] = $_REQUEST['congregationid'];
		} else if (empty($id) && !empty($_SESSION['role_congregationid'])) {
			$this->values['congregationid'] = array_get($_SESSION, 'role_congregationid');
		}
	}

	protected static function _getFields()
	{
		
		$fields = Array(
			'title'		=> Array(
									'type'		=> 'text',
									'maxlength'	=> 128,
									'initial_cap'	=> TRUE,
									'allow_empty' => FALSE,
								   ),
			'congregationid'	=> Array(
									'type'				=> 'reference',
									'references'		=> 'congregation',
									'label'				=> 'Congregation',
									'show_id'			=> FALSE,
									'order_by'			=> 'meeting_time',
									'allow_empty'		=> TRUE,
									'filter'			=> function($x) {$y = $x->getValue("meeting_time"); return !empty($y);},
									'note'				=> 'Only congregations that can contain services are available here',
								   ),
			'active'		=> Array(
									'type'			=> 'select',
									'options'		=> Array(1 => 'Yes', 0 => 'No'),
									'default'		=> '1',
									'note'			=> 'When a role is no longer to be used, mark it as inactive'
							   ),
			'volunteer_group'		=> Array(
									'type'		=> 'reference',
									'references' => 'person_group',
									'order_by'	=> 'name',
									'allow_empty'	=> true,
									'note'			=> 'If no volunteer group is chosen, any person in the system can be allocated to this role'
								   ),
			'assign_multiple'	=> Array(
									'type'			=> 'select',
									'options'		=> Array(1 => 'Yes', 0 => 'No'),
									'default'		=> 0,
									'note'			=> 'Whether multiple people can be assigned to this role on a given date'
							   ),
			'details'		=> Array(
									'type'		=> 'html',
									'label'		=> 'Role description',
									'note'		=> 'These details are shown when somebody clicks the role title in a published roster'
								   ),
		);
		return $fields;
	}
	
	/**
	 * Get foreign keys to apply to this class's DB table
	 */
	public function getForeignKeys()
	{
		return Array('roster_role.volunteer_group' => '_person_group(id)');
	}


	function printFieldInterface($name, $prefix='')
	{
		if (($name == 'volunteer_group') && (empty($this->id) || $this->haveLock())) {
			$GLOBALS['system']->includeDBClass('person_group');
			$value = array_get($this->values, $name);
			Person_Group::printChooser($prefix.$name, $value, array(), null, '(None)');
		} else {
			if ($name == 'active') {
				$memberships = $this->getViewMemberships();
				if ($memberships && $this->getValue('active')) {
					echo 'Yes<br />';
					$this->fields['active']['note'] = 'This role cannot be deactvated because it is used in '.count($memberships).' roster views.';
					return;
				}
			}
			parent::printFieldInterface($name, $prefix);
		}
	}

	function printFieldValue($name, $value=NULL)
	{
		switch ($name) {
			case 'details':
				echo '<a class="pull-right" target="publicrole" href="'.BASE_URL.'/public/?view=display_role_description&role='.$this->id.'"><i class="icon-share"></i>View in public area</a>';
				echo '<div class="text">'.$this->getValue($name).'</div>';
				break;
			case 'volunteer_group':
				if ($val = $this->getValue($name)) {
					$group = new Person_group($val);
					echo '<a href="?view=groups&groupid='.(int)$val.'">'.ents($group->getValue('name')).'</a>';
				}
				break;
			default:
				parent::printFieldValue($name, $value);
		}
	}

	function _getVolunteers()
	{
		if (is_null($this->_volunteers)) {
			$this->_volunteers = Array();
			if ($this->getValue('volunteer_group')) {
				$group = $GLOBALS['system']->getDBObject('person_group', $this->getValue('volunteer_group'));
				if ($group) {
					$params = Array('!(status' => Person_Status::getArchivedIDs());
					foreach ($group->getMembers($params) as $id => $details) {
						$this->_volunteers[$id] = $details['first_name'].' '.$details['last_name'];
					}
				}
			}
		}
		return $this->_volunteers;
	}


	function getInstancesQueryComps($params, $logic, $order)
	{
		$res = parent::getInstancesQueryComps($params, $logic, $order);
		$res['select'][] = 'g.name as volunteer_group_name, c.name as congregation_name';
		$res['from'] = '('.$res['from'].') 
							LEFT OUTER JOIN person_group g ON roster_role.volunteer_group = g.id
							LEFT OUTER JOIN congregation c ON roster_role.congregationid = c.id';
		return $res;
	}
	
	/**
	 * Get the roster views in which this role is used
	 */
	function getViewMemberships()
	{
		$SQL = 'SELECT * from roster_view_role_membership
				WHERE roster_role_id = '.(int)$this->id;
		return $GLOBALS['db']->queryAll($SQL);
	}

	function _printUnlistedAlloceeOption($personid)
	{
		$person = $GLOBALS['system']->getDBObject('person', $personid);
		?>
		<option value="<?php echo (int)$personid; ?>" class="unlisted-allocee" selected="selected" title="This person is not in the volunteer group for this role"><?php echo ents($person->toString()); ?></option>
		<?php
	}
	
	private function _printChooserOption($vid, $name, $selectedid, &$absentees)
	{
		$sel = $dis = $note = '';
		if ($vid == $selectedid) {
			$sel = ' selected="selected" ';
			if (in_array($vid, $absentees)) {
				$note = ' !! ABSENT !!';
			}
		} else {
			if (in_array($vid, $absentees)) {
				$note = ' (absent)';
				$dis = ' disabled="disabled" ';
			}
		}
		?>
		<option value="<?php echo $vid; ?>"<?php echo $sel.$dis;?>><?php echo ents($name).$note; ?></option>
		<?php
	}

	/**
	* Print a widget for choosing people to fulfill this role, using the volunteer group if applicable
	*/
	function printChooser($date, $currentval=Array(''), $absentees=Array())
	{
		if ($groupid = $this->getValue('volunteer_group')) {
			$volunteers = $this->_getVolunteers();
			if ($this->getValue('assign_multiple')) {
				if (empty($currentval)) $currentval = Array('');
				?>
				<table class="expandable no-borders no-padding">
				<?php
				foreach ($currentval as $id) {
					?>
					<tr><td>
					<select name="assignees[<?php echo $this->id; ?>][<?php echo $date; ?>][]">
						<option value=""></option>
					<?php
					if (!empty($id) && !isset($volunteers[$id])) $this->_printUnlistedAlloceeOption($id);
					foreach ($volunteers as $vid => $name) {
						$this->_printChooserOption($vid, $name, $id, $absentees);
					}
					?>
						<option class="other">Other...</option>
					</select>
					</td></tr>
					<?php
				}
				?>
				</table>
				<?php
			} else {
				$currentID = reset($currentval);
				?>
				<select name="assignees[<?php echo $this->id; ?>][<?php echo $date; ?>]">
					<option value=""></option>
				<?php
				if (!empty($currentID) && !isset($volunteers[$currentID])) $this->_printUnlistedAlloceeOption($currentID);
				foreach ($volunteers as $id => $name) {
					$this->_printChooserOption($id, $name, $currentID, $absentees);
				}
				?>
					<option class="other">Other...</option>
				</select>
				<?php
			}
		} else {
			$GLOBALS['system']->includeDBClass('person');
			if ($this->getValue('assign_multiple')) {
				Person::printMultipleFinder('assignees['.$this->id.']['.$date.']', $currentval, $date);
			} else {
				$currentID = (int)reset($currentval);
				Person::printSingleFinder('assignees['.$this->id.']['.$date.']', $currentID, $date);
			}
		}
	}
	
	public function canEditAssignments() {
		if ($this->getValue('volunteer_group')) {
			$group = $GLOBALS['system']->getDBObject('person_group', $this->getValue('volunteer_group'));
			if (!$group) {
				return FALSE; // there is a volunteer group but we cannot access it
			}
		}
		return TRUE;
	}

}