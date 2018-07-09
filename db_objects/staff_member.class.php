<?php
/**
* NB In the web interface, "staff member" objects are referred to as "user accounts"
*/
include_once 'db_objects/person.class.php';
class Staff_Member extends Person
{
	protected $_save_permission_level = PERM_SYSADMIN; // but see below

	var $_restrictions = Array();
	var $_old_restrictions = NULL;

	function __construct($id=0)
	{
		if ($id == $GLOBALS['user_system']->getCurrentUser('id')) {
			$this->_save_permission_level = 0;
		}
		return parent::__construct($id);
	}

	function load($id)
	{
		$res = parent::load($id);

		// Load restrictions
		$sql = 'SELECT congregationid, NULL as groupid
				FROM account_congregation_restriction
				WHERE personid = '.(int)$id.'
				UNION
				SELECT NULL as congregationid, groupid
				FROM account_group_restriction
				WHERE personid = '.(int)$id;
		$res = $GLOBALS['db']->queryAll($sql);
		foreach ($res as $row) {
			$type = empty($row['congregationid']) ? 'group' : 'congregation';
			$this->_restrictions[$type][] = $row[$type.'id'];
		}

		return $res;
	}

	protected static function _getFields()
	{
		return Array(
			'username'	=> Array(
									'type'		=> 'text',
									'width'		=> 30,
									'maxlength'	=> 255,
									'allow_empty'	=> false,
									'autocomplete' => false,
								   ),
			'password'		=> Array(
									'type'		=> 'text',
									'width'		=> 30,
									'maxlength'	=> 255,
									'allow_empty'	=> false,
									'autocomplete' => false,
								   ),
			'active'			=> Array(
									'type'		=> 'select',
									'options'	=> Array(
													'0'	=> 'No',
													'1'	=> 'Yes',
												   ),
									'default'	=> '1',
									'label'		=> 'Account active?',
						   ),
			'permissions'  => Array(
									'type'		=> 'bitmask',
									'options'	=> $GLOBALS['user_system']->getPermissionLevels(),
									'default'	=> ifdef('DEFAULT_PERMISSIONS', 2147483647),
									'label'		=> 'Permissions Granted',
									'cols'		=> 3,
									'note'      => 'NOTE: Changes to permissions only take effect the next time a user logs in',
						   ),

		);

	}


	// We need this to override person::getInitSQL
	public function getInitSQL($table_name=NULL)
	{
		return $this->_getInitSQL();
	}

	public function getForeignKeys()
	{
		return Array();
	}


	public function getTasks($type='all')
	{
		$date_exp = '';
		switch ($type) {
			case 'now':
				$date_exp = 'AND action_date <= DATE(NOW())';
				break;
			case 'later':
				$date_exp = 'AND action_date > DATE(NOW())';
		}
		$db =& $GLOBALS['db'];
		$sql = 'SELECT
					an.id, an.subject, pn.personid, fn.familyid, an.action_date,
					IF(p.id IS NOT NULL,
						CONCAT(p.first_name, '.$db->quote(' ').', p.last_name),
						CONCAT(f.family_name, '.$db->quote(' Family').')
					) as name,
					IF(p.id IS NOT NULL, '.$db->quote('person').', '.$db->quote('family').') as type
				FROM abstract_note an
						LEFT JOIN person_note pn ON an.id = pn.id
						LEFT JOIN person p ON pn.personid = p.id
						LEFT JOIN family_note fn ON an.id = fn.id
						LEFT JOIN family f ON fn.familyid = f.id
				WHERE COALESCE(p.id, f.id) IS NOT NULL
					AND an.assignee = '.$db->quote($this->id).'
					AND an.status = '.$db->quote('pending').'
					'.$date_exp.'
				ORDER BY action_date ASC';
		$res = $db->queryAll($sql, null, null, true);
		return $res;
	}

	/**
	* Print the value of a field to the HTML interface
	*
	* Subclasses should add links and other HTML markup by overriding this
	*/
	function printFieldValue($name, $value=NULL)
	{
		if (is_null($value)) $value = $this->getValue($name);
		if ($name == 'restrictions') {
			?>
			<table class="standard">
				<tr>
					<th>Congregation Restrictions: &nbsp;</th>
					<th>Group Restrictions:</th>
				</tr>
				<tr>
					<td>
						<?php
						if (!empty($value['congregation'])) {
							foreach ($GLOBALS['system']->getDBObjectData('congregation', Array('(id' => $value['congregation'])) as $cong) {
								echo ents($cong['name']).'<br />';
							}
						} else {
							echo '(None)';
						}
						?>
					</td>
					<td>
						<?php
						if (!empty($value['group'])) {
							foreach ($GLOBALS['system']->getDBObjectData('person_group', Array('(id' => $value['group'])) as $group) {
								echo ents($group['name']).'<br />';
							}
						} else {
							echo '(None)';
						}
						?>
					</td>
				</tr>
			</table>
			<?php
		} else {
			return parent::printFieldValue($name, $value);
		}
	}

	function printForm($prefix='', $fields=NULL)
	{
		$this->fields['restrictions'] = Array('type' => 'custom');
		parent::printForm($prefix, $fields);
		unset($this->fields['restrictions']);
	}

	function printFieldInterface($name, $prefix='')
	{
		switch ($name) {
			case 'username':
				print_widget($prefix.'user_un', $this->fields['username'], $this->getValue('username'));
				break;
			case 'password':
				if (($GLOBALS['user_system']->getCurrentUser('id') == $this->id) || $GLOBALS['user_system']->havePerm(PERM_SYSADMIN)) {
					?>
					<input type="password" data-minlength="<?php echo (int)$this->getMinPasswordLength(); ?>" autocomplete="off" name="<?php echo $prefix.'user_pw1'; ?>" /><br />
					<input type="password" data-minlength="<?php echo (int)$this->getMinPasswordLength(); ?>" autocomplete="off" name="<?php echo $prefix.'user_pw2'; ?>" /><br />
					<p class="help-inline">Enter once, then again to confirm. Passwords must be at least <?php echo (int)$this->getMinPasswordLength(); ?> characters and contain 2 letters and 2 numbers</p>
					<?php
				} else {
					?>
					<p class="small">A user's password can only be edited by system administrators or the user themselves</p>
					<?php
				}
				break;
			case 'restrictions':
				$GLOBALS['system']->includeDBClass('person_group');
				if ($GLOBALS['user_system']->havePerm(PERM_SYSADMIN)) {
					?>
					<table class="standard">
						<tr>
							<td>Restrict to these congregations: &nbsp</td>
							<td>Restrict to these groups:</td>
						</tr>
						<tr>
							<td>
								<?php
								print_widget(
									'restrictions[congregation]',
									Array('type' => 'reference', 'references' => 'congregation', 'allow_multiple' => true),
									array_get($this->_restrictions, 'congregation', Array())
								);
								?>
							</td>
							<td>
								<?php
								Person_Group::printMultiChooser('restrictions[group]', array_get($this->_restrictions, 'group', Array()), Array(), FALSE);
								?>
							</td>
						</tr>
					</table>
					<p class="help-inline">If you select congregations or groups here, this user will only be able to see persons who belong to one of the selected congregations or groups.  It will look like those are the only congregations, groups and persons in the system.  
						Users with group <?php if (!ifdef('RESTRICTED_USERS_CAN_ADD')) echo 'or congregation'; ?> restrictions cannot add new persons or families.  Changes to restrictions take effect immediately.
					</p>
					<?php
				} else {
					$this->printFieldValue($name);
					?>
					<p class="help-inline">Only system administrators can edit this field</p>
					<?php
				}
				break;
			case 'permissions':
				if ($name == 'permissions') {
					// Subtly display the numeric permission level to help with config.php
					?>
					<div title="This number represents this combination of permissions, and can be used to set the default permissions in the config file" style="color: #ccc; position: absolute; right: 10px; padding: 3px"><?php echo $this->getValue($name) ?></div>
					<?php
				}
				if ($GLOBALS['user_system']->havePerm(PERM_SYSADMIN)) {
					parent::printFieldInterface($name, $prefix);
				} else {
					$this->printFieldValue($name);
					?>
					<p class="help-inline">Only system administrators can edit this field</p>
					<?php
				}
				break;
			default:
				parent::printFieldInterface($name, $prefix);
		}
	}


	function processFieldInterface($name, $prefix='')
	{
		switch ($name)
		{
			case 'username':
				$this->setValue('username', array_get($_REQUEST, $prefix.'user_un'));
				break;
			case 'password':
				if (!empty($_REQUEST[$prefix.'user_pw1'])) {
					$val = $_REQUEST[$prefix.'user_pw1'];
					if ($val != $_REQUEST[$prefix.'user_pw2']) {
						trigger_error('Password and password confirmation do not match; Password not saved.');
					} else if (strlen($val) < $this->getMinPasswordLength()) {
						trigger_error('Password is too short - must be at least '.$this->getMinPasswordLength().' characters; Password not saved.');
					} else if (!preg_match('/[0-9]+/', $val) || !preg_match('/[^0-9]+/', $val)) {
						trigger_error('Password is too simple - it must contain letters and numbers; Password not saved.');
					} else {
						$this->setValue($name, jethro_password_hash($val));
						$this->_tmp['raw_password'] = $val; // only saved in this script execution
					}
				}
				break;

			case 'permissions':
				if (!$GLOBALS['user_system']->havePerm(PERM_SYSADMIN)) {
					return;
				}
				// fall through
			default:
				parent::processFieldInterface($name, $prefix);
		}
	}

	private function getMinPasswordLength() {
		$minLen = defined('PASSWORD_MIN_LENGTH') ? (int)PASSWORD_MIN_LENGTH : 0;
		$minLen = max($minLen, 8);
		return $minLen;
	}

	function processForm($prefix='', $fields=NULL)
	{
		parent::processForm($prefix, $fields);

		if ($GLOBALS['user_system']->havePerm(PERM_SYSADMIN) && !empty($_REQUEST['restrictions'])) {
			$this->_old_restrictions = $this->_restrictions;
			$this->_restrictions = Array();
			foreach (Array('congregation', 'group') as $type) {
				$this->_restrictions[$type] = Array();
				foreach (array_get($_REQUEST['restrictions'], $type, Array()) as $id) {
					if (!empty($id)) $this->_restrictions[$type][] = $id;
				}
			}
		}
	}


	function getValue($name)
	{
		if ($name == 'raw_password') return array_get($this->_tmp, 'raw_password');
		if ($name == 'restrictions') return $this->_restrictions;
		return parent::getValue($name);
	}


	function _createFinal()
	{
		$res = parent::_createFinal();
		if ($res) {
			$this->_insertRestrictions();
			$GLOBALS['system']->runHooks('staff_member_created', $this);
		}
		return $res;
	}

	function save($update_family = true)
	{
		// Only admins can edit staff other than themselves
		if (!empty($GLOBALS['JETHRO_INSTALLING']) || ($GLOBALS['user_system']->getCurrentUser('id') == $this->id) || $GLOBALS['user_system']->havePerm(PERM_SYSADMIN)) {

			// Save main fields
			parent::save();

			// Save restrictions
			if (isset($this->_old_restrictions)) {
				foreach (Array('congregation', 'group') as $type) {
					if (array_get($this->_restrictions, $type, Array()) != array_get($this->_old_restrictions, $type, Array())) {
						$res = $GLOBALS['db']->query('DELETE FROM account_'.$type.'_restriction WHERE personid = '.(int)$this->id);
					}
				}
				$this->_insertRestrictions();
			}

			// Run hooks
			$GLOBALS['system']->runHooks('staff_member_updated', $this);

			return TRUE;
		} else {
			trigger_error('Permission denied to save user account');
			return FALSE;
		}
	}

	function _insertRestrictions()
	{
		if (empty($this->id)) trigger_error("Don't have an ID, can't insert restrictions", E_USER_ERROR);
		foreach (Array('congregation', 'group') as $type) {
			if (!empty($this->_restrictions[$type])) {
				$rows = Array();
				foreach ($this->_restrictions[$type] as $id) {
					$rows[] = '('.(int)$this->id.','.(int)$id.')';
				}
				$res = $GLOBALS['db']->query('INSERT IGNORE INTO account_'.$type.'_restriction (personid, '.$type.'id) VALUES '.implode(',', $rows));
			}
		}
	}

	public function checkUniqueUsername()
	{
		$others = $GLOBALS['system']->getDBObjectData('staff_member', Array('username' => $this->getValue('username'), '!id' => $this->id), 'AND');
		if ($others) {
			add_message("The username '".$this->getValue('username')."' is already in use.  Please choose a different username.", 'error');
			return FALSE;
		}
		return TRUE;
	}
}
?>
