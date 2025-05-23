<?php
include_once 'include/db_object.class.php';
include_once 'include/size_detector.class.php';
class Person extends DB_Object
{
	protected $_save_permission_level = PERM_EDITPERSON;
	private $_photo_data = NULL;
	private $_custom_values = Array();
	private $_old_custom_values = Array();

	function __construct($id=0)
	{
		if ($id == $GLOBALS['user_system']->getCurrentPerson('id')) {
			// Every person can save their own details
			$this->_save_permission_level = 0;
		}
		return parent::__construct($id);
	}

	static function allowedToAdd()
	{
		$restrictions = $GLOBALS['user_system']->getCurrentRestrictions();
		if (!empty($restrictions['group'])) return FALSE;
		if (!empty($restrictions['congregation'])) {
			return (defined('RESTRICTED_USERS_CAN_ADD') && RESTRICTED_USERS_CAN_ADD);
		}
		return TRUE;
	}

	public static function getStatusOptions()
	{
		static $res = NULL;
		if ($res === NULL) {
			$db = JethroDB::get();
			$res = $db->queryAll('SELECT id, label FROM person_status ORDER BY `rank`', NULL, NULL, true);
		}
		return $res;
	}

	protected static function _getFields()
	{
		$allowEmptyCong = TRUE;
		if ($GLOBALS['user_system']->getCurrentRestrictions('congregation')) {
			$allowEmptyCong = FALSE; // Can only add to congs we can see.
		}
		$res = Array(
			'first_name'	=> Array(
									'type'		=> 'text',
									'width'		=> 30,
									'maxlength'	=> 128,
									'allow_empty'	=> false,
									'initial_cap_singleword'	=> true,
									'trim'			=> TRUE,
								   ),
			'last_name'		=> Array(
									'type'		=> 'text',
									'width'		=> 30,
									'maxlength'	=> 128,
									'allow_empty'	=> false,
									'initial_cap_singleword'	=> true,
									'trim'			=> TRUE,
								   ),
			'gender'		=> Array(
									'type'			=> 'select',
									'options'		=> Array('female' => 'Female', 'male' => 'Male', '' => 'Unknown'),
									'default'		=> 'female',
									'divider_before'	=> true,
							   ),
			'age_bracketid'	=> Array(
									'type'			=> 'reference',
									'references'    => 'age_bracket',
									'allow_empty'	=> false,
									'label'         => 'Age bracket',
									'show_id'		=> false,
							   ),
			'familyid'	=> Array(
								'divider_before' => true,
								'type'	=> 'reference',
								'references'	=> 'family',
								'label'			=> 'Family',
								'show_in_summary'	=> false,
						   ),
			'congregationid'	=> Array(
									'type'				=> 'reference',
									'references'		=> 'congregation',
									'filter'			=> Array('holds_persons' => 1),
									'order_by'			=> 'name',
									'label'				=> 'Congregation',
									'show_id'			=> FALSE,
									'allow_empty'		=> $allowEmptyCong,
									'class'				=> 'person-congregation',
							   ),
			'status'	=> Array(
								'type'	=> 'reference',
								'references' => 'person_status',
								'class'		=> 'person-status',
								'filter'	=> Array('active' => 1),
								'allow_empty'	=> false,
								'default'	=> '', // the widget will use the option marked is_default
								'show_id'		=> false,
						   ),
			'email'			=> Array(
									'divider_before' => true,
									'type'		=> 'email',
									'width'		=> 40,
									'maxlength'	=> 255,
								   ),
			'mobile_tel'	=> Array(
									'type'			=> 'phone',
									'formats'		=> ifdef('MOBILE_TEL_FORMATS', ''),
									'allow_empty'	=> TRUE,
								   ),
			'work_tel'	=> Array(
									'type'			=> 'phone',
									'formats'		=> ifdef('WORK_TEL_FORMATS', ''),
									'allow_empty'	=> TRUE,
								),
			'remarks'	=> Array(
									'type'		=> 'text',
									'width'		=> 40,
									'height'	=> 2,
									'maxlength'	=> 255,
									'label'		=> SizeDetector::isNarrow() ? 'Remarks' : 'Contact Remarks',
									'initial_cap'	=> true,
							),
			'status_last_changed' => Array(
									'type'			=> 'datetime',
									'show_in_summary' => false,
									'allow_empty'	=> TRUE,
									'editable'			=> false,
									'label' => 'Date of last status change',
								   ),
			'created'			=> Array(
									'type'			=> 'datetime',
									'readonly'		=> true,
									'show_in_summary'	=> false,
									'editable'			=> false,
									'label' => 'Created Date',
								   ),
			'creator'			=> Array(
									'type'			=> 'reference',
									'editable'		=> false,
									'references'	=> 'staff_member',
									'show_in_summary'	=> false,
								   ),
			'history'			=> Array(
									'type'			=> 'serialise',
									'editable'		=> false,
									'show_in_summary'	=> false,

								   ),
			'feed_uuid'				=> Array(
									'type' => 'text',
									'editable'		=> false,
									'show_in_summary'	=> false,
									),

		);
		foreach ($res as $k => $v) {
			if ($label = ifdef('PERSON_'.strtoupper($k).'_LABEL')) {
				$res[$k]['label'] = $label;
			}
		}
		return $res;
	}

	function getInitSQL($table_name=NULL)
	{
		return Array(
			"CREATE TABLE `_person` (
			  `id` int(11) NOT NULL auto_increment,
			  `first_name` varchar(255) NOT NULL default '',
			  `last_name` varchar(255) NOT NULL default '',
			  `gender` varchar(64) NOT NULL default '',
			  `age_bracketid` INT(11) DEFAULT NULL,
			  `email` varchar(255) NOT NULL default '',
			  `mobile_tel` varchar(12) NOT NULL default '',
			  `work_tel` varchar(12) NOT NULL default '',
			  `remarks` text NOT NULL,
			  `status` int(11) NOT NULL,
			  `status_last_changed` datetime NULL default NULL,
			  `history` text NOT NULL,
			  `creator` int(11) NOT NULL default '0',
			  `created` timestamp NOT NULL default CURRENT_TIMESTAMP,
			  `congregationid` int(11) default NULL,
			  `familyid` int(11) NOT NULL default '0',
			  `member_password` VARCHAR(255) DEFAULT NULL,
			  `resethash` VARCHAR(255) DEFAULT NULL,
			  `resetexpires` DATETIME DEFAULT NULL,
			  `feed_uuid` VARCHAR(255) DEFAULT NULL,
			  INDEX `person_fn` (`first_name`),
			  INDEX `person_ln` (`last_name`),
			  PRIMARY KEY  (`id`)
			) ENGINE=InnoDB ;",

			"CREATE TABLE person_photo (
			   personid int(11) not null,
			   photodata mediumblob not null,
			   PRIMARY KEY (personid),
			   CONSTRAINT photo_personid FOREIGN KEY (`personid`) REFERENCES `_person` (`id`) ON DELETE CASCADE
			) ENGINE=InnoDB",
		);
	}

	/**
	 *
	 * @return The SQL to run to create any database views used by this class
	 */
	public function getViewSQL()
	{
		return "
			CREATE VIEW person AS
			SELECT * from _person p
			WHERE
				getCurrentUserID() IS NOT NULL
				AND (
					/* the person in question IS the current user */
					(`p`.`id` = `getCurrentUserID`()) 

					OR /* we've been set to public mode */					
					(`getCurrentUserID`() = -(1))  

					OR /* current user has no group/cong restrictions */
					((0 = (select count(congregationid) from account_congregation_restriction cr WHERE cr.personid = getCurrentUserID()))
					   AND (0 = (select count(gr.groupid) from account_group_restriction gr WHERE gr.personid = getCurrentUserID())))

					OR /* person is within a permitted cong */
					(`p`.`congregationid` in (select `cr`.`congregationid` AS `congregationid` from `account_congregation_restriction` `cr` where (`cr`.`personid` = `getCurrentUserID`())))

					OR
					/* person is within a permitted group */
					(`p`.`id` in (select `m`.`personid` AS `personid` from (`person_group_membership` `m` join `account_group_restriction` `gr` on((`m`.`groupid` = `gr`.`groupid`))) where (`gr`.`personid` = `getCurrentUserID`())))
				)
		";
	}
	
	/*
	 * Get the name of the table that objects should be INSERTed into.
	 * This can be overridden if the normal table is actually a view.
	 */
	protected function _getInsertTableName()
	{
		return '_person';
	}	

	/**
	 *
	 * @return Array (columnName => referenceExpression) eg 'tagid' => 'tagoption(id) ON DELETE CASCADE'
	 */
	public function getForeignKeys()
	{
		return Array(
				'_person.age_bracketid' => '`age_bracket`(`id`) ON DELETE RESTRICT',
				'_person.status' => '`person_status`(`id`) ON DELETE RESTRICT',
				'_person.familyid' => '`family`(`id`) ON DELETE RESTRICT',
				'_person.congregationid' => '`congregation`(`id`) ON DELETE RESTRICT',
		);
	}

	public function load($id) {
		parent::load($id);

		// Load custom values
		$SQL = 'SELECT v.fieldid, '.Custom_Field::getRawValueSQLExpr('v', 'f').' as value
				FROM custom_field_value v
				JOIN custom_field f ON v.fieldid = f.id
				WHERE personid = '.(int)$this->id;
		$res = $GLOBALS['db']->queryAll($SQL, NULL, NULL, true, FALSE, TRUE);
		$this->_custom_values = $res;
	}

	function toString()
	{
		return $this->values['first_name'].' '.$this->values['last_name'];
	}


	function printFieldValue($name, $value=NULL)
	{
		if (is_null($value)) $value = $this->getValue($name);
		$person_name = ents($this->getValue('first_name')).'&nbsp;'.ents($this->getValue('last_name'));
		switch ($name) {
			case 'name':
				echo $person_name;
				return;
			case 'mobile_tel':
				if (!strlen($value)) return;
				$links = Array('<a href="tel:'.ents($value).'"><i class="icon-phone"></i> Call</a>');
				if (SMS_Sender::canSend()) {
					$msg = _('SMS via Jethro');
					$links[] = '<a href="#send-sms-modal" data-toggle="sms-modal" data-personid="' . $this->id . '" data-name="' . $person_name . '"><i class="icon-envelope"></i> '.$msg.'</a>';
					static $printedModal = FALSE;
					if (!$printedModal) {
						SMS_Sender::printModal();
						$printedModal = TRUE;
					}
				}
				if (FALSE !== strpos($_SERVER['HTTP_USER_AGENT'], 'Macintosh')) {
					// on mac we can use the messages app
					$msg = _('SMS via iMessage');
					$links[] = '<a href="imessage:'.ents($value).'"><i class="icon-envelope"></i> '.$msg.'</a>';
				} else if (SizeDetector::isNarrow()) {
					// Probably a phone - use SMS link
					$msg = SMS_Sender::canSend() ? 'SMS via my device' : 'SMS';
					$links[] = '<a href="sms:'.ents($value).'"><i class="icon-envelope"></i> '.$msg.'</a>';
				}
				$internationalNumber = preg_replace('/[^0-9]/', '', SMS_INTERNATIONAL_PREFIX).substr($value, strlen(SMS_LOCAL_PREFIX));
				$links[] = '<a href="https://wa.me/'.$internationalNumber.'" target="_whatsapp"><i class="icon-comment"></i> Send WhatsApp</a>';
				$links[] = '<a data-action="copy" data-target="#mobile-'.$this->id.'"><i class="icon-copy"></i> Copy number</a>';

				?>
				<span class="dropdown nowrap">
					<a class="dropdown-toggle mobile-tel" id="mobile-<?php echo $this->id; ?>" data-toggle="dropdown" href="#"><?php echo ents($this->getFormattedValue('mobile_tel')); ?></a>
					<ul class="dropdown-menu" role="menu" aria-labelledby="mobile-<?php echo $this->id; ?>" style="z-index:9999">
					<?php
					foreach ($links as $l) {
						?>
						<li><?php echo $l; ?></li>
						<?php
					}
					?>
					</ul>
				</span>
				<?php
				return;
			default:
				parent::printFieldValue($name, $value);

		}

	}

	protected function _printSummaryRows() {
		parent::_printSummaryRows();
		$wrapwidth = SizeDetector::isNarrow() ? 23 : 35;

		// care is needed here, because we don't print empty fields
		// but we still want to (potentially) print their headings and dividers.
		$showDivider = TRUE; // always show before first field
		$showHeading = '';
		$dummyField = new Custom_Field();
		foreach (self::getCustomFields() as $fieldid => $fieldDetails) {
			$dummyField->populate($fieldid, $fieldDetails);
			if ($fieldDetails['divider_before']) {
				$showDivider = TRUE;
			}
			if (strlen($fieldDetails['heading_before'])) {
				$showHeading = $fieldDetails['heading_before'];
			}
			if (isset($this->_custom_values[$fieldid])) {
				if ($showHeading) {
					?>
					<tr
						<?php
						if ($showDivider) echo 'class="divider-before"';
						?>
					>
						<th colspan="2" class="center">
							<h4><?php echo ents($showHeading); ?></h4>
						</th>
					</tr>
					<?php
					$showDivider = FALSE;
				}
				?>
				<tr
					<?php
					if ($showDivider) echo 'class="divider-before"';
					?>
				>
					<th><?php echo wordwrap(ents($fieldDetails['name']), $wrapwidth, '<br />'); ?></th>
					<td>
						<?php
						foreach ((array)$this->_custom_values[$fieldid] as $j => $val) {
							if ($j > 0) echo '<br />';
							$dummyField->printFormattedValue($val);
						}
						?>
					</td>
				</tr>
				<?php
				$showDivider = FALSE;
				$showHeading = '';
			}
		}
	}


	function getNotesHistory()
	{
		$family_notes = $GLOBALS['system']->getDBObjectData('family_note', Array('familyid' => $this->getValue('familyid')));
		$person_notes = $GLOBALS['system']->getDBObjectData('person_note', Array('personid' => $this->id));
		$all_notes = $family_notes + $person_notes;
		ksort($all_notes);
		if (ifdef('NOTES_ORDER', 'ASC') != 'ASC') {
			$all_notes = array_reverse($all_notes, TRUE);
		}
		return $all_notes;
	}

	function validateFields()
	{
		if (!parent::validateFields()) return FALSE;
		if (empty($this->values['congregationid'])) {
			$stats = Person_Status::getActive();
			if ($stats[$this->values['status']]['require_congregation']) {
				trigger_error('Persons with status '.$this->getFormattedValue('status').' cannot have a blank congregation');
				return FALSE;
			}
		}
		return TRUE;
	}

	function hasAttendance()
	{
		$SQL = 'SELECT count(*) FROM attendance_record
				WHERE personid = '.(int)$this->id;
		return $GLOBALS['db']->queryOne($SQL);
	}

	/**
	 * Returns TRUE if this person has a registered member account,
	 * FALSE if no account at all,
	 * or a datetime string (expiry date) if they are part way through the rego process
	 */
	public function hasMemberAccount()
	{
		$SQL = 'SELECT LENGTH(member_password) as gotpassword, IF(resetexpires > NOW(), resetexpires, NULL) as resetexpires FROM person
				WHERE id = '.(int)$this->id;
		$row = $GLOBALS['db']->queryRow($SQL);
		return $row['gotpassword'] ? TRUE : ($row['resetexpires'] ? $row['resetexpires'] : FALSE);
	}


	function getAttendance($from='1970-01-01', $to='2999-01-01', $groupid=-1)
	{
		$db =& $GLOBALS['db'];
		$datesSQL = '
			SELECT groupid, `date` FROM attendance_record
			WHERE date BETWEEN '.$db->quote($from).' AND '.$db->quote($to).'
		';
		if ($groupid != -1) {
			$datesSQL .= ' AND groupid = '.(int)$groupid;
		} else {
			$datesSQL .= ' AND ((groupid = 0)
								OR (groupid IN (
									select groupid from person_group_membership
									WHERE personid ='.$db->quote($this->id).'
								))
							)';

		}
		$datesSQL .= '
			GROUP BY groupid, `date`';

		$sql = 'SELECT g.id, recorded.`date`, present
				FROM (
					'.$datesSQL.'
					) recorded
					LEFT JOIN attendance_record ar
						ON ar.`date` = recorded.`date`
						AND ar.groupid = recorded.groupid
						AND ar.personid = '.$db->quote($this->id).'
					LEFT JOIN person_group g ON recorded.groupid = g.id
				WHERE
				';
		if ($groupid != -1) {
			$sql .= ' recorded.groupid = '.(int)$groupid;
		} else {
			$sql .= '((recorded.groupid = 0) OR (g.name <> ""))';
		}
		$sql .= '
				GROUP BY g.id, recorded.groupid, recorded.date, ar.present
				ORDER BY recorded.groupid, recorded.date';
		$attendances = $db->queryAll($sql, null, null, true, true, true);
		if ($groupid != -1) $attendances = reset($attendances);
		return $attendances;
	}

	function saveAttendance($attendances, $groupid, $checkinid=NULL) {
		$db =& $GLOBALS['db'];

		$SQL = 'DELETE FROM attendance_record
				WHERE personid = '.(int)$this->id.'
				AND date IN ('.implode(',', array_map((Array($db, 'quote')), array_keys($attendances))).')
				AND groupid = '.(int)$groupid;
		$res = $db->exec($SQL);

		$sets = Array();
		$SQL = 'INSERT INTO attendance_record (personid, groupid, date, present, checkinid)
				VALUES ';
		foreach ($attendances as $date => $present) {
			if ($present == '' || $present == '?' || $present == 'unknown') continue;
			$sets[] = '('.(int)$this->id.', '.(int)$groupid.', '.$db->quote($date).', '.(($present == 1 || $present == 'present') ? 1 : 0).', '.$db->quote($checkinid).')';
		}
		if ($sets) {
			$SQL .= implode(",\n", $sets);
			$res = $db->exec($SQL);
		}

	}

	public static function getPersonsBySearch($searchTerm, $includeArchived=true)
	{
		$db = $GLOBALS['db'];
		$SQL = '
			SELECT pp.id, pp.*
			FROM (
				SELECT p.*
				FROM person p
				WHERE (
					(first_name LIKE '.$db->quote($searchTerm.'%').')
					OR (last_name LIKE '.$db->quote($searchTerm.'%').')
					OR (first_name LIKE '.$db->quote('% '.$searchTerm.'%').')
					OR (last_name LIKE '.$db->quote('% '.$searchTerm.'%').')
					OR (CONCAT(first_name, " ", last_name) LIKE '.$db->quote($searchTerm.'%').')
				)

				UNION

				SELECT p.*
				FROM person p
				JOIN custom_field_value cfv ON cfv.personid = p.id
				JOIN custom_field cf ON cfv.fieldid = cf.id
				WHERE cf.searchable
				AND (
					(cfv.value_text LIKE '.$db->quote($searchTerm.'%').')
					OR (cfv.value_text LIKE '.$db->quote('% '.$searchTerm.'%').' )
				)
			) pp
			JOIN person_status ps ON ps.id = pp.status
		';
		if (!$includeArchived) {
			$SQL .= '
			WHERE (NOT ps.is_archived)
			';
		}
		$SQL .= '
			GROUP BY pp.id
			ORDER BY status
			';
		$res = $db->queryAll($SQL, null, null, true, true); // 5th param forces array even if one col
		return $res;

	}

	/**
	 * Find a person who matches the details given.
	 * @param array $match_data - keys can be first_name, last_name, mobile_tel, email
	 * @return Array(personid => (bool)$certain)
	 */
	public static function getMatchingPerson($match_data)
	{
		$keys = Array('first_name', 'last_name', 'email', 'mobile_tel');
		foreach ($keys as $k) {
			if (isset($match_data[$k])) $match_data[$k] = trim($match_data[$k]);
			if (empty($match_data[$k])) unset($match_data[$k]);
		}
		if (!empty($match_data['mobile_tel'])) {
			$match_data['mobile_tel'] = preg_replace('/[^0-9]/', '', array_get($match_data, 'mobile_tel', ''));
		}
		$db = JethroDB::get();

		$s = $w = Array();
		// 10 points for every col that positively matches
		foreach ($match_data as $k => $v) {
			$s[] = 'IF ('.$k.' = '.$db->quote($v).', 10, 0)';
			$w[$k] = '('.$k.' = '.$db->quote($v).')';
		}
		// 4 points if the email/mobile is blank so can't mis-match
		if (!empty($match_data['email'])) {
			$s[] = 'IF (email = "", 4, 0)';
		}
		if (!empty($match_data['mobile_tel'])) {
			$s[] = 'IF (mobile_tel = "", 4, 0)';
		}
		$SQL = 'SELECT ('.implode(' + ', $s).') as match_rating,
				p.id, p.first_name, p.last_name, p.email, p.mobile_tel
				FROM person p
				WHERE (
					'.implode(' OR ', $w).'
				)
				ORDER BY match_rating DESC
				LIMIT 2';
		$res = $db->queryAll($SQL, null, null, false, false);
		$top = reset($res);
		$second_hit = next($res);
		if ($top && (!$second_hit || ($second_hit['match_rating'] < $top['match_rating']))) {
			bam("Got one stand-out");
			// There is one stand-out result
			$DIFFERENT = -1;
			$MATCH = 1;
			$UNKNOWN = 0;
			foreach ($keys as $k) {
				$cmp[$k] = self::_compareMatch(array_get($match_data, $k), $top[$k]);
			}
			bam($cmp);
			if ($cmp['last_name'] == $MATCH) {
				if ($cmp['first_name'] == $MATCH) {
					if (($cmp['mobile_tel'] != $DIFFERENT) && ($cmp['email'] != $DIFFERENT)) {
						// Match on both names and no clash on contact details = certain.
						return Array($top['id'] => TRUE);
					} else if (($cmp['mobile_tel'] = $MATCH) || ($cmp['email'] == $MATCH)) {
						// One contact detail is different, but 3 other fields have a positive match = probable
						return Array($top['id'] => FALSE);
					} else {
						// Contact details must both mis-match = no match
						return Array(NULL => NULL);
					}
				} else {
					// First name is not a match, we will need some convincing
					if (($cmp['mobile_tel'] == $MATCH) || ($cmp['email'] == $MATCH)) {
						// First name is different but a positive match on 1+ contact fields
						return Array($top['id'] => FALSE);
					} else {
						return Array(NULL => NULL);
					}
				}
			} else if ($cmp['last_name'] == $UNKNOWN) {
				if (($cmp['first_name'] == $MATCH) && ($cmp['email']+$cmp['mobile_tel'] > 0)) {
					// firstname and at least one contact field has positive match, and there are no mis-matches = probable
					return Array($top['id'] => FALSE);
				}
			}
		}
		bam("bottomed out");
		return Array(NULL => NULL);
	}

	private static function _compareMatch($x, $y) {
		$x = $x || '';
		$y = $y || '';
		$x = strtolower($x);
		$y = strtolower($y);
		if (($x != '') && ($y != '') && ($x != $y)) {
			return -1; // truly different
		}
		if (($x != '') && ($y != '') && ($x == $y)) {
			return 1; // truly the same
		}
		return 0; // one must be blank, can't be sure.
	}	

	public function save($update_family=TRUE)
	{
		$GLOBALS['system']->doTransaction('BEGIN');
		$msg = '';

		if ($update_family && $GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
			// We do this perm check because someone might
			// be updating themselves but saving the family will fail

			if (!empty($this->_old_values['status']) || !empty($this->_old_values['last_name'])) {
				$family = $GLOBALS['system']->getDBObject('family', $this->getValue('familyid'));
				$members = $family->getMemberData(TRUE);
				$archivedStatuses = Person_Status::getArchivedIDs();

				if (!empty($this->_old_values['status']) && (in_array($this->getValue('status'), $archivedStatuses))) {
					// status has just been changed to 'archived' so archive family if no live members

					$found_live_member = false;
					foreach ($members as $id => $details) {
						if ($id == $this->id) continue;
						if (!in_array($details['status'], $archivedStatuses)) {
							$found_live_member = true;
							break;
						}
					}
					if (!$found_live_member) {
						if ($family->canAcquireLock()) {
							$family->acquireLock();
							$family->setValue('status', 'archived');
							$family->save(FALSE);
							$family->releaseLock();
							$msg = 'All members of the "'.$family->getValue('family_name').'" family are now archived, the family itself has been archived also';
						} else {
							$msg = 'All members of the "'.$family->getValue('family_name').'" family are now archived.  However the family itself could not be archived because another user holds the lock.';
						}
					}
				}
				if (in_array(array_get($this->_old_values, 'status'), $archivedStatuses) && (!in_array($this->getValue('status'), $archivedStatuses))) {
					// We have just been de-archived so de-archive family too
					if ($family->getValue('status') == 'archived') {
						if ($family->canAcquireLock()) {
							$family->acquireLock();
							$family->setValue('status', 'current');
							$family->save(FALSE);
							$family->releaseLock();
							$msg = '"'.$this->toString().'" has been de-archived, so the "'.$family->getValue('family_name').'" family has also been de-archived';
						} else {
							$msg = $msg = '"'.$this->toString().'" has been de-archived, so the "'.$family->getValue('family_name').'" family should also be de-archived, however this could not be completed because the lock on the family could not be acquried.';
						}
					}
				}
				if (!empty($this->_old_values['last_name'])) {
					// last name has changed - update the family name if only one member
					if (count($members) == 1) {
						if ($family->canAcquireLock()) {
							$family->acquireLock();
							$family->setValue('family_name', $this->getValue('last_name'));
							$family->save(FALSE);
							$family->releaseLock();
							$msg = 'Since "'.$this->toString().'" is the only member of family #'.$family->id.', it has been renamed to the  "'.$family->getValue('family_name').'" family';
						} else {
							$msg = 'Since "'.$this->toString().'" is the only member of family #'.$family->id.', it should be renamed to the  "'.$family->getValue('family_name').'" family. However this could not be completed because the lock on the family could not be acquired.';
						}
					}
				}
			}
		}

		if (!empty($this->_old_values['mobile_tel']) && ($this->getValue('mobile_tel') != $this->_old_values['mobile_tel'])) {
			// Mobile tel has changed; this could have 2FA implications
			$GLOBALS['user_system']->handle2FAMobileTelChange($this, $this->_old_values['mobile_tel']);
		}



		$res = parent::save();
		if ($res) {
			$this->_savePhoto();
			$this->_saveCustomValues();
			$GLOBALS['system']->doTransaction('COMMIT');
		} else {
			$GLOBALS['system']->doTransaction('ROLLBACK');
		}
		if ($msg) add_message($msg);
		return $res;
	}

	private function _savePhoto()
	{
		$db =& $GLOBALS['db'];
		if ($this->_photo_data === FALSE) {
			$this->_clearPhoto();
		} else if ($this->_photo_data) {
			$SQL = 'REPLACE INTO person_photo (personid, photodata)
					VALUES ('.(int)$this->id.', '.$db->quote($this->_photo_data).')';
			$db->query($SQL);
		}
	}

	private function _clearPhoto()
	{
		$db =& $GLOBALS['db'];
		$SQL = 'DELETE FROM person_photo WHERE personid = '.(int)$this->id;
		return $db->query($SQL);
	}

	private function _clearCustomValues()
	{
		$db =& $GLOBALS['db'];
		$SQL = 'DELETE FROM custom_field_value WHERE personid = '.(int)$this->id;
		$res = $db->query($SQL);
	}

	private function _saveCustomValues()
	{
		if (empty($this->_old_custom_values)) return; // Nothing to do.
		$this->_clearCustomValues();
		$db =& $GLOBALS['db'];
		$SQL = 'INSERT INTO custom_field_value
				(personid, fieldid, value_text, value_date, value_optionid)
				VALUES ';
		$customFields = self::getCustomFields();
		$sets = Array();
		foreach ($this->_custom_values as $fieldid => $values) {
			if (!is_array($values)) $values = empty($values) ? Array() : Array($values);
			foreach ($values as $value) {
				$dateVal = $textVal = $optionVal = NULL;
				if (strlen($value)) {
					switch ($customFields[$fieldid]['type']) {
						case 'date':
							$bits = explode(' ', $value);
							$dateVal = array_shift($bits);
							$textVal = implode(' ' , $bits);
							break;
						case 'select':
							$bits = explode(' ', $value);
							$idVal = array_shift($bits);
							$otherVal = implode(' ', $bits);
							if ($idVal) {
								$optionVal = $value;
							} else if (strlen($otherVal) && !empty($customFields[$fieldid]['params']['allow_other'])) {
								$textVal = $otherVal; // 'other' option was selected
							}
							break;
						default:
							$textVal = substr($value, 0, 255); // don't pass too-long strings to database
					}
					if ($textVal || $optionVal || $dateVal) {
						$sets[] = '('.(int)$this->id.','.(int)$fieldid.','.$db->quote($textVal).','.$db->quote($dateVal).','.$db->quote($optionVal).')';
					}
				}
			}
		}
		if ($sets) {
			$SQL .= implode(",\n", $sets);
			$res = $GLOBALS['db']->query($SQL);
		}
	}

	protected function _getChanges()
	{
		$res = parent::_getChanges();
		if (!empty($this->_old_custom_values)) {
			$customFields = self::getCustomFields();
			$dummyField = new Custom_Field();
			foreach ($this->_old_custom_values as $fieldid => $oldVal) {
				$dummyField->populate($fieldid, $customFields[$fieldid]);
				$res[] = $dummyField->getValue('name').' changed from "'.$dummyField->formatValue($oldVal).'" to "'.$dummyField->formatValue($this->_custom_values[$fieldid]).'"';
			}
		}
		return $res;
	}

	public function reset()
	{
		parent::reset();
		$this->_custom_values = Array();
		$this->_old_custom_values = Array();
		$this->_photo_data = NULL;
	}

	function create()
	{
		if (parent::create()) {
			$this->_savePhoto();
			$this->_saveCustomValues();
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Print a widget for choosing a person by name search
	 * @param string	$name				Form element name
	 * @param int		$currentval			PersonID
	 * @param string	$plannedAbsenceDate	(optional) - if specified, planned absences on this date should be displayed in results
	 */
	static function printSingleFinder($name, $currentval, $plannedAbsenceDate=NULL)
	{
		$currentid = 0;
		$currentname = '';
		if (is_int($currentval) && $currentval) {
			$currentid = $currentval;
			$person = $GLOBALS['system']->getDBObject('person', $currentid);
			if ($person) {
				$currentname = $person->toString();
			} else {
				$currentid = 0;
			}
		} else if (is_array($currentval)) {
			list($currentid, $currentname) = each ($currentval);
		}
		$displayname = $currentid ? $currentname.' (#'.$currentid.')' : '';
		?>
		<input type="text" placeholder="Search persons" id="<?php echo $name; ?>-input" class="person-search-single" data-show-absence-date="<?php echo $plannedAbsenceDate; ?>" value="<?php echo ents($displayname); ?>" />
		<input type="hidden" name="<?php echo $name; ?>" value="<?php echo $currentid; ?>" />
		<?php
	}

	/**
	 * Print a widget for choosing multiple persons by name search
	 * @param string	$name
	 * @param array		$val				Array of IDs
	 * @param string	$plannedAbsenceDate	(optional) - if specified, planned absences on this date should be displayed in results
	 */
	static function printMultipleFinder($name, $val=Array(), $plannedAbsenceDate=NULL)
	{
		$persons = empty($val) ? Array() : $GLOBALS['system']->getDBObjectData('person', Array('id' => $val));
		$absences = Planned_Absence::getForPersonsAndDate($val, $plannedAbsenceDate);
		$selected = Array();
		foreach ($persons as $id => $details) {
			$selected[$id] = $details['first_name'].' '.$details['last_name'];
			if (isset($absences[$id])) $selected[$id] .= ' !! ABSENT !!';
		}
		?>
		<ul class="multi-person-finder" id="<?php echo $name; ?>-list">
		<?php
		foreach ($selected as $id => $pname) {
			if (!$id) continue;
			echo '<li><div class="delete-chosen-person" onclick="deletePersonChooserListItem(this)"></div>'.$pname.'<input type="hidden" name="'.$name.'[]" value="'.$id.'" /></li>';
		}
		?>
		</ul>
		<input type="text" placeholder="Search persons" id="<?php echo $name; ?>-input" data-show-absence-date="<?php echo $plannedAbsenceDate; ?>" class="person-search-multiple" />
		<?php
	}

	static function getStatusStats($congregationid=NULL)
	{
		$sql = 'SELECT ps.label as status, count(p.id)
				FROM person p
				JOIN person_status ps ON p.status = ps.id';
		if ($congregationid !== NULL) {
			$sql .= ' WHERE congregationid = '.(int)$congregationid;
		}
		$sql .= ' 
			GROUP BY ps.id
			ORDER BY ps.`rank` ASC
			';
		$res = $GLOBALS['db']->queryAll($sql, NULL, NULL, true);
		return $res;
	}

	/**
	 * Get formatted custom field data indexed by personid and fieldname
	 * @param type $personids
	 * @return array
	 */
	static function getCustomMergeData($personids,$formatted=TRUE)
	{
		$db = $GLOBALS['db'];
		$SQL = 'SELECT '.Custom_Field::getRawValueSQLExpr('v', 'f').' AS value, f.name, v.personid, v.fieldid, f.type
				FROM custom_field_value v
				JOIN custom_field f ON v.fieldid = f.id
				WHERE v.personid IN ('.implode(',', array_map(Array($db, 'quote'), $personids)).')';
		$qres = $db->queryAll($SQL);
		$res = Array();

		$customFields = self::getCustomFields();
		$resTemplate = Array();
		foreach ($customFields as $fieldid => $fieldDetails) {
			$customFields[$fieldid] = new Custom_Field();
			$customFields[$fieldid]->populate($fieldid, $fieldDetails);
			$resTemplate[strtoupper(str_replace(' ', '_', $fieldDetails['name']))] = '';
		}
		foreach ($qres as $row) {
			if (!isset($res[$row['personid']])) {
				// Make sure we have something for every field in the result
				$res[$row['personid']] = $resTemplate;
			}
			$fname = strtoupper(str_replace(' ', '_', $row['name']));
			if ($formatted || ($row['type'] == 'select')) {
				$fVal = $customFields[$row['fieldid']]->formatValue($row['value']);
			} else {
				$fVal = $row['value'];
			}
			if (strlen($res[$row['personid']][$fname])) {
				$res[$row['personid']][$fname] .= ', '.$fVal;
			} else {
				$res[$row['personid']][$fname] = $fVal;
			}
		}
		return $res;
	}

	function getInstancesQueryComps($params, $logic, $order)
	{
		$res = parent::getInstancesQueryComps($params, $logic, $order);
		$res['select'][] = 'f.family_name, f.address_street, f.address_suburb, f.address_state, f.address_postcode, f.home_tel, c.name as congregation, ab.label as age_bracket';
		$res['from'] = '(('.$res['from'].')
						JOIN family f ON person.familyid = f.id)
						LEFT JOIN congregation c ON person.congregationid = c.id
						JOIN age_bracket ab on ab.id = person.age_bracketid
						JOIN person_status ps ON ps.id = person.status';
		return $res;
	}

	function printForm($prefix='', $fields=NULL)
	{
		include_once 'include/size_detector.class.php';

		if ($GLOBALS['system']->featureEnabled('PHOTOS')
			&& (is_null($fields) || in_array('photo', $fields))
		) {
			$this->fields['photo'] = Array('divider_before' => true); // fake field for interface purposes
			if ($this->id && !SizeDetector::isNarrow()) {
				?>
				<div class="person-photo-container">
					<img src="?call=photo&personid=<?php echo (int)$this->id; ?>" />
				</div>
				<?php
			}
		}

		if (!$this->id) unset($this->fields['familyid']);

		// Extra CSRF protection because mobile number is sensitive.
		$_SESSION['person_form_token'][$this->id] = generate_random_string();
		print_hidden_field($prefix.'token', $_SESSION['person_form_token'][$this->id]);

		parent::printForm($prefix, $fields);

		unset($this->fields['photo']);

		if (empty($fields) || in_array('custom', $fields)) {

			$customFields = self::getCustomFields();
			$dummyField = new Custom_Field();
			if ($customFields) {
				?>
				<hr />
				<div class="form-horizontal">
				<?php
				foreach ($customFields as $fieldid => $fieldDetails) {
					$dummyField->populate($fieldid, $fieldDetails);
					$tableClass = $fieldDetails['allow_multiple'] ? 'expandable no-name-increment' : '';
					$values = isset($this->_custom_values[$fieldid]) ? $this->_custom_values[$fieldid] : Array('');

					if ($fieldDetails['divider_before']) echo '<hr />';

					?>
					<div class="control-group">
						<?php
						if (strlen($fieldDetails['heading_before'])) {
							?>
								<h4><?php echo ents($fieldDetails['heading_before']); ?></h4>
							<?php
						}
						?>

						<label class="control-label" for="custom_<?php echo $fieldid; ?>"><?php echo ents($fieldDetails['name']); ?></label>
						<div class="controls">
							<table class="<?php echo $tableClass; ?>">
							<?php
							foreach ($values as $value) {
								?>
								<tr><td>
									<?php
									$dummyField->printWidget($value);
									?>
								</td></tr>
								<?php
							}
							?>
							</table>
						</div>
					</div>
					<?php
				}
				?>
				</div>
				<?php
			}
		}

	}

	function processForm($prefix='', $fields=NULL)
	{
		// Extra CSRF protection because mobile number is sensitive.
		if (($this->id) && (array_get($_REQUEST, $prefix.'token') != $_SESSION['person_form_token'][$this->id])) {
			trigger_error("Synchroniser token mismatch - person could not be saved", E_USER_ERROR);
			return FALSE;
		}

		$res = parent::processForm($prefix, $fields);
		if (empty($fields)) {
			foreach ($this->getCustomFields() as $fieldid => $fieldDetails) {
				$field = $GLOBALS['system']->getDBObject('custom_field', $fieldid);
				$this->setCustomValue($fieldid, $field->processWidget($prefix));
			}
		}
		$this->_photo_data = Photo_Handler::getUploadedPhotoData($prefix.'photo');
		return $res;
	}

	function printFieldInterface($name, $prefix='')
	{
		switch ($name) {
			case 'photo':
				$existing_photo_url = NULL;
				if ($this->id && $GLOBALS['db']->queryOne('SELECT 1 FROM person_photo WHERE personid = '.(int)$this->id)) {
					$existing_photo_url = '?call=photo&personid='.(int)$this->id; 
				}
				Photo_Handler::printChooser($prefix, $existing_photo_url);
				break;
			case 'familyid':
				?>
				<div class="controls-text">
				<i class="icon-home"></i>
				<?php $this->printFieldValue('familyid'); ?>
				<br />
				<a href="<?php echo build_url(Array('view' => '_edit_family', 'familyid' => $this->values['familyid'])); ?>">
				<i class="icon-wrench"></i>Edit family details</a>
				&nbsp;
				<a href="<?php echo build_url(Array('view' => '_move_person_to_family', 'personid' => $this->id)); ?>">
				<i class="icon-random"></i>Move to different family</a>
				</div>
				<?php
				break;
			case 'congregationid':
				$stats = $GLOBALS['system']->getDBOBjectData('person_status', Array('active'=> 1));
				foreach ($stats as $id => $details) {
					print_hidden_field('status_'.$id.'_require_congregation', (int)$details['require_congregation']);
				}
				// intentional fallthrough.
			default:
				parent::printFieldInterface($name, $prefix);
		}
	}

	public function setFeedUUID()
	{
		$uuid = generate_random_string(60);
		while ($others = $GLOBALS['system']->getDBObjectData('person', Array('feed_uuid' => $uuid))) {
			$uuid = generate_random_string(60);
		}
		$this->setValue('feed_uuid', $uuid);
		return $uuid;
	}

	public static function &getCustomFields()
	{
		static $customFields = NULL;
		if ($customFields === NULL) {
			$customFields = $GLOBALS['system']->getDBObjectData('custom_field', Array(), 'OR', 'rank');
		}
		return $customFields;
	}

	public function setCustomValue($fieldid, $newVal, $addToExisting=FALSE)
	{
		$fields = self::getCustomFields();
		$oldVal = array_get($this->_custom_values, $fieldid, '');
		if ((!empty($oldVal) || !empty($newVal)) && ($addToExisting || ($oldVal != $newVal))) {
			$this->_old_custom_values[$fieldid] = $oldVal;
			if ($fields[$fieldid]['allow_multiple'] && $addToExisting && $oldVal) {
				$this->_custom_values[$fieldid] = array_merge((array)$oldVal, (array)$newVal);
			} else {
				$this->_custom_values[$fieldid] = $newVal;
			}
		}
	}

	public function getCustomValues()
	{
		return $this->_custom_values;
	}

	/**
	 * Set values (incl custom values) from an array of data from CSV
	 * NB it should NOT assume that the input row contains ALL custom fields
	 * Any existing data not mentioned in the import row should be retained.
	 * @param array $row
	 * @param bool $overwriteExistingValues	For fields that already have a value, whether to overwrite with new data from $row.
	 */
	public function fromCsvRow($row, $overwriteExistingValues=TRUE) {
		static $customFields = NULL;
		if ($customFields === NULL) {
			$fields = $GLOBALS['system']->getDBObjectdata('custom_field');
			foreach ($fields as $fieldID => $field) {
				$field['id'] = $fieldID;
				$customFields[str_replace(' ', '_', strtolower($field['name']))] = $GLOBALS['system']->getDBObject('custom_field', $fieldID);
			}
		}
		if (empty($this->id) && !isset($row['gender'])) $row['gender'] = "Unknown"; // only apply the female default via the GUI, not import
		foreach ($row as $k => $v) {
			$k = str_replace(' ', '_', strtolower($k));
			if (isset($customFields[$k]) && strlen($v)) {
				if (empty($this->id)
						|| $overwriteExistingValues
						|| ($this->_custom_values[$customFields[$k]->id] == '')
				) {
					$this->setCustomValue($customFields[$k]->id, $customFields[$k]->parseValue($v));
				}
				unset($row[$k]); // so it doesn't upset db_object::fromCsvRow
			}
		}

		if (!empty($row['status']) && !is_int($row['status'])) {
			$row['status'] = Person_Status::getByLabel($row['status']);
		}

		if (isset($row['age_bracket']) && strlen($row['age_bracket'])) {
			foreach (Age_Bracket::getMap() as $id => $label) {
				if (trim(strtolower($label)) == trim(strtolower($row['age_bracket']))) {
					$row['age_bracketid'] = $id;
					break;
				}
			}
			if (!isset($row['age_bracketid'])) {
				// no match was found - copy the raw value across to trigger an error later
				trigger_error("Invalid age bracket ".$row['age_bracket']);
				$row['age_bracketid'] = NULL;
			}
			unset($row['age_bracket']);
		}

		parent::fromCsvRow($row, $overwriteExistingValues);
	}

	public function populate($id, $values)
	{
		parent::populate($id, $values);
		$this->_custom_values = Array();
		$this->_old_custom_values = Array();

		foreach ($values as $k => $v) {
			if (0 === strpos($k, 'CUSTOM_')) {
				$this->setCustomValue(substr($k, 7), $v);
			}
		}
	}

	/**
	 * Archive and clean this record:
	 *  Change their name to "Removed"
	 *	Change their status to "archived"
	 *	Blank out all their fields except congregation
	 *	Clear their history and notes
	 *	Preserve their (anonymous) roster assignments, group memberships and attendance records
	 */
	public function archiveAndClean()
	{
		$res = 1;
		$GLOBALS['system']->doTransaction('BEGIN');
		$this->setValue('first_name', '['._('Removed').']');
		$this->setValue('last_name', '['._('Removed').']');
		$this->setValue('email', '');
		$this->setValue('mobile_tel', '');
		$this->setValue('work_tel', '');
		$this->setValue('remarks', '');
		$this->setValue('gender', '');
		$this->setValue('feed_uuid', '');
		$stats = Person_Status::getArchivedIDs();
		$this->setValue('status', reset($stats)); // we use the top-ranked 'is_archived' status.
		$this->setValue('history', Array());
		$this->_clearCustomValues();
		$this->_clearPhoto();
		if (!$this->save(FALSE)) return FALSE;

		$notes = $GLOBALS['system']->getDBObjectData('person_note', Array('personid' => $this->id));
		foreach ($notes as $noteid => $data) {
			$n = new Person_Note($noteid);
			$n->delete();
		}

		$family = $GLOBALS['system']->getDBObject('family', $this->getValue('familyid'));
		$members = $family->getMemberData();
		$found_live_member = false;
		foreach ($members as $id => $details) {
			if ($id == $this->id) continue;
			if (!in_array($details['status'], Person_Status::getArchivedIDs())) {
				$found_live_member = true;
				break;
			}
		}
		if (!$found_live_member) {
			if ($family->archiveAndClean()) $res = 2;
		}

		$GLOBALS['system']->doTransaction('COMMIT');
		return $res;
	}

	public function delete()
	{
		$GLOBALS['system']->doTransaction('BEGIN');
		$family = $GLOBALS['system']->getDBObject('family', $this->getValue('familyid'));
		$members = $family->getMemberData();
		unset($members[$this->id]);
		parent::delete();
		if (empty($members)) {
			$family->delete();
		}
		Abstract_Note::cleanupInstances();
		$GLOBALS['system']->doTransaction('COMMIT');
	}

}

