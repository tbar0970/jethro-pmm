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
	private $_is_public = FALSE;
	private $_is_cli_script = FALSE;
	/** Null, or why 2FA cannot send a code for the pending login (see _require2FA()). */
	private ?string $_2faBlockedReason = null;

	public function __construct()
	{
		$this->_loadPermissionLevels();

		if (!empty($_REQUEST['logout'])) {
			$this->_logOut();
		} else if (empty($_SESSION['user']) && !empty($_POST['username'])) {
			// process the login form
			if (array_get($_SESSION, 'login_key', NULL) != $_POST['login_key']) {
				$this->_error = 'Login form expired.  Please try again.';
				return;
			}
			$user_details = $this->_findUser($_POST['username'], $_POST['password']);
			if (empty($user_details)) {
				// No user found matching those credentials.
				$this->_error = 'Incorrect username or password';
				// Waste scammers' time with a randomised delay to slow down brute force attempts
				$_SESSION['bad_password_attempts'] = array_get($_SESSION, 'bad_password_attempts', 0) + 1;
				$sleeptime = rand(1, min(18, $_SESSION['bad_password_attempts'] * 2))/2;
				sleep($sleeptime);
			} else {
				// Found a user matching username and password. But check some things before making them the current user.
				$go_ahead = TRUE;
				if ($errs = self::getPasswordStrengthErrors($_POST['password'])) {
					// Found a user, but thier  password does not meet the current strength requirements.
					// Record their details but don't log them in.
					$_SESSION['user_requiring_password_upgrade'] = $user_details;
					$_SESSION['password_upgrade_key'] = generate_random_string(32);
					$_SESSION['password_upgrade_expiry'] = time() + (60*5); // 5 mins to change password
					$go_ahead = FALSE;
				}
				if ($this->_require2FA($user_details)) {
					$this->_init2FA($user_details);
					$go_ahead = FALSE;
				}
				if ($go_ahead) {
					// Found a user, nothing further required, so do the business to make them the logged-in user.
					$this->_logUserIn($user_details);
				}
			}
		} else if (!empty($_SESSION['2fa']['pending_user'])) {
			// Form-submission checks — only when the 2FA form was actually POSTed.
			// A request carrying the session cookie but no POST data must not
			// trigger expiry/key/code checks.
			if (!empty($_POST)) {
				if ($_SESSION['2fa']['expiry'] < time()) {
					add_message('SMS-code form has expired. Please try again.', 'warning');
					$this->_reset2FA();
					return;
				} else if (!hash_equals((string) $_SESSION['2fa']['key'], (string) array_get($_POST, '2fa_key'))) {
					add_message("2-factor authentication was interrupted. Please try again.", 'warning');
					$this->_reset2FA();
					return;
				}
				if (!empty($_POST['2fa_code'])) {
					// Constant-time comparison of the strings, not ==.
					if (hash_equals((string) $_SESSION['2fa']['code'], trim((string) $_POST['2fa_code']))) {
						$this->_2faLog($_SESSION['2fa']['pending_user']['username']." entered the correct code and will be logged in.");
						$this->_process2FATrust();
						if (empty($_SESSION['user_requiring_password_upgrade'])) {
							$this->_logUserIn($_SESSION['2fa']['pending_user']);
						}
						$this->_reset2FA();
					} else {
						$this->_error = 'Incorrect 2-factor code. Please try again';
						$this->_2faLog("Received incorrect code for ".$_SESSION['2fa']['pending_user']['username']);
						$_SESSION['2fa']['wrong_code_count']++;
						if ($_SESSION['2fa']['wrong_code_count'] > 1) $this->_reset2FA(); // start again from scratch after 2 wrong codes.
						return;
					}
				}
			}

		} else if (!empty($_SESSION['user_requiring_password_upgrade'])) {
			// We are processing a password upgrade.
			if ($_SESSION['password_upgrade_expiry'] < time()) {
				unset($_SESSION['user_requiring_password_upgrade']);
				return;
			} else if (array_get($_POST, 'password_upgrade_key') != $_SESSION['password_upgrade_key']) {
				unset($_SESSION['user_requiring_password_upgrade']);
				return;
			}
			if (!empty($_POST['new_user_pw1'])) {
				$hashed = Staff_Member::processPasswordField('new_');
				if ($hashed) {
					$this->_setUserPassword($_SESSION['user_requiring_password_upgrade']['id'], $hashed);
					$this->_logUserIn($_SESSION['user_requiring_password_upgrade']);
					add_message('Password updated');
					unset($_SESSION['user_requiring_password_upgrade']);
				}
				// else we fall through to show the password-upgrade form again.
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

			if (!empty($_SESSION['user'])) {
				$GLOBALS['db']->setCurrentUserID((int)$_SESSION['user']['id']);
			}
		}

	}//end constructor

	private function _logUserIn($user_details)
	{
		// Recreate session when logging in
		session_regenerate_id();
		$_SESSION = Array();
		$_SESSION['user'] = $user_details;
		$_SESSION['login_time'] = time();
		$_SESSION['last_activity_time'] = time();
		include_once 'include/size_detector.class.php';
		SizeDetector::processRequest();
		session_write_close();
		header('Location: '.build_url(Array()), true, 303); // the login form was POSTed; 303 See Other makes the subsequent page load a clean GET request.
		exit;
	}


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

	private function _setUserPassword($userid, $hashed) {
		$db = $GLOBALS['db'];
		$SQL = 'UPDATE staff_member
				SET password = '.$db->quote($hashed).'
				WHERE id = '.(int)$userid;
		return $db->exec($SQL);
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

	public function havePerm($permission)
	{
		if (!is_int($permission)) throw new \RuntimeException("Non-numeric permission level is invalid");
		if ($permission == 0) return true;
		if (!empty($GLOBALS['JETHRO_INSTALLING'])) return true;
		if (!array_key_exists($permission, $this->_permission_levels)) return false; // disabled feature
		// Beware: the & operator is less sticky than the == operator. So the parentheses around the & operation are very important!
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
	
	/**
	 * Similar to setPublic - there is no current user. Called by scripts.
	 */
	public function setCLIScript()
	{
        $GLOBALS['db']->setCurrentUserID(-1);
		$this->_is_cli_script = TRUE;
	}
	
	public function isCLIScript()
	{
		return $this->_is_cli_script;
	}

	public function printLogin()
	{
		if (!$this->hasUsers()) {
			throw new \RuntimeException("This system has no user accounts - it has not been installed properly");
			exit;
		}

		if (!empty($_SESSION['2fa']['pending_user'])) {
			// Print the 2fa form
			if ($_SESSION['2fa']['expiry'] < time()) {
				// took too long. Forget about them, and fall through to the login form again.
				unset($_SESSION['2fa']['pending_user']);
			} else {
				$key = $_SESSION['2fa']['key'];
				$person_name = $_SESSION['2fa']['pending_user']['first_name'];
				require TEMPLATE_DIR.'/2fa_form.template.php';
				exit;
			}
		}


		if (!empty($_SESSION['user_requiring_password_upgrade'])) {
			if ($_SESSION['password_upgrade_expiry'] < time()) {
				// took too long. Forget about them, and fall through to the login form again.
				unset($_SESSION['user_requiring_password_upgrade']);
			} else {
				$password_upgrade_key = $_SESSION['password_upgrade_key'];
				$person_name = $_SESSION['user_requiring_password_upgrade']['first_name'].' '.$_SESSION['user_requiring_password_upgrade']['last_name'];
				require TEMPLATE_DIR.'/upgrade_password_form.template.php';
				exit;
			}
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

	}

	/**
	 * @param array $user_details	Details of the person who has entered their password correctly
	 * @return boolean TRUE if this person needs to do 2-factor auth before being logged in.
	 */

	private function _require2FA($user_details)
	{
		// Allow 2FA to be forcibly turned off regardless of admin settings e.g. for dev environments
		if (defined('2FA_ENABLED') && constant('2FA_ENABLED')==False) return FALSE;

		if (!empty($_COOKIE['Jethro2FATrust'])) {
			$db = $GLOBALS['db'];
			$SQL = 'SELECT * FROM 2fa_trust
					WHERE userid = '.(int)$user_details['id'].'
					AND expiry > NOW()
					AND token = '.$db->quote($_COOKIE['Jethro2FATrust']);
			$trust_record = $db->queryOne($SQL);

			if ($trust_record) {
				$this->_2faLog($user_details['username'].' has an active "trust this browser" record, so no 2FA is required');
				return FALSE;
			}

			if (rand(0, 10) == 0) {
				// Clean up old trust records
				$SQL = 'DELETE FROM 2fa_trust WHERE expiry < NOW()';
				$db->exec($SQL);
			}
		}

		require_once __DIR__ . '/jethro_sms.php';

		$req_perms = ifdef('2FA_REQUIRED_PERMS', '');
		if (!strlen($req_perms)) return FALSE;
		if (!empty($user_details['congregation_restrictions']) && !ifdef('2FA_EVEN_FOR_RESTRICTED_ACCTS', true)) {
			$this->_2faLog($user_details['username'].' (with cong restrictions) does not require 2FA');
			return FALSE;
		}
		if (!empty($user_details['group_restrictions']) && !ifdef('2FA_EVEN_FOR_RESTRICTED_ACCTS', true)) {
			$this->_2faLog($user_details['username'].' (with group restrictions) does not require 2FA');
			return FALSE;
		}

		$required = FALSE;
		foreach (explode(',', $req_perms) as $perm) {
			$levels = $this->getPermissionLevels();
			if (!isset($levels[$perm])) {
				$this->_2faLog("ERROR: Unknown permission level ".$perm." in system setting 2FA_REQUIRED_PERMS");
				trigger_error("Unknown permission level ".$perm." in system setting 2FA_REQUIRED_PERMS", E_USER_WARNING);
				$required = TRUE; // error on the side of requiring 2FA
				break;
			}

			if (((int)$user_details['permissions'] & (int)$perm) == (int)$perm) {
				$permName = $this->getPermissionLevels()[$perm];
				$this->_2faLog($user_details['username'].' requires 2FA because they hold permission "'.$permName.'"');
				$required = TRUE;
				break;
			}
		}
		if (!$required) {
			$this->_2faLog($user_details['username'].' does not require 2FA');
			return FALSE;
		}

		// This user's login needs 2FA. If no code can be sent, stop the login
		// rather than silently letting it through without a second factor.
		$blocked = $this->_2faBlockedReason();
		if ($blocked !== null) {
			$this->_2faLog('ERROR: '.$user_details['username'].' requires 2FA but cannot be sent a code: '.$blocked);
			$this->_notifySysadmins("The user ".$user_details['username']." was unable to log in: two-factor authentication is required for them, but Jethro cannot send a code (".$blocked."). Fix the SMS settings, or disable 2FA with define('2FA_ENABLED', false) in conf.php.");
			$this->_2faBlockedReason = $blocked;
		}
		return TRUE;
	}

	/**
	 * Pick the sender a 2FA code is sent as, and say why there isn't one.
	 *
	 * 2FA sends before login, so the acting user's mobile is unknown. Order:
	 *   1. 2FA_SENDER_ID, a literal sender ID or number;
	 *   2. else a literal SMS_SENDER (SMS_SENDER=_USER_MOBILE_ can't resolve yet);
	 *   3. else the first sender ID the provider reports as ACMA-registered:
	 *      from 1 July 2026 an unregistered alphanumeric ID is shown to
	 *      recipients as 'Unverified'.
	 * With no usable sender the code cannot be sent, and 2FA would lock every
	 * affected user out, so the login is refused with this reason instead.
	 *
	 * Pure, for unit tests. $senderIds is null when the provider couldn't be read.
	 *
	 * @param \Sms\SenderID[]|null $senderIds
	 * @return array{0: ?\Sms\SmsSender, 1: ?string} the sender, or null and why not
	 */
	public static function resolve2FASender(string $configured, string $smsSender, ?array $senderIds): array
	{
		$configured = trim($configured);
		if ($configured !== '') {
			return array(\Sms\senderFromString($configured), null);
		}
		$smsSender = trim($smsSender);
		if ($smsSender !== '' && $smsSender !== '_USER_MOBILE_') {
			return array(\Sms\senderFromString($smsSender), null);
		}
		foreach ($senderIds ?? array() as $senderId) {
			if ($senderId instanceof \Sms\SenderID && $senderId->acmaApproved === true) {
				return array($senderId, null);
			}
		}
		if ($senderIds === null) {
			return array(null, 'no sender ID could be read from the SMS provider');
		}
		if ($smsSender === '_USER_MOBILE_') {
			return array(null, 'SMS_SENDER is _USER_MOBILE_, which cannot be resolved before login, and the SMS provider reports no ACMA-registered sender ID');
		}
		return array(null, 'neither 2FA_SENDER_ID nor SMS_SENDER is set, and the SMS provider reports no ACMA-registered sender ID');
	}

	/**
	 * {@see resolve2FASender()} with the settings and provider sender IDs.
	 *
	 * The provider is only asked when neither setting names a literal sender,
	 * and its sender-ID list is cached, so repeated 2FA logins are cheap.
	 *
	 * @return array{0: ?\Sms\SmsSender, 1: ?string}
	 */
	private function _twoFASender(): array
	{
		require_once __DIR__ . '/jethro_sms.php';
		$configured = (string) ifdef('2FA_SENDER_ID', '');
		$smsSender = (string) ifdef('SMS_SENDER', '');
		$senderIds = null;
		if (trim($configured) === '' && (trim($smsSender) === '' || trim($smsSender) === '_USER_MOBILE_')) {
			$senderIds = array();
			$providerResult = Jethro\Sms\getSmsProvider();
			if ($providerResult->isFailure()) {
				$senderIds = null;
			} else {
				$idsResult = $providerResult->getValue()->getSenderIds();
				if ($idsResult->isFailure()) {
					$senderIds = null;
				} else {
					$senderIds = $idsResult->getValue();
				}
			}
		}
		return self::resolve2FASender($configured, $smsSender, $senderIds);
	}

	/**
	 * Why a 2FA code cannot be sent right now, or null when it can.
	 *
	 * @return string|null
	 */
	private function _2faBlockedReason()
	{
		if (Jethro\Sms\legacyTfaGatewayConstantsDefined()) {
			return 'conf.php still defines 2FA_SMS_* settings for a dedicated 2FA SMS gateway, which Jethro no longer supports (2FA codes go through the main SMS gateway)';
		}
		if (!Jethro\Sms\isConfigured()) {
			return 'no SMS gateway is configured';
		}
		list($sender, $problem) = $this->_twoFASender();
		if ($sender === null) {
			return $problem . '; set 2FA_SENDER_ID to an ACMA-registered sender ID, or register one with your SMS provider';
		}
		return null;
	}

	/**
	 * {@see _2faBlockedReason()}, for the system configuration page's warning.
	 *
	 * Null when 2FA is switched off (2FA_ENABLED = false), so a page that asks
	 * unconditionally doesn't complain on installs that don't use 2FA. Callers
	 * still decide whether anyone requires it (2FA_REQUIRED_PERMS).
	 *
	 * @return string|null null when a 2FA code can be sent
	 */
	public function twoFactorBlockedReason(): ?string
	{
		if (defined('2FA_ENABLED') && constant('2FA_ENABLED') == false) {
			return null;
		}
		return $this->_2faBlockedReason();
	}

	public function handle2FAMobileTelChange($person, $old_mobile)
	{
		$staff_member = new Staff_Member($person->id);
		if (!$staff_member) return;
		if ($staff_member->requires2FA()) {
			// Send a notification to the old number.
			$msg = 'The mobile number for user account "'.$staff_member->getValue('username').'" was just changed to '.$person->getValue('mobile_tel').'. If this is unexpected, please contact your system administrator ASAP.';
			$this->_send2FAMessage($msg, Array('mobile_tel' => $old_mobile));
		}
	}

	/**
	 * Initialise the 2FA process
	 * @param array $user_details  The user who has just entered their password correctly
	 * @return voice
	 */
	private function _init2FA($user_details)
	{
		if ($this->_2faBlockedReason !== null) {
			add_message(
				'Two-factor authentication is required for your account, but Jethro cannot send you a code: '
				. $this->_2faBlockedReason
				. ". Ask your system administrator to fix the SMS settings, or to disable 2FA with define('2FA_ENABLED', false) in conf.php.",
				'error'
			);
			return;
		}

		$_SESSION['2fa'] = Array();
		$_SESSION['2fa']['pending_user'] = $user_details;
		$_SESSION['2fa']['code'] = generate_random_string(6, range(0, 9));
		$_SESSION['2fa']['key'] = generate_random_string(32);
		$_SESSION['2fa']['wrong_code_count'] = 0;
        $_SESSION['2fa']['expiry'] = time() + 600; // 10 minutes

		if (!strlen($user_details['mobile_tel'])) {
			$this->_2faLog($_SESSION['2fa']['pending_user']['username']." can't log in because they have blank mobile number. Sysadmins have been notified");
			add_message("2-factor auth could not be completed because your contact details are incomplete - no mobile number has been set.  Please ask a system administrator to add one to your record.", 'error');
			$this->_notifySysadmins("The user ".$_SESSION['2fa']['pending_user']['username']." was unable to log in, because they have no mobile number for 2-factor authentication.");
			$this->_reset2FA();
			return;
		}
		if (ifdef('SMS_2FA_BLOCK_MESSAGES', ifdef('2FA_BLOCK_MESSAGES', false))) {
			$this->_2faLog($_SESSION['2fa']['pending_user']['username']." WOULD BE sent code ".$_SESSION['2fa']['code']." at ".$_SESSION['2fa']['pending_user']['mobile_tel'].' but SMS_2FA_BLOCK_MESSAGES in effect');
			return;
		}

		$msg = $_SESSION['2fa']['code'].' '._('is your code to log in to').' '.SYSTEM_NAME;
		if (!$this->_send2FAMessage($msg, $_SESSION['2fa']['pending_user'])) {
			add_message("System error during 2-factor auth. Please contact your system administrator.", 'error');
			$this->_notifySysadmins("The user ".$_SESSION['2fa']['pending_user']['username']." was unable to log in, because Jethro could not send the 2-factor auth code. The SMS gateway may be down, or mis-configured.");
			$this->_reset2FA();
			return;
		}
		// Never log the code: anyone who can read the log could complete the login.
		$this->_2faLog($_SESSION['2fa']['pending_user']['username']." was sent a code at ".$_SESSION['2fa']['pending_user']['mobile_tel']);
	}

	/**
	 * Send the 2FA code by SMS to the 2fa-pending user.
	 * @param string $message
	 * @param array	$recipient	person record data
	 * @return boolean TRUE if sent successfully.
	 */
	private function _send2FAMessage($msg, $recipient)
	{

		require_once __DIR__ . '/jethro_sms.php';
		// getSenderFromRequest() cannot be used here: the user is not logged in yet,
		// so the request carries no sender param and getCurrentUserMobileNumber() is null.
		list($sender, $problem) = $this->_twoFASender();
		if ($sender === null) {
			$this->_2faLog('Cannot send 2FA: ' . $problem);
			return false;
		}
		$smsRecipients = Jethro\Sms\getRecipientsFromPersonRecords([$recipient['id'] ?? 0 => $recipient]);
		$result = Jethro\Sms\sendSms((string)$msg, $smsRecipients, $sender, logToDb: false);

		if ($result->isFailure()) {
			$this->_2faLog("ERROR: SMS send failure: ".$result->getError());
			return FALSE;
		}

		$batch = $result->getValue();
		$summary = \Sms\sendSummary($batch->deliveries, $smsRecipients);

		if ($summary instanceof \Sms\Failed) {
			$this->_2faLog("ERROR: SMS send failure: ".$summary->error);
			return FALSE;
		}

		if ($summary instanceof \Sms\AllSent || $summary instanceof \Sms\PartialSuccess) {
			return TRUE;
		}

		$this->_2faLog("ERROR: SMS send failure: ".print_r($result, true));
		return FALSE;
	}

	/**
	 * Process the 'trust this device for X days' checkbox, if relevant.
	 */
	private function _process2FATrust()
	{
		$trust_days = ifdef('2FA_TRUST_DAYS', 30);
		$db = $GLOBALS['db'];
		if (!empty($_REQUEST['2fa_trust'])) {
			$trust_token = generate_random_string(64);
			$SQL = 'INSERT INTO 2fa_trust (userid, token, expiry)
					VALUES ('.(int)$_SESSION['2fa']['pending_user']['id'].',
							'.$db->quote($trust_token).',
							NOW() + INTERVAL '.(int)$trust_days.' DAY)';
			$res = $db->exec($SQL);
			if (!$res) $this->_2faLog("ERROR: Failed saving trust record to DB");

			$expiry = strtotime('+'.$trust_days.' days');
				$res = setcookie('Jethro2FATrust', $trust_token, [
					'expires'  => $expiry,
					'httponly' => true,
					'samesite' => 'Lax',
				]);
			if (!$res) trigger_error("Could not save trust cookie");


			$this->_2faLog("Saved trust to expire in $trust_days days on ".date('c', $expiry));
		}
	}

	/**
	 * Clear state of the 2FA system
	 */
	private function _reset2FA()
	{
		unset($_SESSION['2fa']);
	}

	/**
	 * Write to the 2FA log
	 * @param string $message
	 */
	private function _2faLog($message)
	{
		$logfile = ifdef('SMS_2FA_LOGFILE', ifdef('2FA_LOGFILE'));
		if (!$logfile) return;

		if (strtolower(ifdef('SMS_2FA_LOG_LEVEL', ifdef('2FA_LOG_LEVEL', 'full'))) == 'quiet') {
			if (0 !== strpos($message, 'ERROR')) return; // in 'quiet' mode, only log messages starting with ERROR.
		}

		$message = date('c').' '.$message;
		$res = @error_log($message."\n", 3, $logfile);
		if (!$res) trigger_error("Failed writing to 2fa logfile", E_USER_WARNING);
	}

	/**
	 * Send an email to SysAdmins (used if something goes wrong in the 2FA process)
	 * @param string $message
	 * @return void
	 */
	private function _notifySysAdmins($message)
	{
		$SQL = 'SELECT email
				FROM _person p
				JOIN person_status ps ON ps.id = p.status
				JOIN staff_member sm on p.id = sm.id
				WHERE email <> "" AND sm.active AND (NOT ps.is_archived) AND sm.permissions = '.PERM_SYSADMIN;
		$emails = $GLOBALS['db']->queryCol($SQL);
		if (empty($emails)) return;

		$text = "Hi, \n\nThis is an automated message to System Administrators, sent from the Jethro system at ".baseurl_absolute().".\n\n";
		$text .= $message;

		$message = Emailer::newMessage()
		  ->setSubject("Jethro SysAdmin notification for ".SYSTEM_NAME)
		  ->setTo($emails)
		  ->text($text);

		$from_address = ifdef('MEMBER_REGO_EMAIL_FROM_ADDRESS', '');
		if (strlen($from_address)) {
			$message->setFrom(array($from_address => SYSTEM_NAME.' Jethro System'));
		} else {
			$message->setFrom(array(reset($emails) => SYSTEM_NAME.' Jethro System'));

		}

		try {
			$res = Emailer::send($message);
			if (!$res) {
				$this->_2faLog("ERROR: Failed sending 2FA-failure notification to SysAdmins");
			}
		} catch (Exception $e) {
			$this->_2faLog("ERROR: Failed sending 2FA-failure notification to SysAdmins: ".$e->getMessage());
		}

	}

	public function hasUsers()
	{
		$SQL = 'SELECT count(*) FROM staff_member WHERE 1';
		try {
			return ($GLOBALS['db']->queryOne($SQL) > 0);
		} catch (Exception $ex) {
			return FALSE;
		}
	}
	
	/**
	 * Return true if the supplied password is correct for the current user
	 * @param string $password
	 * @return bool
	 */
	public function reverifyCurrentUser($password)
	{
		if (!empty($GLOBALS['JETHRO_INSTALLING'])) return TRUE;
		$res = $this->_findUser($this->getCurrentUser('username'), $password);
		return ($res) && ($res['id'] == $this->getCurrentUser('id'));
	}

	/**
	 * Check password strength
	 * @param string $val  Password to check
	 * @return   FALSE if no errors, otherwise a string error message.
	 */
	public static function getPasswordStrengthErrors($val)
	{
		$min_length = max(8, ifdef('PASSWORD_MIN_LENGTH', 8));
		if (strlen($val) < $min_length) {
			return 'Passwords must be at least '.$min_length.' characters';
		}
		if (!preg_match('/[0-9]+/', $val) || !preg_match('/[^0-9]+/', $val)) {
			return 'Passwords must contain letters and numbers';
		}
		return FALSE;
	}


}//end class
