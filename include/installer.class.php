<?php
require_once 'db_objects/staff_member.class.php';
require_once 'db_objects/family.class.php';
require_once 'db_objects/congregation.class.php';

class Installer
{
	var $initial_person_fields = Array('first_name', 'last_name', 'gender', 'username', 'password', 'email');
	var $person = NULL;
	var $family = NULL;
	var $user = NULL;
	var $congregations = Array();

	function run()
	{
		if ($GLOBALS['db']->hasTables() || $GLOBALS['db']->hasFunctions()) {
			trigger_error('MySQL database is not empty. Installer is aborting');
			exit();
		}
		include 'templates/installer.template.php';
	}


	function printBody()
	{
		require_once dirname(__FILE__).'/system_controller.class.php';
		$GLOBALS['system'] = $GLOBALS['system'] = System_Controller::get();
		set_error_handler(Array($GLOBALS['system'], '_handleError'));

		// the first time we call initInitialEntities is just to check for errors
		if ($this->readyToInstall() && $this->initInitialEntities()) {
			$GLOBALS['JETHRO_INSTALLING'] = 1;
			date_default_timezone_set('Australia/Sydney'); // Temporary timezone to avoid errors during install
			$this->initDB();
			Config_Manager::init();
			$this->initInitialEntities(); // do this afresh now that settings have been loaded
			$this->createInitialEntities();
			unset($GLOBALS['JETHRO_INSTALLING']);

			$this->printConfirmation();
		} else {
			$this->printForm();
		}
	}

	function readyToInstall()
	{
		if (empty($_POST)) {
			return FALSE;
		}

		if ($GLOBALS['db']->hasTables() || $GLOBALS['db']->hasFunctions()) {
			print_message('MySQL database is not empty. Installer is aborting');
			return FALSE;
		}

		foreach ($this->initial_person_fields as $field) {
			if (isset($_POST['install_'.$field]) && empty($_POST['install_'.$field])) {
				print_message('You must enter a value for '.$field.' to proceed', 'error');
				return FALSE;
			}
		}

		// if we get to here, all person details were supplied
		if (empty($_POST['congregation_name'])) {
			print_message('You must enter at least one congregation name to proceed', 'error');
			return FALSE;
		}
		$cong_found = FALSE;
		foreach ($_POST['congregation_name'] as $cname) {
			if (!empty($cname)) {
				$cong_found = TRUE;
				break;
			}
		}
		if (!$cong_found) {
			print_message('You must enter at least one congregation name to proceed', 'error');
			return FALSE;
		}

		if (empty($_REQUEST['system_name'])) {
			print_message("You must enter a system name", 'error');
		}

		return TRUE;
	}



	function initDB($printOnly=FALSE)
	{
		$allSQL = Array();
		ini_set('max_execution_time', 120);
		$filenames = glob(dirname(dirname(__FILE__)).'/db_objects/*.class.php');

		$fks  = Array();
		$views = Array();

		sort($filenames);
		foreach ($filenames as $filename) {
			$filename = basename($filename);
			$classname = str_replace('.class.php', '', $filename);
			require_once dirname(dirname(__FILE__)).'/db_objects/'.$filename;
			$data_obj = new $classname;
			if (method_exists($data_obj, 'getInitSQL')) {
				$sql = $data_obj->getInitSQL();
				if (!empty($sql)) {
					if (!is_array($sql)) $sql = Array($sql);
					foreach ($sql as $s) {
						$allSQL[] = $s;
					}
				}

				$f = $data_obj->getForeignKeys();
				if ($f) $fks[$classname] = $f;

				$v = $data_obj->getViewSQL();
				if ($v) $views[$classname] = $v;
			}
		}

		$sql = Array(
			"CREATE TABLE `db_object_lock` (
			  `objectid` int(11) NOT NULL default '0',
			  `userid` int(11) NOT NULL default '0',
			  `lock_type` VARCHAR( 16 ) NOT NULL,
			  `object_type` varchar(255) NOT NULL default '',
			  `expires` datetime NOT NULL,
			  KEY `objectid` (`objectid`),
			  KEY `userid` (`userid`),
			  KEY `object_type` (`object_type`)
			) ENGINE=InnoDB ;",

			"CREATE FUNCTION getCurrentUserID() RETURNS INTEGER NO SQL RETURN @current_user_id;",

			"CREATE TABLE account_group_restriction (
			   personid INTEGER NOT NULL,
			   groupid INTEGER NOT NULL,
			   PRIMARY KEY (personid, groupid),
			   CONSTRAINT account_group_restriction_personid FOREIGN KEY (personid) REFERENCES staff_member(id),
			   CONSTRAINT account_group_restriction_groupid FOREIGN KEY (groupid) REFERENCES _person_group(id)
			) engine=innodb;",

			"CREATE TABLE account_congregation_restriction (
			   personid INTEGER NOT NULL,
			   congregationid INTEGER NOT NULL,
			   PRIMARY KEY (personid, congregationid),
			   CONSTRAINT account_congregation_restriction_personid FOREIGN KEY (personid) REFERENCES staff_member(id),
			   CONSTRAINT account_group_restriction_congregationid FOREIGN KEY (congregationid) REFERENCES congregation(id)
			) engine=innodb;",

			'CREATE VIEW member AS
			SELECT mp.id, mp.first_name, mp.last_name, mp.gender, mp.age_bracketid, mp.congregationid,
			mp.email, mp.mobile_tel, mp.work_tel, mp.familyid,
			mf.family_name, mf.address_street, mf.address_suburb, mf.address_state, mf.address_postcode, mf.home_tel
			FROM _person mp
			JOIN person_status mps ON mps.id = mp.status
			JOIN family mf ON mf.id = mp.familyid
			JOIN person_group_membership pgm1 ON pgm1.personid = mp.id
			JOIN _person_group pg ON pg.id = pgm1.groupid AND pg.share_member_details = 1
			JOIN person_group_membership pgm2 ON pgm2.groupid = pg.id
			JOIN _person up ON up.id = pgm2.personid
			JOIN person_status ups ON ups.id = up.status
			WHERE up.id = getCurrentUserID()
			   AND (NOT mps.is_archived)    /* dont show archived persons */
			   AND mf.status <> "archived"  /* dont show archived families */
			   AND (NOT ups.is_archived)	/* dont let persons who are themselves archived see anything */

			UNION

			SELECT mp.id, mp.first_name, mp.last_name, mp.gender, mp.age_bracketid, mp.congregationid,
			mp.email, mp.mobile_tel, mp.work_tel, mp.familyid,
			mf.family_name, mf.address_street, mf.address_suburb, mf.address_state, mf.address_postcode, mf.home_tel
			FROM _person mp
			JOIN person_status mps ON mps.id = mp.status
			JOIN family mf ON mf.id = mp.familyid
			JOIN _person self ON self.familyid = mp.familyid
			JOIN person_status selfs ON selfs.id = self.status
			WHERE
				self.id = getCurrentUserID()
				AND ((NOT mps.is_archived) OR (mp.id = self.id))
				AND ((NOT selfs.is_archived) OR (mp.id = self.id))
				/* archived persons can only see themselves, not any family members */
			;',

			"CREATE TABLE setting (
				`rank`  int(11) unsigned,
				heading VARCHAR(255) DEFAULT NULL,
				symbol VARCHAR(255) NOT NULL,
				note VARCHAR(255) NOT NULL,
				type VARCHAR(255) NOT NULL,
				value VARCHAR(255) NOT NULL,
				CONSTRAINT UNIQUE KEY `setting_symbol` (`symbol`)
			  );",

			"SET @rank = 1;	",

			"INSERT INTO setting (`rank`, heading, symbol, note, type, value)
			 VALUES
			(@rank:=@rank+5, '','SYSTEM_NAME','Label displayed at the top of every page','text',''),

			(@rank:=@rank+5, 'Permissions and Security','ENABLED_FEATURES','Which Jethro features are visible to users?','multiselect{\"NOTES\":\"Notes\",\"PHOTOS\":\"Photos\",\"ATTENDANCE\":\"Attendance\",\"ROSTERS&SERVICES\":\"Rosters & Services\",\"SERVICEDETAILS\":\"Service Details\",\"DOCUMENTS\":\"Documents\",\"SERVICEDOCUMENTS\":\"Service documents\"}','NOTES,PHOTOS,ATTENDANCE,ROSTERS&SERVICES,SERVICEDETAILS,DOCUMENTS,SERVICEDOCUMENTS'),
			(@rank:=@rank+5, '',                         'DEFAULT_PERMISSIONS','Permissions to grant to new user accounts by default','int','7995391'),
			(@rank:=@rank+5, '',                         'RESTRICTED_USERS_CAN_ADD','Allow users with group/congregation restrictions to create new persons and families?','bool','0'),
			(@rank:=@rank+5, '',                         'PASSWORD_MIN_LENGTH','Minimum password length','int','8'),
			(@rank:=@rank+5, '',                         'SESSION_TIMEOUT_MINS','Inactive sessions will be logged out after this number of minutes','int','90'),
			(@rank:=@rank+5, '',                         'SESSION_MAXLENGTH_MINS','Every session will be logged out this many minutes after login','int','480'),

			(@rank:=@rank+5, '2-Factor Authentication',  '2FA_REQUIRED_PERMS','Users who hold permission levels selected here will be required to complete 2-factor authentication at login.','text',''),
			(@rank:=@rank+5, '',                         '2FA_EVEN_FOR_RESTRICTED_ACCTS','Require 2-factor auth even for accounts with group/congregation restrictions?','bool','0'),
			(@rank:=@rank+5, '',                         '2FA_TRUST_DAYS','Users can tick a box to skip 2-factor auth for this many days. Set to 0 to disable.','int','30'),
			(@rank:=@rank+5, '',                         '2FA_SENDER_ID','Sender ID for 2-factor auth messages','text','Jethro'),

			(@rank:=@rank+5, 'Jethro Behaviour Options','REQUIRE_INITIAL_NOTE','Whether an initial note is required when adding new family','bool','1'),
			(@rank:=@rank+5, '',                         'DEFAULT_NOTE_STATUS','Default status when creating a new note','select{\"no_action\":\"No Action Required\",\"pending\":\"Requires Action\"}', 'pending'),
			(@rank:=@rank+5, '',                         'NOTES_ORDER','Order to display person and family notes','select{\"ASC\":\"Oldest first\",\"DESC\":\"Newest first\"}','ASC'),
			(@rank:=@rank+5, '',                         'LOCK_LENGTH','Number of minutes users have to edit an object before their lock expires','int','10'),
			(@rank:=@rank+5, '',                         'PERSON_LIST_SHOW_GROUPS','Show all groups when listing persons?','bool','0'),
			(@rank:=@rank+5, '',                         'NOTES_LINK_TO_EDIT','Should the homepage notes list link to the edit-note page?','bool','0'),
			(@rank:=@rank+5, '',                         'CHUNK_SIZE','Batch size to aim for when dividing lists of items','int','100'),
			(@rank:=@rank+5, '',                         'REPEAT_DATE_THRESHOLD','When a roster has this many columns, show the date on the right as well as the left','int','10'),
			(@rank:=@rank+5, '',                         'ROSTER_WEEKS_DEFAULT','Number of weeks to show in rosters by default','int','8'),
			(@rank:=@rank+5, '',                         'ATTENDANCE_DEFAULT_DAY','Default day to record attendance','select[\"Sunday\",\"Monday\",\"Tuesday\",\"Wednesday\",\"Thursday\",\"Friday\",\"Saturday\"]','Sunday'),
			(@rank:=@rank+5, '',                         'ENVELOPE_WIDTH_MM','Envelope width (mm)','int','220'),
			(@rank:=@rank+5, '',                         'ENVELOPE_HEIGHT_MM','Envelope height (mm)','int','110'),

			(@rank:=@rank+5, 'Data Structure options',   'PERSON_STATUS_OPTIONS','','',''),
			(@rank:=@rank+5, '',                         'AGE_BRACKET_OPTIONS','','',''),
			(@rank:=@rank+5, '',                         'GROUP_MEMBERSHIP_STATUS_OPTIONS','','',''),
			(@rank:=@rank+5, '',                         'TIMEZONE','','text','Australia/Sydney'),
			(@rank:=@rank+5, '',                         'ADDRESS_STATE_OPTIONS','(Leave blank to hide the state field)','multitext_cm', 'ACT,NSW,NT,QLD,SA,TAS,VIC,WA'),
			(@rank:=@rank+5, '',                         'ADDRESS_STATE_LABEL','Label for the \'state\' field. (Leave blank to hide the state field)','text','State'),
			(@rank:=@rank+5, '',                         'ADDRESS_STATE_DEFAULT','Default state', 'text', 'NSW'),
			(@rank:=@rank+5, '',                         'ADDRESS_SUBURB_LABEL','Label for the \'suburb\' field','text','Suburb'),
			(@rank:=@rank+5, '',                         'ADDRESS_POSTCODE_LABEL','Label for the \'postcode\' field','text','Postcode'),
			(@rank:=@rank+5, '',                         'ADDRESS_POSTCODE_WIDTH','Width of the postcode box','int','4'),
			(@rank:=@rank+5, '',                         'ADDRESS_POSTCODE_REGEX','Regex to validate postcodes; eg /^[0-9][0-9][0-9][0-9]$/ for 4 digits','text','/^[0-9][0-9][0-9][0-9]$/'),
			(@rank:=@rank+5, '',                         'HOME_TEL_FORMATS','Valid formats for home phone; use X for a digit','multitext_nl','XXXX-XXXX\n(XX) XXXX-XXXX'),
			(@rank:=@rank+5, '',                         'WORK_TEL_FORMATS','Valid formats for work phone; use X for a digit','multitext_nl','XXXX-XXXX\n(XX) XXXX-XXXX'),
			(@rank:=@rank+5, '',                         'MOBILE_TEL_FORMATS','Valid formats for mobile phone; use X for a digit','multitext_nl','XXXX-XXX-XXX'),

			(@rank:=@rank+5, 'Member area',              'MEMBER_LOGIN_ENABLED','Should church members be able to log in at <system_url>members ?','bool','0'),
			(@rank:=@rank+5, '',                         'MEMBER_REGO_EMAIL_FROM_NAME','Sender name for member rego emails','text',''),
			(@rank:=@rank+5, '',                         'MEMBER_REGO_EMAIL_FROM_ADDRESS','Sender address for member rego emails','text',''),
			(@rank:=@rank+5, '',                         'MEMBER_REGO_EMAIL_SUBJECT','Subject for member rego emails','text','Setting up your account'),
			(@rank:=@rank+5, '',                         'MEMBER_REGO_HELP_EMAIL', 'Address that users can contact for assistance with member rego (optional)', 'text', ''),			(@rank:=@rank+5, '',                         'MEMBER_REGO_FAILURE_EMAIL','Address to notifiy when member rego fails','text',''),
			(@rank:=@rank+5, '',                         'MEMBER_PASSWORD_MIN_LENGTH','Minimum length for member passwords','int','7'),
			(@rank:=@rank+5, '',                         'MEMBERS_SHARE_ADDRESS','Should addresses be visible in the members area?','bool','0'),

			(@rank:=@rank+5, 'iCal feeds',               'ROSTER_FEEDS_ENABLED','Whether users can access their roster assignments via an ical feed with secret URL', 'bool', 1),

			(@rank:=@rank+5, 'Public area',              'PUBLIC_AREA_ENABLED','Whether to allow public access to certain info at <system_url>public','bool',''),
			(@rank:=@rank+5, '',						  'SHOW_SERVICE_NOTES_PUBLICLY','Should service notes be visible in the public area at <system_url>public?','bool',''),
			(@rank:=@rank+5, '',                         'PUBLIC_ROSTER_SECRET','Advanced: Only allow access to public rosters if the URL contains \"&secret=<this-secret>\"','text',''),

			(@rank:=@rank+5, 'External Links',           'BIBLE_URL','URL Template for bible passage links, with the keyword __REFERENCE__','text','https://www.biblegateway.com/passage/?search=__REFERENCE__&version=NIVUK'),
			(@rank:=@rank+5, '',                         'CCLI_SEARCH_URL','URL Template for searching CCLI, with the keyword __TITLE__','text','https://songselect.ccli.com/search/results?search=__TITLE__'),
			(@rank:=@rank+5, '',                         'CCLI_DETAIL_URL','URL Template for CCLI song details by song number, with the keyword __NUMBER__','text','https://songselect.ccli.com/songs/__NUMBER__'),
			(@rank:=@rank+5, '',                         'CCLI_REPORT_URL','URL Template for reporting usage to CCLI by song number, with keyword __NUMBER__', 'text', 'https://reporting.ccli.com/search?s=__NUMBER__&page=1&category=all'),
			(@rank:=@rank+5, '',                         'POSTCODE_LOOKUP_URL','URL template for looking up postcodes, with the keyword __SUBURB__','text','https://auspost.com.au/postcode/__SUBURB__'),
			(@rank:=@rank+5, '',                         'MAP_LOOKUP_URL','URL template for map links, with the keywords __ADDRESS_STREET__, __ADDRESS_SUBURB__, __ADDRESS_POSTCODE__, __ADDRESS_STATE__','text','http://maps.google.com.au?q=__ADDRESS_STREET__,%20__ADDRESS_SUBURB__,%20__ADDRESS_STATE__,%20__ADDRESS_POSTCODE__'),
			(@rank:=@rank+5, '',                         'QR_CODE_GENERATOR_URL', 'URL template for generating QR codes, containing the placeholder __URL__', 'text', 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=__URL__'),
			(@rank:=@rank+5, '',                         'EMAIL_CHUNK_SIZE','When displaying mailto links for emails, divide into batches of this size','int','25'),
			(@rank:=@rank+5, '',                         'MULTI_EMAIL_SEPARATOR','When displaying mailto links for emails, separate addresses using this character','text',','),

			(@rank:=@rank+5, 'Task Notifications', 'TASK_NOTIFICATION_ENABLED', '(This feature also requires the task_reminder.php script to be called by cron every 5 minutes)', 'bool', 0),
			(@rank:=@rank+5, '',                   'TASK_NOTIFICATION_FROM_NAME', 'Name from which task notifications should be sent', 'text', 'Jethro'),
			(@rank:=@rank+5, '',                   'TASK_NOTIFICATION_FROM_ADDRESS', 'Email address from which task notifications should be sent', 'text', ''),
			(@rank:=@rank+5, '',                   'TASK_NOTIFICATION_SUBJECT', '', 'text', 'New notes assigned to you'),

			(@rank:=@rank+5, 'Mailchimp Sync',           'MAILCHIMP_API_KEY', 'API Key for Mailchimp integration. NB the mailchimp sync script must also be called regularly by cron.', 'text', ''),

			(@rank:=@rank+5, 'SMTP Email Server',        'SMTP_SERVER','SMTP server for sending emails','text',''),
			(@rank:=@rank+5, '',                         'SMTP_ENCRYPTION','Encryption method for SMTP server','select{\"ssl\":\"SSL\",\"tls\":\"TLS\",\"\":\"(None)\"}',''),
			(@rank:=@rank+5, '',                         'SMTP_USERNAME','Username for SMTP server','text',''),
			(@rank:=@rank+5, '',                         'SMTP_PASSWORD','Password for SMTP server','text',''),

			(@rank:=@rank+5, 'SMS Gateway',              'SMS_MAX_LENGTH','','int','160'),
			(@rank:=@rank+5, '',                         'SMS_HTTP_URL','URL of the SMS messaging service','text',''),
			(@rank:=@rank+5, '',                         'SMS_HTTP_HEADER_TEMPLATE','Template for the headers of a request to the SMS messaging service','text_ml',''),
			(@rank:=@rank+5, '',                         'SMS_HTTP_POST_TEMPLATE','Template for the body of a request to the SMS messaging service','text_ml',''),
			(@rank:=@rank+5, '',                         'SMS_RECIPIENT_ARRAY_PARAMETER','','text',''),
			(@rank:=@rank+5, '',                         'SMS_HTTP_RESPONSE_OK_REGEX','Regex for recognising a successful send','text_ml',''),
			(@rank:=@rank+5, '',                         'SMS_HTTP_RESPONSE_ERROR_REGEX','Regex for recognising an API error','text_ml',''),
			(@rank:=@rank+5, '',                         'SMS_LOCAL_PREFIX','Used for converting local to international numbers.  eg 0','text',''),
			(@rank:=@rank+5, '',                         'SMS_INTERNATIONAL_PREFIX','Used for converting local to international numbers. eg +61','text',''),
			(@rank:=@rank+5, '',                         'SMS_SAVE_TO_NOTE_BY_DEFAULT','Whether to save each sent SMS as a person note by default','bool',''),
			(@rank:=@rank+5, '',                         'SMS_SAVE_TO_NOTE_SUBJECT','','text','SMS Sent'),
			(@rank:=@rank+5, '',                         'SMS_SEND_LOGFILE','File on the server to save a log of sent SMS messages','text',''); "
		);
		foreach ($sql as $s) {
			$allSQL[] = $s;
		}

		foreach ($fks as $table => $keys) {
			foreach ($keys as $from => $to) {
				if (FALSE !== strpos($from, '.')) {
					list($table, $from) = explode('.', $from);
				}
				$name = $table.$from;
				$SQL = 'ALTER TABLE '.$table.'
						ADD CONSTRAINT `'.$name.'`
						FOREIGN KEY ('.$from.') REFERENCES '.$to;
				$allSQL[] = $SQL;
			}
		}

		foreach (array_unique($views) as $v) {
			$allSQL[] = $v;
		}

		// RUN ALL THE SQL WE'VE ACCUMULATED
		if ($printOnly) {
			foreach ($allSQL as $s) bam(str_replace("\t", "  ", trim($s)));
			return;
		}
		$sql_so_far = Array();
		foreach ($allSQL as $sql) {
			$sql_so_far[] = $sql;
			try {
				$GLOBALS['db']->query($sql);
			} catch (Exception $e) {
				trigger_error("Error during install.  Bad query is at the bottom of the list below.");
				bam($e->getMessage());
				bam($sql_so_far);
				exit;
			}
		}

		// NOW SAVE SOME SOME FINAL SETTINGS

		Config_Manager::saveSetting('SYSTEM_NAME', substr($_REQUEST['system_name'], 0, 30));

		if (!empty($_REQUEST['locale'])) {
			if (preg_match('/^[-0-9A-Za-z_]+$/', $_REQUEST['locale'])) {
				$fn = JETHRO_ROOT.'/locale/settings/'.$_REQUEST['locale'].'.csv';
				if (file_exists($fn)) {
					$fp = fopen($fn, 'r');
					while ($row = fgetcsv($fp)) {
						Config_Manager::saveSetting($row[0], $row[1]);
					}
					fclose($fp);
				} else {
					trigger_error("Unknown locale ".$_REQUEST['locale']);
				}
			} else {
				trigger_error("Bad locale ".$_REQUEST['locale']);
			}
		}
	}


	function initInitialEntities()
	{
		$this->congregations = Array();
		foreach ($_POST['congregation_name'] as $cname) {
			if (empty($cname)) continue;
			$c = new Congregation();
			$c->setValue('name', $cname);
			$c->setValue('long_name', $cname);
			$this->congregations[] = $c;
			if (!$c->validateFields()) return FALSE;
		}

		$this->user = new Staff_Member();
		foreach ($this->initial_person_fields as $field) {
			$this->user->processFieldInterface($field, 'install_');
		}
		$this->user->setValue('age_bracketid', 1);
		$this->user->setValue('congregationid', 1); // will be overwritten with a real one later
		$this->user->setValue('status', 0); // ditto
		$this->user->setValue('permissions', PERM_SYSADMIN);
		if (!$this->user->validateFields()) return FALSE;

		$this->family = new Family();
		$this->family->setValue('family_name', $this->user->getValue('last_name'));
		$this->family->setValue('creator', 0);
		if (!$this->family->validateFields()) return FALSE;

		return TRUE;
	}

	function createInitialEntities()
	{
		$cong_ids = Array();
		foreach ($this->congregations as $cong) {
			if (!$cong->create()) {
				$this->reportFailure();
				return;
			}
			$cong_ids[] = $cong->id;
		}

		if (!$this->family->create()) {
			$this->reportFailure();
			return;
		}

		$this->user->setValue('familyid', $this->family->id);
		$this->user->setValue('congregationid', reset($cong_ids));
		$this->user->setValue('status', Person_Status::getDefault());
		$this->user->setValue('creator', 0);
		if (!$this->user->create()) {
			$this->reportFailure();
			return;
		}


		$this->user->setValue('creator', $this->user->id);
		$this->user->save();

		$this->family->setValue('creator', $this->user->id);
		$this->family->save();
	}

	function reportFailure()
	{
		echo "<p style=\"color: red\">An error has occurred.  Your Jethro system has not installed successfully.  You will need to drop and re-create the mysql database before trying again.</p>";
		exit;
	}


	function printForm()
	{
		?>
		<h2>Welcome</h2>
		<p>Welcome to the Jethro installer.  The installation process will set up your MySQL database so that <br />it's ready to run Jethro.  First we need to collect some details to get things started.</p>

		<form method="post">
			<h3>Overall Settings</h3>
			<p>Please enter a name for your system and choose a set of default settings appropriate for your location.
			<br />Settings can be adjusted later under Admin &gt; System Configuration.</p>
			<table>
				<tr>
					<th>System Name</th>
					<td>
						<?php print_widget('system_name', Array(
							'type' => 'text',
							'maxlength' => 30,
							'placeholder' => 'eg. St Demo\'s Davidsville',
						), '');
						?>
					</td>
				</tr>
				<tr>
					<th>Default Settings</th>
					<td>
						<?php
						$options = Array();
						chdir(JETHRO_ROOT.'/locale/settings/');
						foreach (glob('*.csv') as $f) {
							$f = str_replace('.csv', '', $f);
							$options[$f] = ucfirst($f);
						}
						print_widget('locale', Array(
							'type' => 'select',
							'options' => $options,
						), 'Australia');
						?>
					</td>
				</tr>
			</table>

			<h3>Initial User Account</h3>
			<p>Please enter the details of the first user you want to add to the system.
			<br />This is the user account you will use to log in initially.
			<br />After you have logged in you can edit the rest of your details and add additional user accounts.</p>

			<table>
			<?php
			$sm = new Staff_Member();
			$sm->setValue('username', ifdef('PREFILL_USERNAME'));
			foreach ($this->initial_person_fields as $fieldname) {
				?>
				<tr>
					<th><?php echo $sm->getFieldLabel($fieldname); ?></th>
					<td><?php $sm->printFieldInterface($fieldname, 'install_'); ?></td>
				</tr>
				<?php
			}
			?>
			</table>

			<h3>Congregations</h3>
			<p>Please enter the names of the congregations in your church.  These can be edited later under Admin &gt; Congregations.</p>
			<table class="expandable">
				<tr>
					<td>
						<input type="text" name="congregation_name[]" />
					</td>
				</tr>
			</table>
			<p class="smallprint">(List expands as you type)</p>

			<h3>Continue...</h3>
			<input type="submit" class="btn" value="Set up the database" />
		</form>
		<?php
	}

	function printConfirmation()
	{
		dump_messages();
		?>
		<h2>Installation Complete!</h2>

		You can now <a href="<?php echo BASE_URL; ?>">log in to the system</a> to start work.

		<?php
	}
}
