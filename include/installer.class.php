<?php
require_once 'db_objects/staff_member.class.php';
require_once 'db_objects/family.class.php';
require_once 'db_objects/congregation.class.php';

class Installer
{
	var $initial_person_fields = Array('first_name', 'last_name', 'gender', 'username', 'password', 'email');
	var $person = NULL;
	var $family = NULL;
	var $congregations = Array();

	function run()
	{
		$sql = 'SELECT count(*) FROM _person';
		$res = $GLOBALS['db']->queryOne($sql);
		if (!PEAR::isError($res)) {
			trigger_error('System has already been installed, installer is aborting');
			exit();
		}
		include 'templates/installer.template.php';
	}


	function printBody()
	{
		require_once dirname(__FILE__).'/system_controller.class.php';
		$GLOBALS['system'] = $GLOBALS['system'] = System_Controller::get();
		set_error_handler(Array($GLOBALS['system'], '_handleError'));

		if ($this->readyToInstall() && $this->initInitialEntities()) {
			$GLOBALS['JETHRO_INSTALLING'] = 1;
			$this->initDB();
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

		return TRUE;
	}



	function initDB()
	{
		ini_set('max_execution_time', 120);
		$dh = opendir(dirname(dirname(__FILE__)).'/db_objects');
		while (FALSE !== ($filename = readdir($dh))) {
			if (($filename[0] == '.') || is_dir($filename)) continue;
			$filenames[] = $filename;
		}

		$fks  = Array();

		sort($filenames);
		foreach ($filenames as $filename) {
			$classname = str_replace('.class.php', '', $filename);
			require_once dirname(dirname(__FILE__)).'/db_objects/'.$filename;
			$data_obj = new $classname;
			if (method_exists($data_obj, 'getInitSQL')) {
				$sql = $data_obj->getInitSQL();
				if (!empty($sql)) {
					if (!is_array($sql)) $sql = Array($sql);
					foreach ($sql as $s) {
						$r = $GLOBALS['db']->query($s);
						check_db_result($r);
					}
				}

				$f = $data_obj->getForeignKeys();
				if ($f) $fks[$classname] = $f;
			}
		}

		$sql = Array(
			"CREATE TABLE `db_object_lock` (
			  `objectid` int(11) NOT NULL default '0',
			  `userid` int(11) NOT NULL default '0',
			  `lock_type` VARCHAR( 16 ) NOT NULL,
			  `object_type` varchar(255) collate latin1_general_ci NOT NULL default '',
			  `expires` datetime NOT NULL default '0000-00-00 00:00:00',
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

			"CREATE VIEW person AS
			SELECT * from _person p
			WHERE
				getCurrentUserID() IS NOT NULL
				AND (
					(`p`.`id` = `getCurrentUserID`())
					OR (`getCurrentUserID`() = -(1))
					OR (
						(
						(not(exists(select 1 AS `Not_used` from `account_congregation_restriction` `cr` where (`cr`.`personid` = `getCurrentUserID`()))))
						OR `p`.`congregationid` in (select `cr`.`congregationid` AS `congregationid` from `account_congregation_restriction` `cr` where (`cr`.`personid` = `getCurrentUserID`()))
						)
						AND
						(
						(not(exists(select 1 AS `Not_used` from `account_group_restriction` `gr` where (`gr`.`personid` = `getCurrentUserID`()))))
						OR `p`.`id` in (select `m`.`personid` AS `personid` from (`person_group_membership` `m` join `account_group_restriction` `gr` on((`m`.`groupid` = `gr`.`groupid`))) where (`gr`.`personid` = `getCurrentUserID`()))
						)
					)
				);",

			"CREATE VIEW person_group AS
			SELECT * from _person_group g
			WHERE
			  getCurrentUserID() IS NOT NULL
			  AND
			  (NOT EXISTS (SELECT * FROM account_group_restriction gr WHERE gr.personid  = getCurrentUserID())
				   OR g.id IN (SELECT groupid FROM account_group_restriction gr WHERE gr.personid = getCurrentUserID()))",

			'CREATE VIEW member AS
			SELECT mp.id, mp.first_name, mp.last_name, mp.gender, mp.age_bracket, mp.congregationid,
			mp.email, mp.mobile_tel, mp.work_tel, mp.familyid,
			mf.family_name, mf.address_street, mf.address_suburb, mf.address_state, mf.address_postcode, mf.home_tel
			FROM _person mp
			JOIN family mf ON mf.id = mp.familyid
			JOIN person_group_membership pgm1 ON pgm1.personid = mp.id
			JOIN _person_group pg ON pg.id = pgm1.groupid AND pg.share_member_details = 1
			JOIN person_group_membership pgm2 ON pgm2.groupid = pg.id
			JOIN _person up ON up.id = pgm2.personid
			WHERE up.id = getCurrentUserID()
			   AND mp.status <> "archived"
			   AND mf.status <> "archived"
			   AND up.status <> "archived"	/* archived persons cannot see members of any group */

			UNION

			SELECT mp.id, mp.first_name, mp.last_name, mp.gender, mp.age_bracket, mp.congregationid,
			mp.email, mp.mobile_tel, mp.work_tel, mp.familyid,
			mf.family_name, mf.address_street, mf.address_suburb, mf.address_state, mf.address_postcode, mf.home_tel
			FROM _person mp
			JOIN family mf ON mf.id = mp.familyid
			JOIN _person self ON self.familyid = mp.familyid
			WHERE 
				self.id = getCurrentUserID()
				AND mp.status <> "archived"
				AND mf.status <> "archived"
				AND ((self.status <> "archived") OR (mp.id = self.id))
				/* archived persons can only see themselves, not any family members */
			;'
		);
		foreach ($sql as $s) {
			$r = $GLOBALS['db']->query($s);
			check_db_result($r);
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
				$r = $GLOBALS['db']->query($SQL);
				check_db_result($r);

			}
		}
	}


	function initInitialEntities()
	{
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
		$this->user->setValue('status', 0);
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
		$tables = $GLOBALS['db']->queryCol('SHOW TABLES');
		$routines = $GLOBALS['db']->queryCol('SHOW CREATE FUNCTION getCurrentUserID');
		if (!empty($tables) || !($routines instanceof MDB2_Error)) {
			print_message('Your MySQL database is not empty.  This could be due to a failed previous installation attempt.  Please drop and re-create the database to ensure it is entirely blank, then reload this page.', 'error');
			return;
		}
		?>
		<h2>Welcome</h2>
		<p>Welcome to the Jethro installer.  The installation process will set up your MySQL database so that <br />it's ready to run Jethro.  First we need to collect some details to get things started.</p>
		
		<form method="post">
			<h3>Initial User Account</h3>
			<p>Please enter the details of the first user you want to add to the system.  <br />This is the user as which you will initially log in.  <br />After you have logged in you can edit the rest of your details.</p>

			<table>
			<?php
			$sm = new Staff_Member();
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
			<p>Please enter the names of the congregations your church has.  These can be edited later under admin &gt; congregations.</p>
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