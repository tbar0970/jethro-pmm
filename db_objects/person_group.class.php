<?php
include_once 'include/db_object.class.php';
require_once 'include/data_permissions.php';
class Person_Group extends DB_Object
{
	protected $_save_permission_level = PERM_EDITGROUP;

	protected static function _getFields()
	{
		$fields = Array(
			'name'		=> Array(
									'type'		=> 'text',
									'width'		=> 40,
									'maxlength'	=> 128,
									'allow_empty'	=> FALSE,
									'initial_cap'	=> TRUE,
								   ),
			'categoryid'	=> Array(
									'type'	=> 'reference',
									'references' => 'person_group_category',
									'label' => 'Category',
									'allow_empty' => TRUE,
									'order_by' => 'name',
								),
			'is_archived'	=> Array(
									'type' => 'select',
									'options'	=> Array('Active', 'Archived'),
									'label' => 'Status',
									'default'	=> 0,
								),
			'owner'			=> Array(
									'type'			=> 'reference',
									'references'	=> 'staff_member',
									'label'			=> 'Visibility',
									'allow_empty'   => TRUE,
									'default'      => NULL,
								   ),
			'show_add_family'	=> Array(
									'type' => 'select',
									'options' => Array(
													'yes' => 'Yes',
													'no' => 'No',
												),
									'default' => 'no',
									'label' => 'Show on add-family page?',
									'note' => 'Should this group be shown as an option when <a href="?view=families__add">adding a new family</a>?',
									'divider_before' => true,
									'heading_before' => 'Advanced Options:',
									),
			'share_member_details' => Array(
									'type' => 'select',
									'options' => Array('No', 'Yes'),
									'default' => 0,
									'note' => 'If enabled, members can choose which contact info (name, email, mobile) they share with fellow members of this group via the <a href="'.BASE_URL.'/members">member portal</a>',
									'label' => 'Members may share their contact info?',
								),
		);
		// Check if attendance is enabled
		$enabled = explode(',', ifdef('ENABLED_FEATURES', 'ATTENDANCE'));
		if(in_array('ATTENDANCE', $enabled)){
			 $fields['attendance_recording_days']	= Array(
									'type'		=> 'bitmask',
									'options'	=> Array(
													1	=> 'Sunday',
													2	=> 'Monday',
													4	=> 'Tuesday',
													8	=> 'Wednesday',
													16	=> 'Thursday',
													32	=> 'Friday',
													64	=> 'Saturday',
									),
									'default'	=> 0,
									'label'		=> 'Attendance Recording Days',
									'cols'		=> 4,
									'note'		=> 'If you want to record attendance at this group, select the applicable weekdays. ',
									'show_unselected' => FALSE,
						   );
		}
		return $fields;
	}

	function __construct($id=NULL) {
		parent::__construct($id);

		if (!$this->id) {
			$this->fields['is_archived']['editable'] = false;
		}

		if (!empty($_REQUEST['categoryid'])) {
			$_SESSION['group_categoryid'] = $_REQUEST['categoryid'];
		} else if (empty($this->id) && !empty($_SESSION['group_categoryid'])) {
			$this->values['categoryid'] = array_get($_SESSION, 'group_categoryid');
		}
	}

	function getInitSQL($table_name=NULL)
	{
		// Need to create the group-membership table as well as the group table
		return Array(
				parent::getInitSQL('_person_group'),

				"CREATE TABLE person_group_membership_status (
					id INT AUTO_INCREMENT PRIMARY KEY,
					label VARCHAR(255) NOT NULL,
					`rank` int not null default 0,
					is_default TINYINT(1) UNSIGNED DEFAULT 0,
					CONSTRAINT UNIQUE INDEX (label)
				) ENGINE=InnoDB;",

				"INSERT INTO person_group_membership_status (label, is_default)
				VALUES ('Member', 1);",

				"CREATE TABLE `person_group_membership` (
				  `personid` int(11) NOT NULL default '0',
				  `groupid` int(11) NOT NULL default '0',
				  `membership_status` int NOT NULL,
				  `created` timestamp NOT NULL default CURRENT_TIMESTAMP,
				  PRIMARY KEY  (`personid`,`groupid`),
				  INDEX personid (personid),
				  INDEX groupid (groupid),
				  CONSTRAINT `membership_status_fk` FOREIGN KEY (membership_status) REFERENCES person_group_membership_status (id) ON DELETE RESTRICT,
				  CONSTRAINT `pgm_personid` FOREIGN KEY (personid) REFERENCES _person(id) ON DELETE CASCADE
				) ENGINE=InnoDB",

				// Per-person share consent for the members-area directory (#792).
				// data_permissions holds bits from include/data_permissions.php;
				// inherit_mask bit 1 = Visible follows the person's default row,
				// bit 2 = Share contact info follows it (a row with both bits set
				// is equivalent to no row and is never stored).  consented_on/
				// granted_by record when consent was given and whether the member or
				// staff set it, or 'upgrade' for consent carried over from the old
				// group-wide setting.  groupid 0 stores the person's DEFAULT
				// consent, applying to could-share groups where no explicit row
				// exists.  Rows are independent of membership: they survive members
				// leaving and rejoining a group.  (No FK on groupid: the default row
				// has no group to reference.)
				"CREATE TABLE _person_share_consent (
					personid INT NOT NULL,
					groupid INT NOT NULL,
					data_permissions INT UNSIGNED NOT NULL DEFAULT 0,
					inherit_mask TINYINT UNSIGNED NOT NULL DEFAULT 0,
					consented_on DATETIME NOT NULL,
					granted_by ENUM('user','staff','upgrade') NOT NULL,
					PRIMARY KEY (personid, groupid),
					CONSTRAINT psc_personid FOREIGN KEY (personid) REFERENCES _person(id) ON DELETE CASCADE
				) ENGINE=InnoDB",
		);
	}
	
	public function getForeignKeys()
	{
		return Array(
			'_person_group.categoryid' => "`person_group_category` (`id`) ON DELETE SET NULL",
		);
	}	


	/**
	 * Staff sessions (@current_user_id) see their own and shared groups, limited by
	 * account_group_restriction; members-area sessions (@current_member_id) see all
	 * shared (non-personal) groups, unaffected by staff restrictions.
	 * Keep in sync with upgrades/2026-upgrade-to-2.40.sql (person_group view).
	 * @see docs/docs/developer/reference/db-session-identity.md
	 * @return string The SQL to run to create any database views used by this class
	 */
	public function getViewSQL()
	{
		return "CREATE VIEW person_group AS
			SELECT * from _person_group g
			WHERE
			  (getCurrentMemberID() IS NOT NULL AND g.owner IS NULL)
			  OR (
			  getCurrentUserID() IS NOT NULL
			  AND
			  ((g.owner IS NULL) OR (g.owner = getCurrentUserID()))
			  AND
			  (NOT EXISTS (SELECT * FROM account_group_restriction gr WHERE gr.personid  = getCurrentUserID())
			  OR g.id IN (SELECT groupid FROM account_group_restriction gr WHERE gr.personid = getCurrentUserID()))
			  )";
	}


	function toString()
	{
		return $this->values['name'];
	}

	function getMembers($params=Array(), $order_by=NULL)
	{
		if ($order_by == NULL) {
			$order_by = 'pgms.`rank`, person.last_name, person.first_name';
		} else {
			// replace 'status' with membership status rank.
			// but retain 'person.status' unchanged.
			$order_by = preg_replace("/(^|[^.])status($| |,)/", '\\1pgms.`rank`\\2', $order_by);
		}
		$person = new Person();
		$comps = $person->getInstancesQueryComps($params, 'AND', $order_by);
		$comps['from'] .= '
			JOIN person_group_membership pgm ON pgm.personid = person.id
			LEFT JOIN person_group_membership_status pgms ON pgms.id = pgm.membership_status
			';
		if (strlen($comps['where'])) $comps['where'] .= ' AND ';
		$comps['where'] .= ' pgm.groupid = '.(int)$this->id;
		$comps['select'][] = 'pgm.membership_status as membership_status_id';
		$comps['select'][] = 'pgms.label as membership_status';
		$comps['select'][] = 'pgm.created as joined_group';
		return $person->_getInstancesData($comps);
	}

	function addMember($personid, $membership_status=NULL, $overwrite_existing=FALSE)
	{
		if (!$GLOBALS['user_system']->havePerm(PERM_EDITGROUP)) {
			trigger_error("You do not have permission to add group members");
			return FALSE;
		}
		list($statuses, $default_status) = self::getMembershipStatusOptionsAndDefault();
		if ($membership_status === NULL) $membership_status = $default_status;
		if (!isset($statuses[$membership_status])) {
			throw new \RuntimeException("Invalid membership status value '$membership_status'");
			return FALSE;
		}

		$new_member = $GLOBALS['system']->getDBObject('person', $personid);
		if ($new_member && $new_member->id) {
			$db =& $GLOBALS['db'];
			if ($overwrite_existing) {
				$sql = 'INSERT ';
			} else {
				$sql = 'INSERT IGNORE ';
			}
			$sql .= 'INTO person_group_membership (groupid, personid, membership_status)
					VALUES ('.$db->quote((int)$this->id).', '.$db->quote((int)$personid).', '.$db->quote($membership_status).')';
			if ($overwrite_existing) {
				$sql .= ' ON DUPLICATE KEY UPDATE membership_status=VALUES(membership_status)';
			}
			$res = $db->query($sql);
			return TRUE;
		}
		return FALSE;
	}

	function removeMember($personid)
	{
		if (!$GLOBALS['user_system']->havePerm(PERM_EDITGROUP)) {
			trigger_error("You do not have permission to remove group members");
			return FALSE;
		}
		$new_member = $GLOBALS['system']->getDBObject('person', $personid);
		if ($new_member->id) {
			$db =& $GLOBALS['db'];
			$sql = 'DELETE FROM person_group_membership
					WHERE groupid = '.$db->quote((int)$this->id).'
						AND personid = '.$db->quote((int)$personid);
			$res = $db->query($sql);
			return TRUE;
		}
		return FALSE;
	}

	function removeMembers($personids)
	{
		if (!$GLOBALS['user_system']->havePerm(PERM_EDITGROUP)) {
			trigger_error("You do not have permission to remove group members");
			return FALSE;
		}
		// Do a query first to make sure it's only persons we have access to.
		$members = $GLOBALS['system']->getDBObjectData('person', Array('id' => $personids));
		if ($members) {
			$db =& $GLOBALS['db'];
			$SQL = 'DELETE FROM person_group_membership
					WHERE groupid = '.$db->quote((int)$this->id).'
						AND personid IN ('.implode(',', array_map(Array($db, 'quote'), array_keys($members))).')';
			$res = $db->query($SQL);
			return TRUE;
		}
		return FALSE;
	}


	static function getGroups($personid, $includeArchived=FALSE, $whichShareMemberDetails=NULL)
	{
		$db =& $GLOBALS['db'];
		$sql = 'SELECT g.id, g.name, gm.created, g.is_archived, g.categoryid, g.share_member_details, pgms.label as membership_status, psc.data_permissions as share_data_permissions, psc.inherit_mask as share_inherit_mask, psc.consented_on as share_consented_on, psc.granted_by as share_granted_by, pscd.data_permissions as default_share_data_permissions, pscd.consented_on as default_consented_on, pscd.granted_by as default_granted_by
				FROM person_group_membership gm
				JOIN person_group g ON gm.groupid = g.id
				LEFT JOIN person_group_membership_status pgms ON pgms.id = gm.membership_status
				LEFT JOIN _person_share_consent psc ON psc.personid = gm.personid AND psc.groupid = gm.groupid
				LEFT JOIN _person_share_consent pscd ON pscd.personid = gm.personid AND pscd.groupid = 0
				WHERE gm.personid = '.$db->quote((int)$personid).'
				'.($includeArchived ? '' : ' AND NOT g.is_archived').'
				'.(is_null($whichShareMemberDetails) ? '' : ' AND g.share_member_details = '.(int)$whichShareMemberDetails).'
				ORDER BY g.is_archived ASC, g.name';
		$res = $db->queryAll($sql, null, null, true);
		return $res;
	}

	/**
	 * Record one person's directory-sharing consent for one group (#792).
	 * $visible lists the person (their name shows in the members area);
	 * $contact additionally shares their email/phone details.  Consent is
	 * stored per person+group in _person_share_consent and survives membership
	 * changes; the member view also requires current membership on both sides,
	 * so a lapsed member drops out of the directory until they rejoin.
	 *
	 * Member sessions may only set their own consent, and only for
	 * could-share groups they currently belong to (or their own default).
	 * Staff sessions need PERM_EDITGROUP (e.g. recording verbal consent for
	 * offline members).
	 *
	 * @param int  $personid
	 * @param int  $groupid
	 * @param bool $visible
	 * @param bool $contact
	 * @return bool
	 */
	static function setShareConsent($personid, $groupid, $visible, $contact)
	{
		$grantedBy = self::_consentWritePerm((int)$personid, (int)$groupid);
		if ($grantedBy === FALSE) return FALSE;
		$mask = ($visible ? DATA_PERM_VIEWNAME : 0)
			| ($contact ? DATA_PERM_VIEWEMAIL | DATA_PERM_VIEWMOBILE : 0);
		list($mask) = self::_contactImpliesName($mask, 0);
		if ($mask) {
			self::_writeShareConsentRow((int)$personid, (int)$groupid, $mask, 0, $grantedBy);
		} else {
			self::_deleteShareConsentRow((int)$personid, (int)$groupid);
		}
		return TRUE;
	}

	/**
	 * Apply the sharing form's checkbox states.  Each group row carries two
	 * independent columns - Share my Name (listed in the directory) and
	 * Share my Contact Info (email/phone shown with the name) - and each column can
	 * either hold an explicit choice or follow the person's default row.
	 * Clicking a column's "Use default" link marks that column as
	 * inheriting; a row with both columns inheriting is deleted, equivalent to
	 * having no row at all.
	 *
	 * Used by the members-area Sharing page and the staff person Sharing tab.
	 * Permissions are enforced per group by _consentWritePerm().
	 *
	 * @param int   $personid
	 * @param array $groups         Rows from getGroups() for this person
	 * @param array $visible        groupid => truthy (Visible checkbox submitted ticked)
	 * @param array $contact        groupid => truthy (Share contact info submitted ticked)
	 * @param array $inheritVisible groupid => truthy (Visible follows the default)
	 * @param array $inheritContact groupid => truthy (Share contact info follows the default)
	 * @param array $rendered       groupid => "mask:inherit_mask" as rendered; rows
	 *                              absent from this map were not shown in the form and
	 *                              are left untouched (e.g. a group joined between
	 *                              rendering and submission); rows whose submitted
	 *                              state is unchanged are also left alone, so a default
	 *                              changed in the same POST is not mistaken for an
	 *                              explicit per-group choice
	 * @return void
	 */
	static function applyShareConsentCheckboxes($personid, $groups, $visible, $contact, $inheritVisible = Array(), $inheritContact = Array(), $rendered = Array())
	{
		foreach ($groups as $groupid => $details) {
			if (empty($details['share_member_details'])) continue;
			if (!isset($rendered[$groupid])) continue;
			$grantedBy = self::_consentWritePerm((int)$personid, (int)$groupid);
			if ($grantedBy === FALSE) continue;
			$inh = (!empty($inheritVisible[$groupid]) ? 1 : 0)
				| (!empty($inheritContact[$groupid]) ? 2 : 0);
			$mask = (!empty($visible[$groupid]) ? 1 : 0)
				| (!empty($contact[$groupid]) ? DATA_PERM_VIEWEMAIL | DATA_PERM_VIEWMOBILE : 0);
			if (isset($rendered[$groupid]) && $mask.':'.$inh === (string)$rendered[$groupid]) continue;
			if ($inh == 3) {
				// Both columns follow the default: equivalent to no row.
				if ($details['share_data_permissions'] !== NULL) {
					self::_deleteShareConsentRow((int)$personid, (int)$groupid);
				}
				continue;
			}
			// Contact info implies the name is shared: apply that to the
			// effective state (including contact inherited from the default).
			list($mask, $inh) = self::_contactImpliesName($mask, $inh, (int)$details['default_share_data_permissions']);
			// Inherited columns store no explicit bits; the view reads the default.
			if ($inh & 1) $mask &= ~1;
			if ($inh & 2) $mask &= ~(DATA_PERM_VIEWEMAIL | DATA_PERM_VIEWMOBILE);
			self::_writeShareConsentRow((int)$personid, (int)$groupid, $mask, $inh, $grantedBy);
		}
	}

	/**
	 * Bulk equivalent for one group's member list (staff "Edit sharing
	 * settings").  That list exposes only the Visible (Name) column: ticking it
	 * keeps the member's effective contact consent and records the name grant,
	 * while clearing it records an explicit refusal for both columns (contact
	 * info cannot be shared without a name).  Contact consent is edited on the
	 * person's Sharing tab or by the member.
	 * @param int   $groupid
	 * @param array $personids Members shown in the form
	 * @param array $visible   personid => truthy (Visible checkbox submitted ticked)
	 * @param array $rendered  personid => effective mask when the form was rendered
	 * @return bool
	 */
	static function applyGroupShareConsentCheckboxes($groupid, $personids, $visible, $rendered = Array())
	{
		if (!$GLOBALS['user_system']->havePerm(PERM_EDITGROUP)) {
			trigger_error("You do not have permission to set sharing consent");
			return FALSE;
		}
		// Only rows this form rendered, and only members this staff session can
		// see: the person view applies group/congregation restrictions, so a
		// forged share_consent_all[] id cannot grant consent for someone the
		// user could not otherwise reach.
		$personids = array_values(array_intersect(
			array_map('intval', $personids),
			array_map('intval', array_keys($rendered))
		));
		if (!$personids) return TRUE;
		$allowed = $GLOBALS['db']->queryAll(
			'SELECT id FROM person
			 WHERE id IN ('.implode(',', $personids).')
			   AND id IN (SELECT personid FROM person_group_membership WHERE groupid = '.(int)$groupid.')',
			null, null, false
		);
		$allowed = array_map(function ($row) { return (int)$row['id']; }, $allowed);
		$consents = self::getShareConsents($groupid, $allowed);
		foreach ($allowed as $pid) {
			$explicit = array_key_exists($pid, $consents['group']) ? $consents['group'][$pid] : NULL;
			$default = array_key_exists($pid, $consents['default']) ? $consents['default'][$pid] : NULL;
			$mask = $explicit ? (int)$explicit['data_permissions'] : 0;
			$inh = $explicit ? (int)$explicit['inherit_mask'] : 3;
			if (!empty($visible[$pid])) {
				$targetMask = ($mask & (DATA_PERM_VIEWEMAIL | DATA_PERM_VIEWMOBILE)) | DATA_PERM_VIEWNAME;
				// The name becomes an explicit choice; a new row keeps contact
				// following the person's default.
				$targetInh = ($inh & ~1) | ($explicit ? 0 : 2);
			} else {
				$targetMask = 0;
				$targetInh = 0;
			}
			$defMask = $default ? (int)$default['data_permissions'] : 0;
			$targetEff = (($targetInh & 1) ? $defMask : $targetMask) & 1
				| ((($targetInh & 2) ? $defMask : $targetMask) & (DATA_PERM_VIEWEMAIL | DATA_PERM_VIEWMOBILE));
			// The form renders the effective state; unchanged rows are left alone.
			if ($targetEff == (int)$rendered[$pid]) continue;
			self::_writeShareConsentRow($pid, (int)$groupid, $targetMask, $targetInh, 'staff');
		}
		return TRUE;
	}


	/**
	 * @return string|FALSE 'user' or 'staff' if the current session may set
	 * consent for this person+group, else FALSE
	 */
	private static function _consentWritePerm($personid, $groupid)
	{
		$user_system = $GLOBALS['user_system'];
		// getCurrentMember() exists on both user systems (it returns NULL for
		// staff sessions), so detect a members-area session by an actual
		// logged-in member rather than by method existence.
		$memberid = (int)$user_system->getCurrentMember('id');
		if ($memberid) {
			if ($memberid !== $personid) {
				trigger_error("Members may only change their own sharing consent");
				return FALSE;
			}
			if ($groupid != 0) {
				// Groupid 0 is the person's default row: it needs no membership.
				$groups = self::getGroups($memberid);
				if (empty($groups[$groupid]) || empty($groups[$groupid]['share_member_details'])) {
					trigger_error("Sharing consent can only be set for groups you belong to");
					return FALSE;
				}
			}
			return 'user';
		}
		if (!$user_system->havePerm(PERM_EDITGROUP)) {
			trigger_error("You do not have permission to set sharing consent");
			return FALSE;
		}
		return 'staff';
	}

	/**
	 * Contact info implies the name is shared: normalise a consent mask and
	 * inherit mask so a contact grant also lists the person, and a contact
	 * grant inherited from the default cannot leave Name inheriting a
	 * name-less value.
	 * @param int $mask
	 * @param int $inheritMask
	 * @param int $defaultMask Default-row mask, read for inherited contact bits
	 * @return array{0:int,1:int} [mask, inheritMask]
	 */
	private static function _contactImpliesName($mask, $inheritMask, $defaultMask = 0)
	{
		$contactBits = DATA_PERM_VIEWEMAIL | DATA_PERM_VIEWMOBILE;
		$effectiveContact = (($inheritMask & 2) ? $defaultMask : $mask) & $contactBits;
		if ($effectiveContact) {
			$mask |= 1;
			$inheritMask &= ~1;
		}
		return Array((int)$mask, (int)$inheritMask);
	}

	private static function _writeShareConsentRow($personid, $groupid, $mask, $inheritMask, $grantedBy)
	{
		$db =& $GLOBALS['db'];
		$db->query('INSERT INTO _person_share_consent
					(personid, groupid, data_permissions, inherit_mask, consented_on, granted_by)
					VALUES ('.(int)$personid.', '.(int)$groupid.', '.(int)$mask.', '.(int)$inheritMask.', NOW(), '.$db->quote($grantedBy).')
					ON DUPLICATE KEY UPDATE data_permissions = '.(int)$mask.',
					inherit_mask = '.(int)$inheritMask.',
					consented_on = NOW(), granted_by = '.$db->quote($grantedBy));
	}

	private static function _deleteShareConsentRow($personid, $groupid)
	{
		$GLOBALS['db']->query('DELETE FROM _person_share_consent
								WHERE personid = '.(int)$personid.' AND groupid = '.(int)$groupid);
	}

	/**
	 * Get one person's default consent row (groupid 0), if any.
	 * @param int $personid
	 * @return array|NULL Row with data_permissions, consented_on, granted_by
	 */
	static function getDefaultShareConsent($personid)
	{
		$rows = $GLOBALS['db']->queryAll('SELECT data_permissions, consented_on, granted_by
										FROM _person_share_consent
										WHERE personid = '.(int)$personid.' AND groupid = 0', null, null, false);
		return empty($rows) ? NULL : $rows[0];
	}

	/**
	 * Consent records for one group's members, plus each person's default row
	 * (groupid 0) so callers can resolve effective consent: the group row where
	 * one exists, otherwise the default.
	 * @param int $groupid
	 * @param array $personids Optional person ids to constrain the lookup
	 * @return array{group: array<int,array>, default: array<int,array>} personid => row
	 */
	static function getShareConsents($groupid, $personids = NULL)
	{
		$personSql = '';
		if ($personids) {
			$personSql = ' AND personid IN ('.implode(',', array_map('intval', $personids)).')';
		}
		$res = Array('group' => Array(), 'default' => Array());
		foreach ($GLOBALS['db']->queryAll('SELECT personid, groupid, data_permissions, inherit_mask, consented_on, granted_by
										FROM _person_share_consent
										WHERE (groupid = '.(int)$groupid.' OR groupid = 0)'.$personSql, null, null, false) as $row) {
			if ($row['groupid'] == 0) {
				$res['default'][(int)$row['personid']] = $row;
			} else {
				$res['group'][(int)$row['personid']] = $row;
			}
		}
		return $res;
	}

	function printSummary()
	{
		?>
		<table class="standard">
			<tr>
				<th>Group Name</th>
				<td><?php echo $this->getValue('name'); ?></td>
			</tr>
			<tr>
				<th>Members</th>
				<td>
					<ul>
					<?php
					foreach ($this->getMembers() as $id => $details) {
						?>
						<li><a href="?view=persons&personid=<?php echo $id; ?>"><?php echo $details['first_name'].' '.$details['last_name']; ?></a></li>
						<?php
					}
					?>
					</ul>
				</td>
			</tr>
		</table>
		<?php
	}


	function getInstancesQueryComps($params, $logic, $order)
	{
		$res = parent::getInstancesQueryComps($params, $logic, $order);
		$res['from'] .= "\n LEFT JOIN person_group_category pgc ON person_group.categoryid = pgc.id ";
		$res['select'][] = 'pgc.name as category';

		// TODO: we don't need to join this all the time, only for the groups list all page
		$res['from'] .= "\n LEFT JOIN person_group_membership gm ON gm.groupid = person_group.id ";
		$res['from'] .= "\n LEFT JOIN person aperson ON gm.personid = aperson.id AND aperson.status NOT IN (SELECT id FROM person_status WHERE is_archived)";
		$res['select'][] = 'COUNT(aperson.id) as member_count';
		$res['group_by'] = 'person_group.id';
		return $res;

	}

	function delete()
	{
		$SQL = 'SELECT COUNT(*) FROM account_group_restriction WHERE groupid = '.(int)$this->id;
		$res = $GLOBALS['db']->queryOne($SQL);
		if ($res > 0) {
			add_message("This group cannot be deleted because it is used to restrict one or more user accounts", 'error');
			return FALSE;
		}

		$roles = $GLOBALS['system']->getDBObjectData('roster_role', Array('volunteer_group' => $this->id));
		if ($roles) {
			$role = reset($roles);
			add_message("This group cannot be deleted because it used by the roster role '".$role['title']."'", 'error');
			return FALSE;
		}

		$r = parent::delete();
		$db =& $GLOBALS['db'];
		$sql = 'DELETE FROM person_group_membership WHERE groupid = '.$db->quote($this->id);
		$res = $db->query($sql);
		return $r;
	}

	function printFieldValue($fieldname, $value=NULL)
	{
		if (is_null($value)) $value = $this->values[$fieldname];
		switch ($fieldname) {
			case 'attendance_recording_days':
				if ($value == 0) {
					echo _('No');
					return;
				}
				if ($value == 127) {
					echo _('Yes, any day');
					return;
				}
				return parent::printFieldValue($fieldname, $value);
				break;
			case 'owner':
				echo _(($value === NULL) ? 'Everyone' : 'Only me');
				break;

			case 'categoryid':
				if ($value == 0) {
					echo '<i>(Uncategorised)</i>';
					return;
				}
				// deliberate fall through
			default:
				return parent::printFieldValue($fieldname, $value);
		}
	}

	function printFieldInterface($fieldname, $prefix='')
	{
		switch ($fieldname) {
			case 'categoryid':
				$GLOBALS['system']->includeDBClass('person_group_category');
				Person_Group_Category::printChooser($prefix.$fieldname, $this->getValue('categoryid'));
				echo ' &nbsp; &nbsp;<small><a href="'.build_url(Array('view' => 'groups__manage_categories')).'">Manage categories</a></small>';
				break;
			case 'owner':
				$visibilityParams = Array(
					'type' => 'select',
					'options' => Array('Visible to everyone', 'Visible only to me')
				);
				print_widget('is_private', $visibilityParams, $this->getValue('owner') !== NULL);
				break;
			default:
				return parent::printFieldInterface($fieldname, $prefix);
		}
	}

	public function processFieldInterface($name, $prefix='')
	{
		switch ($name) {
			case 'owner':
				$this->setValue('owner', empty($_REQUEST['is_private']) ? NULL : $GLOBALS['user_system']->getCurrentUser('id'));
				break;
			default:
				return parent::processFieldInterface($name, $prefix);
		}
	}

	public static function getMembershipStatusOptionsAndDefault($with_usages = FALSE)
	{
		if ($with_usages) {
			$sql = 'SELECT s.*, COUNT(pgm.personid) as usages
					FROM person_group_membership_status s
					LEFT JOIN person_group_membership pgm ON pgm.membership_status = s.id
					ORDER BY s.`rank`';
		} else {
			$sql = 'SELECT s.*
					FROM person_group_membership_status s
					ORDER BY s.`rank`';
		}
		$res = $GLOBALS['db']->queryAll($sql, null, null, true);
		$options = Array();
		$default = null;
		$usages = Array();
		foreach ($res as $id => $detail) {
			$options[$id] = $detail['label'];
			if ($with_usages) $usages[$id] = $detail['usages'];
			if ($detail['is_default']) $default = $id;
		}
		if (empty($default)) $default = key($options);
		return Array($options, $default, $usages);
	}


	public static function printMembershipStatusChooser($name, $value=NULL, $multi=FALSE)
	{
		list($options, $default) = self::getMembershipStatusOptionsAndDefault();
		$params = Array(
			'type' => 'select',
			'options' => $options,
		);
		if (empty($value)) $value = $default;
		if ($multi) {
			$params['allow_multiple'] = true;
			if (substr($name, -2) != '[]') $name .= '[]';
		}
		print_widget($name, $params, $value);
	}


	public function updateMembershipStatuses($vals)
	{
		$GLOBALS['system']->doTransaction('BEGIN');
		list($options, $default) = self::getMembershipStatusOptionsAndDefault();
		foreach ($vals as $personid => $status) {
			if (!isset($options[$status])) {
				trigger_error("Invalid person status $status not saved");
				continue;
			}
			$res = $GLOBALS['db']->query('UPDATE person_group_membership
										SET membership_status = '.$GLOBALS['db']->quote($status).'
										WHERE groupid = '.(int)$this->id.'
											AND personid = '.(int)$personid);
		}
		$GLOBALS['system']->doTransaction('COMMIT');
		return TRUE;
	}

	static function printMultiChooser($name, $value, $exclude_groups=Array(), $allow_category_select=FALSE)
	{
		static $gotGroups = NULL;
		static $groupsTreeCache = Array(0 => NULL, 1 => NULL);
		if ($groupsTreeCache[(int)$allow_category_select] === NULL) {
			// Save the groups tree in a JS variable for the treeselect to use
			$cats = $GLOBALS['system']->getDBObjectData('person_group_category', Array(), 'OR', 'name');
			$cats[0] = Array('name' => 'Uncategorised groups', 'parent_category' => NULL);
			$groups = $GLOBALS['system']->getDBObjectData('person_group', Array('is_archived' => 0, 'id' => $value), 'OR', 'name, person_group.id');
			$groupsTreeCache[(int)$allow_category_select] = self::_getGroupTree($cats, $groups, $allow_category_select);
			$gotGroups = !empty($groups);
			?>
			<script>JethroGroupChooser.groupTrees[<?php echo (int)$allow_category_select; ?>] = <?php echo json_encode($groupsTreeCache[$allow_category_select]); ?>;</script>
			<?php
		}
		if (!$gotGroups) {
			?><i>There are no groups in the system yet</i> &nbsp;<?php
			return FALSE;
		}


		$chosen = Array();
		if (count(array_filter($value)) == 0) {
			$chosen = Array('' => Array('name' => '--Choose--'));
		} else {
			// For each of our selected groups/categories, we print a select box contining a single option.
			// The treeselect JS handles the rest.
			// There's a bit of faffing to look up names of selected groups AND categories, intermingled.
			$chosen = array_flip(array_filter($value)); // got our array keys in place...
			$catids = $groupids = Array();
			foreach ($chosen as $val => $x) {
				if (is_string($val) && ($val[0] == 'c')) {
					$catids[] = substr($val, 1);
				} else {
					$groupids[] = $val;
				}
			}
			$allData = $GLOBALS['system']->getDBOBjectData('person_group', Array('(id'=>$groupids));
			$allData['c0'] = Array('name' => 'Uncategorised Groups (all)');
			foreach ($GLOBALS['system']->getDBOBjectData('person_group_category', Array('(id'=>$catids)) as $k => $v) {
				$allData['c'.$k] = $v;
			}
			foreach ($chosen as $val => $x) {
				$deletedLabel = (is_string($val) && ($val[0] == 'c')) ? '(deleted category)' : '(deleted group)';
				$chosen[$val] = array_get($allData, $val, Array('name' => $deletedLabel));
			}
		}

		?>
		<div class="dropdown group-chooser-container">
			<div class="group-chooser-multi" data-allow-category-select="<?php echo (int)$allow_category_select; ?>" id="<?php echo ents($name); ?>">
			<?php
			foreach ($chosen as $gid => $gdetail) {
				?>
				<select name="<?php echo ents($name);?>[]">
					<option selected value="<?php echo ents($gid); ?>">
						<?php echo ents($gdetail['name']); ?>
					</option>
				</select>
				<?php
			}
			?>
			</div>
			<div class="custom-treeview-wrapper" id="<?php echo ents($name); ?>-container"></div>
		</div>
		<?php
		return TRUE;
	}


	static function printChooser($fieldname, $value, $empty_text='(Choose)')
	{
		static $groups = NULL;
		static $gotGroups = NULL;
		static $groupsTreeCache = Array(0 => NULL, 1 => NULL);
		if ($groupsTreeCache[0] === NULL) {
			// Save the groups tree in a JS variable for the treeselect to use
			$cats = $GLOBALS['system']->getDBObjectData('person_group_category', Array(), 'OR', 'name');
			$cats[0] = Array('name' => 'Uncategorised groups', 'parent_category' => NULL);
			$groups = $GLOBALS['system']->getDBObjectData('person_group', Array('is_archived' => 0, 'id' => $value), 'OR', 'name, person_group.id');
			$groupsTreeCache[0] = self::_getGroupTree($cats, $groups, 0);
			$gotGroups = !empty($groups);
			?>
			<script>JethroGroupChooser.groupTrees[0] = <?php echo json_encode($groupsTreeCache[0]); ?>;</script>
			<?php
		}

		if (!$gotGroups) {
			?><i>There are no groups in the system yet</i> &nbsp;<?php
			return FALSE;
		}

		?>
		<span class="dropdown group-chooser-container">
		<select class="group-chooser" name="<?php echo ents($fieldname);?>" data-allow-category-select="0">
			<option selected value="<?php echo (int)$value; ?>">
				<?php 
				if ($value) {
					if (isset($groups[$value])) {
						echo ents($groups[$value]['name']);
					} else {
						echo ents('!! Error: Group #'.(int)$value." not found");
					}
				} else {
					echo ents($empty_text);
				}
				?>
			</option>
		</select>
		<div class="custom-treeview-wrapper" id=""></div>
		</span>
		<?php
		return TRUE;
	}

	private static function _getGroupTree($cats, $groups, $allow_category_select, $parentcatid=NULL)
	{
		$res = Array();
		foreach ($cats as $cid => $cat) {
			if ($cat['parent_category'] !== $parentcatid) continue;
			$entry = Array(
				'id' => 'c'.$cid,
				'name' => $cat['name'].($allow_category_select ? ' (ALL)' : ''),
				'selectable' => (bool)$allow_category_select,
				'children' => self::_getGroupTree($cats, $groups, $allow_category_select, $cid)
			);
			foreach ($groups as $gid => $group) {
				if ($group['categoryid'] != $cid) continue;
				$entry['children'][] = Array(
					'id' => $gid,
					'name' => $group['name'],
				);
			}
			$res[] = $entry;
		}
		return $res;
	}

	public function canRecordAttendanceOn($date)
	{
		$testIndex = array_search(date('l', strtotime($date)), $this->fields['attendance_recording_days']['options']);
		return $testIndex & $this->getValue('attendance_recording_days');

	}

	/**
	 * If there is exactly one group whose name matches, return the group object.
	 * Matching is done case-insensitively, and considering spaces and underscores as the same.
	 * @param string $name
	 */
	public static function findByName($name)
	{
		static $warnings = Array();
		$name = str_replace(' ', '_', strtolower($name));
		$SQL = 'SELECT * from person_group WHERE REPLACE(LOWER(name), " ", "_") = '.$GLOBALS['db']->quote($name);
		$res = $GLOBALS['db']->queryAll($SQL, NULL, NULL, TRUE);
		if (count($res) > 1) {
			if (empty($warnings[$name])) {
				add_message("Could not match a single group called \"".$name.'" - there are several groups with that name', 'warning');
				$warnings[$name] = TRUE;
			}
		}
		if (count($res) == 1) {
			$g = new Person_Group();
			$g->populate(key($res), reset($res));
			return $g;
		}
		return NULL;
	}





}
