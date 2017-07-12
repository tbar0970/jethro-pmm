<?php
require_once dirname(__FILE__).'/general.php';
require_once dirname(__FILE__).'/abstract_user_system.class.php';

/**
 * This class is the user system for fully-logged-in users (eg staff members).
 * See members/include/member_user_system for church-member login handling.
 */
class User_System extends Abstract_User_System
{
	private $_error;
	private $_permission_levels = Array();
	private $_is_public = FALSE;

	public function __construct()
	{
		include 'permission_levels.php';
		$enabled_features = explode(',', strtoupper(ifdef('ENABLED_FEATURES', '')));
		foreach ($PERM_LEVELS as $i => $detail) {
			list($define_symbol, $desc, $feature_code) = $detail;
			define('PERM_'.$define_symbol, $i);
			if (empty($feature_code) || in_array($feature_code, $enabled_features)) {
				$this->_permission_levels[$i] = $desc;
			}
		}

		if (!empty($_REQUEST['logout'])) {
			$this->_logOut();
		} else if (empty($_SESSION['user']) && !empty($_POST['username'])) {
			// process the login form
			if (array_get($_SESSION, 'login_key', NULL) != $_POST['login_key']) {
				$this->_error = 'Login Key Incorrect.  Please try again.';
				return;
			}
			$user_details = $this->_findUser($_POST['username'], $_POST['password']);
			if (is_null($user_details)) {
				$this->_error = 'Incorrect username or password';
			} else {
				// Log the user in
				// Recreate session when logging in
				session_regenerate_id();
				$_SESSION = Array();
				$_SESSION['user'] = $user_details;
				$_SESSION['login_time'] = time();
				$_SESSION['last_activity_time'] = time();
				include_once 'include/size_detector.class.php';
				SizeDetector::processRequest();
			}
		}
		if (!empty($_SESSION['user'])) {
			if (defined('SESSION_TIMEOUT_MINS') && constant('SESSION_TIMEOUT_MINS')) {
				if ((time() - $_SESSION['last_activity_time']) / 60 > SESSION_TIMEOUT_MINS) {
					// They have been idle too long
					$this->_logOut();
				}
			}
			if (defined('SESSION_MAXLENGTH_MINS') && constant('SESSION_MAXLENGTH_MINS')) {
				if ((time() - $_SESSION['login_time']) / 60 > SESSION_MAXLENGTH_MINS) {
					// They have been logged in too long
					$this->_logOut();
				}

			}
			$_SESSION['last_activity_time'] = time();

            $GLOBALS['db']->setCurrentUserID((int)$_SESSION['user']['id']);
		}

	}//end constructor


	public function setError($s)
	{
		$this->_error = $s;
	}

	private function _logOut() {
		if (!empty($_SESSION['user'])) {
			DB_Object::releaseAllLocks($_SESSION['user']['id']);
		}
		$_SESSION['user'] = NULL;
		$_SESSION['login_time'] = NULL;
		$_SESSION['last_activity_time'] = NULL;
	}

	/**
	 * Get details of the currently-authorised user account
	 * @see Abstract_User_System::getCurrentPerson()
	 * @param string $field	Particular field to return; null=return all fields
	 * @return mixed
	 */

	public function getCurrentPerson($field='')
	{
		return $this->getCurrentUser($field);
	}

	/**
	 * Get details of the currently-authorised *user account* (staff member)
	 * @param string $field	Particular field to return; null=return all fields
	 * @return mixed
	 */
	public function getCurrentUser($field='')
	{
		if (empty($_SESSION['user']) || $this->_is_public) {
			return NULL;
		} else {
			if (empty($field)) {
				return $_SESSION['user'];
			} else {
				return array_get($_SESSION['user'], $field, '');
			}
		}

	}//end getCurrentUser()

	public function getCurrentRestrictions($type=NULL)
	{
		$res = Array();
		if (!empty($_SESSION['user']['group_restrictions'])) $res['group'] = $_SESSION['user']['group_restrictions'];
		if (!empty($_SESSION['user']['congregation_restrictions'])) $res['congregation'] = $_SESSION['user']['congregation_restrictions'];
		if ($type) $res = array_get($res, $type);
		return $res;
	}

	public function getPermissionLevels()
	{
		return $this->_permission_levels;
	}

	public function havePerm($permission)
	{
		if ($permission == 0) return true;
		if (!empty($GLOBALS['JETHRO_INSTALLING'])) return true;
		if (!array_key_exists($permission, $this->_permission_levels)) return false; // disabled feature
		return (($this->getCurrentUser('permissions') & $permission) == $permission);
	}

	public function checkPerm($permission)
	{
		if (!$this->havePerm($permission)) {
			trigger_error('Your user account does not have permission to perform this action');
			exit;
		}
	}

	// Called by the public interface to indicate no login expected
	public function setPublic()
	{
        $GLOBALS['db']->setCurrentUserID(-1);
		$this->_is_public = TRUE;
	}

	public function printLogin()
	{
		if (!$this->hasUsers()) {
			trigger_error("This system has no user accounts - it has not been installed properly", E_USER_ERROR);
			exit;
		}
		$_SESSION['login_key'] = $login_key = generate_random_string(32);
		require TEMPLATE_DIR.'/login_form.template.php';

	}//end printLogin()

	private function _findUser($username, $password)
	{
		$db =& $GLOBALS['db'];
		$sql = 'SELECT sm.*, p.*, GROUP_CONCAT(cr.congregationid) as congregation_restrictions, GROUP_CONCAT(gr.groupid) as group_restrictions
				FROM staff_member sm
					JOIN _person p ON sm.id = p.id
					LEFT JOIN account_congregation_restriction cr ON cr.personid = sm.id
					LEFT JOIN account_group_restriction gr ON gr.personid = sm.id
				WHERE sm.username = '.$db->quote($username).'
					AND active = 1
				GROUP BY p.id';
		$row = $db->queryRow($sql);
		if (!empty($row) && jethro_password_verify($password, $row['password'])) {
			$row['congregation_restrictions'] = empty($row['congregation_restrictions']) ? Array() : explode(',', $row['congregation_restrictions']);
			$row['group_restrictions'] = empty($row['group_restrictions']) ? Array() : explode(',', $row['group_restrictions']);
			return $row;
		}
		return NULL;

	}//end _validateUser()

	public function hasUsers()
	{
		$SQL = 'SELECT count(*) FROM staff_member WHERE 1';
		try {
			return ($GLOBALS['db']->queryOne($SQL) > 0);
		} catch (Exception $ex) {
			return FALSE;
		}
	}


}//end class
