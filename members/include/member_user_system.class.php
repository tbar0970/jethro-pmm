<?php
require_once JETHRO_ROOT.'/include/general.php';
require_once JETHRO_ROOT.'/include/abstract_user_system.class.php';
class Member_User_System extends Abstract_User_System
{
	private $_error;

	public function __construct()
	{
	}

	public function run() {
		if (!empty($_REQUEST['logout'])) {
			$this->_clearAuthMember();
		} else if (empty($_SESSION['member']) && !empty($_REQUEST['login-request'])) {
			$this->handleLoginRequest();
		} else if (!empty($_REQUEST['password-request']) && !empty($_REQUEST['email'])) {
			$this->handleAccountRequest();
		} else if (!empty($_REQUEST['verify'])) {
			$this->processEmailVerification();
		} else if (!empty($_REQUEST['set-password'])) {
			$this->processSetPassword();
		}

		if (!empty($_SESSION['member'])) {
			if (defined('SESSION_TIMEOUT_MINS') && constant('SESSION_TIMEOUT_MINS')) {
				if ((time() - $_SESSION['last_activity_time']) / 60 > SESSION_TIMEOUT_MINS) {
					// They have been idle too long
					$this->_clearAuthMember();
					$this->printLogin();
				}
			}
			if (defined('SESSION_MAXLENGTH_MINS') && constant('SESSION_MAXLENGTH_MINS')) {
				if ((time() - $_SESSION['login_time']) / 60 > SESSION_MAXLENGTH_MINS) {
					// They have been logged in too long
					$this->_clearAuthMember();
					$this->printLogin();
				}

			}
			$_SESSION['last_activity_time'] = time();
            $GLOBALS['db']->setCurrentUserID((int)$_SESSION['member']['id']);


			include JETHRO_ROOT.'/include/permission_levels.php';
			foreach ($PERM_LEVELS as $i => $detail) {
				list($define_symbol, $desc, $feature_code) = $detail;
				define('PERM_'.$define_symbol, $i);
			}
			return;

		} else {
			$this->printLogin();
		}

	}

	private function handleLoginRequest()
	{
			// process the login form
			if (array_get($_SESSION, 'login_key', NULL) != $_POST['login_key']) {
				$this->_error = 'Login Key Incorrect.  Please try again.';
				return;
			}
			$user_details = $this->_findAuthMember($_POST['email'], $_POST['password']);
			if (is_null($user_details)) {
				$this->_error = 'Incorrect email address or password';
				return;
			} else {
				// Log the member in
				$this->_setAuthMember($user_details);
			}
	}

	private function handleAccountRequest()
	{
			$person = $this->_findCandidateMember($_REQUEST['email']);
			require_once 'include/emailer.class.php';
			$failureEmail = MEMBER_REGO_FAILURE_EMAIL;

			if (is_array($person)) {
				// Send them an email

				$hash = generate_random_string(32);
				$SQL = 'UPDATE _person
						SET resethash='.$GLOBALS['db']->quote($hash).',
						resetexpires = NOW() + INTERVAL 24 HOUR
						WHERE id = '.(int)$person['id'];
				$res = $GLOBALS['db']->exec($SQL);

				$url = BASE_URL.'/members/?email='.rawurlencode($person['email']).'&verify='.rawurlencode($hash);

				$body = "Hi %s,

To activate your %s account, please %s

If you didn't request an account, you can just ignore this email";

				$text = sprintf($body, $person['first_name'], SYSTEM_NAME, 'go to '.$url);
				$html = sprintf(nl2br($body), $person['first_name'], SYSTEM_NAME, '<a href="'.$url.'">click here</a>.');

				$message = Emailer::newMessage()
				  ->setSubject(MEMBER_REGO_EMAIL_SUBJECT)
				  ->setFrom(array(MEMBER_REGO_EMAIL_FROM_ADDRESS => MEMBER_REGO_EMAIL_FROM_NAME))
				  ->setTo(array($person['email'] => $person['first_name'].' '.$person['last_name']))
				  ->setBody($text)
				  ->addPart($html, 'text/html');

				$res = Emailer::send($message);

				if (TRUE == $res) {
					require_once 'templates/account_request_received.template.php';
					exit;
				} else {
					$this->_error = 'Could not send to the specified address.  Your email server may be experiencing problems.';
					return;
				}

			} else if (!Emailer::validateAddress($_REQUEST['email'])) {
				$this->_error = 'You have entered an invalid email address.  Please check the address and try again.';

			} else if (($person == -1) && !empty($failureEmail)) {
				// This email address is in use by two or more persons from *different families*.
				// Therefore this address cannot be used for member access.

				$message = Emailer::newMessage()
				  ->setSubject("Member Account request from multi-family email")
				  ->setFrom(array(MEMBER_REGO_EMAIL_FROM_ADDRESS => SYSTEM_NAME.' Jethro System'))
				  ->setTo(MEMBER_REGO_FAILURE_EMAIL)
				  ->setBody("Hi, \n\nThis is an automated message from the Jethro system at ".BASE_URL.".\n\n"
						  ."Somebody has used the form at ".BASE_URL."/members to request member-access to this Jethro system. \n\n"
						  ."The email address they specified was ".$_REQUEST['email']." but this address belongs to SEVERAL persons from DIFFERENT families.  It therefore can't be used for member access.\n\n"
						  ."Please look up this email address in Jethro and contact the relevant persons to help them solve this problem.\n\n");

				$res = Emailer::send($message);

				// Show the user the generic "thanks" page - because we do not want
				// to tell strangers whether an email is or isn't known.
				require_once 'templates/account_request_received.template.php';
				exit;

			} else if (!empty($failureEmail)) {
				// This email address doesn't match any person record.
				// Send the administrator an email

				$message = Emailer::newMessage()
				  ->setSubject("Member Account request from unknown email")
				  ->setFrom(array(MEMBER_REGO_EMAIL_FROM_ADDRESS => SYSTEM_NAME.' Jethro System'))
				  ->setTo(MEMBER_REGO_FAILURE_EMAIL)
				  ->setBody("Hi, \n\nThis is an automated message from the Jethro system at ".BASE_URL.".\n\n"
						  ."Somebody has used the form at ".BASE_URL."/members to request member-access to this Jethro system. \n\n"
						  ."The email address they specified was ".$_REQUEST['email']." but there is no current person record in the Jethro system with that address. (There could be an archived record).\n\n"
						  ."If you believe this person is a church member, please add their email address to their person record and then ask them to try registering again.\n\n");

				$res = Emailer::send($message);

				// Show the user the generic "thanks" page - because we do not want
				// to tell strangers whether an email is or isn't known.
				require_once 'templates/account_request_received.template.php';
				exit;
			} else {
				// Show the user the generic "thanks" page - because we do not want
				// to tell strangers whether an email is or isn't known.
				// (even though there is no failure email to send)
				require_once 'templates/account_request_received.template.php';
				exit;
			}
	}

	private function processEmailVerification() {
		if ($person = $this->_findPendingMember($_REQUEST['email'], $_REQUEST['verify'])) {
			$this->_setAuthMember($person);
			require_once('templates/set_password.template.php');
			exit;
		} else {
			$this->_error = 'The account request is not valid.  You may have used an out-of-date link.  Please try registering again.';
		}
	}

	private function processSetPassword() {
		$db = $GLOBALS['db'];
		$val = $_REQUEST['password1'];
		if ($val != $_REQUEST['password2']) {
			$this->_error = 'Password and password confirmation do not match.  Try again.';
			require_once('templates/set_password.template.php');
			exit;
		} else if (strlen($val) < MEMBER_PASSWORD_MIN_LENGTH) {
			$this->_error = 'Password is too short - must be at least '.MEMBER_PASSWORD_MIN_LENGTH.' characters; Password not saved.';
			require_once('templates/set_password.template.php');
			exit;
		} else if (!preg_match('/[0-9]+/', $val) || !preg_match('/[^0-9]+/', $val)) {
			$this->_error = 'Password is too simple - it must contain letters and numbers; Password not saved.';
			require_once('templates/set_password.template.php');
			exit;
		} else {
			$sql = 'UPDATE _person '
					. 'SET `member_password` = '.$db->quote(jethro_password_hash($val)).', '
					. 'resethash = NULL, '
					. 'resetexpires = NULL '
					. 'WHERE id = '.(int)$_SESSION['member']['id'];
			$res = $db->exec($sql);

			if (!empty($_REQUEST['isreset'])) {
				add_message('Your password has been successfully changed.');
			} else {
				add_message('Welcome!  Your account is complete and you are now logged in.');
			}
		}
	}


	public function printLogin()
	{
		$_SESSION['login_key'] = $login_key = generate_random_string(32);
		require TEMPLATE_DIR.'/login_form.template.php';
		exit;

	}//end printLogin()

	/**
	 * Get details of the currently-authorised church member
	 * @param string $field	Particular field to return; null=return all fields
	 * @return mixed
	 */
	public function getCurrentMember($field='')
	{
		if (empty($_SESSION['member'])) {
			return NULL;
		} else {
			if (empty($field)) {
				return $_SESSION['member'];
			} else {
				return array_get($_SESSION['member'], $field, '');
			}
		}

	}

	/**
	 * Get details of the currently-authorised church member
	 * @see Abstract_User_System::getCurrentPerson()
	 * @param string $field	Particular field to return; null=return all fields
	 * @return mixed
	 */
	public function getCurrentPerson($field='')
	{
		return $this->getCurrentMember($field);
	}

	/**
	 * Set the session as having the specified person logged in
	 * @param array $member_details
	 */
	private function _setAuthMember($member_details)
	{
		// Recreate session ID when logging in
		session_regenerate_id();
		$_SESSION = Array();
		$_SESSION['member'] = $member_details;
		$_SESSION['login_time'] = time();
		$_SESSION['last_activity_time'] = time();
	}

	/**
	 * Set the session as not having any member logged in
	 */
	private function _clearAuthMember() {
		$_SESSION['member'] = NULL;
		$_SESSION['login_time'] = NULL;
		$_SESSION['last_activity_time'] = NULL;
	}

	/**
	 * Find a person record to which we could attach a member account
	 * If the email address belongs to several persons in the one family, it returns the first member
	 *   preferring adult males.
	 * If the email address belongs to several persons in different families, returns -1
	 *   because such addresses cannot be used for member-login.
	 * If the email address does not belong to any person record, returns null.
	 * @param string $email	Email address for the account
	 * @return mixed.
	 */
	private function _findCandidateMember($email)
	{
		$db =& $GLOBALS['db'];
		$sql = 'SELECT COUNT(DISTINCT familyid) '
				. 'FROM _person '
				. 'WHERE email = '.$db->quote($email).''
				. 'AND status <> "archived"';
		$familyCount = $db->queryOne($sql);

		if ($familyCount > 1) return -1;


		$sql = 'SELECT p.*
				FROM _person p
				JOIN age_bracket ab ON ab.id = p.age_bracketid
				WHERE p.email  = '.$db->quote($email).'
				AND status <> "archived"
				ORDER BY (IF(p.member_password IS NOT NULL, 0, 1)), ab.rank ASC, p.gender DESC';
		$res = $db->queryRow($sql);

		return $res;
	}

	/**
	 * Find a person record that has the specified email and account-creation hash
	 * @param string $email
	 * @param string $hash
	 * @return array
	 */
	private function _findPendingMember($email, $hash) {
		$db =& $GLOBALS['db'];
		$sql = 'SELECT p.*
				FROM _person p
				WHERE p.email  = '.$db->quote($email).'
				AND resethash = '.$db->quote($hash).'
				AND resetexpires > NOW()';
		$res = $db->queryRow($sql);
		return $res;
	}


	/**
	 * Find a person record that matches the given email and password
	 * @param string $email		Find a person with this record
	 * @param string $password	Find a person with this member_password
	 * @return array	Person details
	 */
	private function _findAuthMember($email, $password)
	{
		$db =& $GLOBALS['db'];
		$sql = 'SELECT p.*
				FROM _person p
				WHERE p.email  = '.$db->quote($email).' AND member_password IS NOT NULL';
		$res = $db->queryAll($sql);
		foreach ($res as $row) {
			if (jethro_password_verify($password, $row['member_password'])) {
				unset($row['member_password']);
				unset($row['history']);
				return $row;
			}
		}
		return NULL;
	}

}
