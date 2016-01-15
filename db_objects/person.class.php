<?php
include_once 'include/db_object.class.php';
include_once 'include/size_detector.class.php';
class Person extends DB_Object
{
	var $_save_permission_level = PERM_EDITPERSON;
	var $_photo_data = NULL;
	var $_custom_values = Array();
	var $_old_custom_values = Array();

	const MAX_PHOTO_WIDTH = 200;
	const MAX_PHOTO_HEIGHT = 200;

	function __construct($id=0)
	{
		if ($id == $this->getCurrentUser('id')) {
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

	protected static function _getFields()
	{
		$allowEmptyCong = TRUE;
		if (!empty($GLOBALS['user_system']) && $GLOBALS['user_system']->getCurrentRestrictions('congregation')) {
			$allowEmptyCong = FALSE; // Can only add to congs we can see.
		}
		$res = Array(
			'first_name'	=> Array(
									'type'		=> 'text',
									'width'		=> 30,
									'maxlength'	=> 128,
									'allow_empty'	=> false,
									'initial_cap'	=> true,
									'trim'			=> TRUE,
								   ),
			'last_name'		=> Array(
									'type'		=> 'text',
									'width'		=> 30,
									'maxlength'	=> 128,
									'allow_empty'	=> false,
									'initial_cap'	=> true,
									'trim'			=> TRUE,
								   ),
			'gender'		=> Array(
									'type'			=> 'select',
									'options'		=> Array('male' => 'Male', 'female' => 'Female', '' => 'Unknown'),
									'default'		=> '',
									'divider_before'	=> true,
							   ),
			'age_bracket'	=> Array(
									'type'			=> 'select',
									'options'		=> explode(',', AGE_BRACKET_OPTIONS),
									'default'		=> '0',
									'allow_empty'	=> false,
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
									'order_by'			=> 'name',
									'label'				=> 'Congregation',
									'show_id'			=> FALSE,
									'allow_empty'		=> $allowEmptyCong,
									'class'				=> 'person-congregation',
							   ),
			'status'	=> Array(
								'type'	=> 'select',
								'options'	=> explode(',', PERSON_STATUS_OPTIONS) 
											+ Array('contact' => 'Contact', 'archived' => 'Archived'),
								'default'	=> 'contact' /* but see below */,
								'class'		=> 'person-status',
								'allow_empty'	=> false,
						   ),
			'email'			=> Array(
									'divider_before' => true,
									'type'		=> 'email',
									'width'		=> 40,
									'maxlength'	=> 255,
									'class'		=> 'valid-email',
								   ),
			'mobile_tel'	=> Array(
									'type'			=> 'phone',
									'formats'		=> MOBILE_TEL_FORMATS,
									'allow_empty'	=> TRUE,
								   ),
			'work_tel'	=> Array(
									'type'			=> 'phone',
									'formats'		=> WORK_TEL_FORMATS,
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
									)
	
		);
		if (defined('PERSON_STATUS_DEFAULT')) {
			if (FALSE !== ($i = array_search(constant('PERSON_STATUS_DEFAULT'), $res['status']['options']))) {
				$res['status']['default'] = "$i";
			}
		}
		return $res;
	}

	function getInitSQL()
	{
		return Array(
			"CREATE TABLE `_person` (
			  `id` int(11) NOT NULL auto_increment,
			  `first_name` varchar(255) NOT NULL default '',
			  `last_name` varchar(255) NOT NULL default '',
			  `gender` varchar(64) NOT NULL default '',
			  `age_bracket` varchar(64) NOT NULL default '',
			  `email` varchar(255) NOT NULL default '',
			  `mobile_tel` varchar(12) NOT NULL default '',
			  `work_tel` varchar(12) NOT NULL default '',
			  `remarks` text NOT NULL,
			  `status` varchar(8) NOT NULL default '',
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
			  PRIMARY KEY  (`id`),
			  KEY `first_name` (`first_name`),
			  KEY `last_name` (`last_name`),
			  KEY `email` (`email`),
			  KEY `mobile_tel` (`mobile_tel`),
			  KEY `work_tel` (`work_tel`),
			  KEY `status` (`status`),
			  KEY `familyid` (`familyid`)
			) ENGINE=InnoDB ;",

			"CREATE TABLE person_photo (
			   personid int(11) not null,
			   photodata mediumblob not null,
			   PRIMARY KEY (personid),
			   CONSTRAINT photo_personid FOREIGN KEY (`personid`) REFERENCES `_person` (`id`) ON DELETE CASCADE
			) ENGINE=InnoDB",
		);
	}

	public function load($id) {
		parent::load($id);

		// Load custom values
		$SQL = 'SELECT v.fieldid, '.Custom_Field::getRawValueSQLExpr('v').' as value
				FROM custom_field_value v
				WHERE personid = '.(int)$this->id;
		$res = $GLOBALS['db']->queryAll($SQL, NULL, NULL, true, FALSE, TRUE);
		check_db_result($res);
		$this->_custom_values = $res;
	}

	function toString()
	{
		return $this->values['first_name'].' '.$this->values['last_name'];
	}


	function printFieldValue($name, $value=null)
	{
		if (is_null($value)) $value = $this->getValue($name);
		switch ($name) {
			case 'name':
				echo ents($this->getValue('first_name')).'&nbsp;'.ents($this->getValue('last_name'));
				return;
			case 'mobile_tel':
				
				if (!strlen($value)) return;
				echo ents($this->getFormattedValue($name, $value));

				$smsLink = '';
				if (SizeDetector::isNarrow()) {
					// Probably a phone - use a plain sms: link
					$smsLink = 'href="sms:'.ents($value).'"';
				} else if (defined('SMS_HTTP_URL') && constant('SMS_HTTP_URL') && $GLOBALS['user_system']->havePerm(PERM_SENDSMS)) {
					// Provide a link to send SMS through the SMS gateway
					?>
					<div id="send-sms-modal" class="modal hide fade" role="dialog" aria-hidden="true">
						<form method="post" action="?view=_send_sms_http">
							<input type="hidden" name="personid" value="<?php echo $this->id; ?>" />

							<div class="modal-header">
								<h4>Send SMS to <?php $this->printFieldValue('name'); ?></h4>
							</div>
							<div class="modal-body">
								Message:<br />
								<textarea autofocus="autofocus" name="message" class="span4" rows="5" cols="30" maxlength="<?php echo SMS_MAX_LENGTH; ?>"></textarea>
							</div>
							<div class="modal-footer">
								<input type="submit" class="btn" value="Send" accesskey="s" onclick="if (!$('[name=message]').val()) { alert('Enter a message first'); return false; }" />
								<button class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>
							</div>
						</form>
					</div>
					<?php
					$smsLink = 'href="#send-sms-modal" data-toggle="modal"';
				}
				?>
				<span class="nowrap">
					<a href="tel:<?php echo ents($value); ?>" class="btn btn-mini"><i class="icon-phone"></i></a>
				<?php
				if ($smsLink) {
					?>
					<a <?php echo $smsLink; ?> class="btn btn-mini"><i class="icon-envelope"></i></a>
					<?php
				}
				?>
				</span>
				<?php
				return;


		}
		parent::printFieldValue($name, $value);
	}

	protected function _printSummaryRows() {
		parent::_printSummaryRows();

		$divided = FALSE;
		$dummyField = new Custom_Field();
		foreach ($this->getCustomFields() as $fieldid => $fieldDetails) {
			$dummyField->populate($fieldid, $fieldDetails);
			if (isset($this->_custom_values[$fieldid])) {
				?>
				<tr <?php if (!$divided) { echo 'class="divider-before"'; $divided = TRUE; } ?>>
					<th><?php echo ents($fieldDetails['name']); ?></th>
					<td>
						<?php
						foreach ($this->_custom_values[$fieldid] as $j => $val) {
							if ($j > 0) echo '<br />';
							echo ents($dummyField->formatValue($val));
						}
						?>
					</td>
				</tr>
				<?php
			}
		}
	}


	function getNotesHistory()
	{
		$family_notes = $GLOBALS['system']->getDBObjectData('family_note', Array('familyid' => $this->getValue('familyid')));
		$person_notes = $GLOBALS['system']->getDBObjectData('person_note', Array('personid' => $this->id));
		$all_notes = $family_notes + $person_notes;
		uasort($all_notes, Array($this, '_compareCreatedDates'));
		return $all_notes;
	}

	function _compareCreatedDates($a, $b)
	{
		return $a['created'] > $b['created'];

	}

	function validateFields()
	{
		if (!parent::validateFields()) return FALSE;
		if (empty($this->values['congregationid']) && ($this->values['status'] != 'contact') && ($this->values['status'] != 'archived')) {
			trigger_error('Only persons with status "contact" may have a blank congregation');
			return FALSE;
		}
		return TRUE;
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
				GROUP BY recorded.groupid, recorded.date
				ORDER BY recorded.groupid, recorded.date';
		$attendances = $db->queryAll($sql, null, null, true, true, true);
		if ($groupid != -1) $attendances = reset($attendances);
		check_db_result($attendances);
		return $attendances;
	}

	function saveAttendance($attendances, $groupid) {
		$db =& $GLOBALS['db'];

		$SQL = 'DELETE FROM attendance_record
				WHERE personid = '.(int)$this->id.'
				AND date IN ('.implode(',', array_map((Array($db, 'quote')), array_keys($attendances))).')
				AND groupid = '.(int)$groupid;
		$res = $db->exec($SQL);
		check_db_result($res);

		$SQL = 'INSERT INTO attendance_record (personid, groupid, date, present)
				VALUES ';
		foreach ($attendances as $date => $present) {
			if ($present == '' || $present == '?' || $present == 'unknown') continue;
			$sets[] = '('.(int)$this->id.', '.(int)$groupid.', '.$db->quote($date).', '.(($present == 1 || $present == 'present') ? 1 : 0).')';
		}
		$SQL .= implode(",\n", $sets);
		$res = $db->exec($SQL);
		check_db_result($res);
		
	}

	function getPersonsByName($name, $include_archived=true)
	{
		$params = Array('CONCAT(first_name, " ", last_name)' => $name);
		if (!$include_archived) {
			$params['!status'] = 'archived';
		}
		$results = $GLOBALS['system']->getDBObjectData('person', $params, 'AND', 'last_name');
		if (empty($results)) {
			$params['CONCAT(first_name, " ", last_name)'] = '%'.$name.'%';
			$results = $GLOBALS['system']->getDBObjectData('person', $params, 'AND', 'last_name');
		}
		return $results;
	}
	
	function save($update_family=TRUE)
	{
		$GLOBALS['system']->doTransaction('BEGIN');
		$msg = '';

		if ($update_family && $GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
			// We do this perm check because someone might
			// be updating themselves but saving the family will fail

			if (!empty($this->_old_values['status']) || !empty($this->_old_values['last_name'])) {
				$family =& $GLOBALS['system']->getDBObject('family', $this->getValue('familyid'));
				$members = $family->getMemberData();
				
				if (!empty($this->_old_values['status']) && ($this->getValue('status') == 'archived')) {
					// status has just been changed to 'archived' so archive family if no live members

					$found_live_member = false;
					foreach ($members as $id => $details) {
						if ($id == $this->id) continue;
						if ($details['status'] != 'archived') {
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
				if ((array_get($this->_old_values, 'status') == 'archived') && ($this->getValue('status') != 'archived')) {
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

	function _savePhoto() {
		$db =& $GLOBALS['db'];
		if ($this->_photo_data) {
			$SQL = 'REPLACE INTO person_photo (personid, photodata)
					VALUES ('.(int)$this->id.', '.$db->quote($this->_photo_data).')';
			$res = $db->query($SQL);
			check_db_result($res);
		}
	}

	function _saveCustomValues() {
		$db =& $GLOBALS['db'];
		$SQL = 'DELETE FROM custom_field_value WHERE personid = '.(int)$this->id;
		check_db_result($db->query($SQL));
		$SQL = 'INSERT INTO custom_field_value
				(personid, fieldid, value_text, value_date, value_optionid)
				VALUES ';
		$customFields = $this->getCustomFields();
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
							$optionVal = $value;
							break;
						default:
							$textVal = $value;
					}
					$sets[] = '('.(int)$this->id.','.(int)$fieldid.','.$db->quote($textVal).','.$db->quote($dateVal).','.$db->quote($optionVal).')';
				}
			}
		}
		if ($sets) {
			$SQL .= implode(",\n", $sets);
			check_db_result($GLOBALS['db']->query($SQL));
		}
	}

	protected function _getChanges()
	{
		$res = parent::_getChanges();
		if (!empty($this->_old_custom_values)) {
			$customFields = $this->getCustomFields();
			$dummyField = new Custom_Field();
			foreach ($this->_old_custom_values as $fieldid => $oldVal) {
				$dummyField->populate($fieldid, $customFields[$fieldid]);
				$res[] = $dummyField->getValue('name').' changed from "'.$dummyField->formatValue($oldVal).'" to "'.$dummyField->formatValue($this->_custom_values[$fieldid]).'"';
			}
		}
		return $res;
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

	static function printSingleFinder($name, $currentval)
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
		<input type="text" placeholder="Search persons" id="<?php echo $name; ?>-input" class="person-search-single" value="<?php echo ents($displayname); ?>" />
		<input type="hidden" name="<?php echo $name; ?>" value="<?php echo $currentid; ?>" />
		<?php
	}

	static function printMultipleFinder($name, $val=Array())
	{
		if (!empty($val) && reset($val) == '') {
			// contains only IDs - need to get names
			$persons = $GLOBALS['system']->getDBObjectData('person', Array('id' => array_keys($val)));
			foreach ($persons as $id => $details) {
				$val[$id] = $details['first_name'].' '.$details['last_name'];
			}
		}
		?>
		<ul class="multi-person-finder" id="<?php echo $name; ?>-list">
		<?php
		foreach ($val as $id => $pname) {
			if (!$id) continue;
			echo '<li><div class="delete-chosen-person" onclick="deletePersonChooserListItem(this)"></div>'.$pname.'<input type="hidden" name="'.$name.'[]" value="'.$id.'" /></li>';
		}
		?>
		</ul>
		<input type="text" placeholder="Search persons" id="<?php echo $name; ?>-input" class="person-search-multiple" />
		<?php
	}

	function getStatusOptions()
	{
		return $this->fields['status']['options'];
	}

	static function getStatusStats()
	{
		$dummy = new Person();
		$status_options = $dummy->getStatusOptions();
		$sql = 'SELECT status, count(id)
				FROM person
				GROUP BY status';
		$res = $GLOBALS['db']->queryAll($sql, NULL, NULL, true);
		check_db_result($res);
		$out = Array();
		foreach ($status_options as $k => $v) {
			$out[$v] = (int)array_get($res, $k, 0);
		}
		return $out;
	}
		
	function getInstancesQueryComps($params, $logic, $order)
	{
		$res = parent::getInstancesQueryComps($params, $logic, $order);
		$res['select'][] = 'f.family_name, f.address_street, f.address_suburb, f.address_state, f.address_postcode, f.home_tel, c.name as congregation';
		$res['from'] = '(('.$res['from'].') 
						JOIN family f ON person.familyid = f.id)
						LEFT OUTER JOIN congregation c ON person.congregationid = c.id';
		return $res;
	}

	function printForm($prefix='', $fields=NULL)
	{
		include_once 'include/size_detector.class.php';

		if ($GLOBALS['system']->featureEnabled('PHOTOS')
			&& (is_null($fields) || in_array('photo', $fields))
			&& !SizeDetector::isNarrow()
		) {
			$this->fields['photo'] = Array('divider_before' => true); // fake field for interface purposes
			if ($this->id) {
				?>
				<div class="person-photo-container">
					<img src="?call=person_photo&personid=<?php echo (int)$this->id; ?>" />
				</div>
				<?php
			}
		}

		if (!$this->id) unset($this->fields['familyid']);

		parent::printForm($prefix, $fields);

		unset($this->fields['photo']);

		$customFields = $this->getCustomFields();
		$dummyField = new Custom_Field();
		if ($customFields) {
			?>
			<hr />
			<div class="form-horizontal">
			<?php
			foreach ($customFields as $fieldid => $fieldDetails) {
				$dummyField->populate($fieldid, $fieldDetails);
				$tableClass = $fieldDetails['allow_multiple'] ? 'expandable' : '';
				$values = isset($this->_custom_values[$fieldid]) ? $this->_custom_values[$fieldid] : Array('');
				?>
				<div class="control-group">
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

	function processForm($prefix='', $fields=NULL)
	{
		$res = parent::processForm($prefix, $fields);
		foreach ($this->getCustomFields() as $fieldid => $fieldDetails) {
			$field = $GLOBALS['system']->getDBObject('custom_field', $fieldid);
			$this->setCustomValue($fieldid, $field->processWidget());
		}

		if (!empty($_FILES['photo']) && !$_FILES['photo']['error']) {
			if (!in_array($_FILES['photo']['type'], Array('image/jpeg', 'image/gif', 'image/png', 'image/jpg'))) {
				add_message("The uploaded photo was not of a permitted type and has not been saved.  Photos must be JPEG, GIF or PNG", 'error');
			} else if (!is_uploaded_file($_FILES['photo']['tmp_name'])) {
				trigger_error("Security error with file upload", E_USER_ERROR);
			} else {
				$ext = strtolower(end(explode('.', $_FILES['photo']['name'])));
				if ($ext == 'jpg') $ext = 'jpeg';
				if (!in_array($ext, Array('jpeg', 'gif', 'png'))) {
					add_message("The uploaded photo was not of a permitted type and has not been saved.  Photos must be JPEG, GIF or PNG", 'error');
					return $res;
				}
				if (function_exists('imagepng')) {
					$fn = 'imagecreatefrom'.$ext;
					list($orig_width, $orig_height) = getimagesize($_FILES['photo']['tmp_name']);
					$input_img = $fn($_FILES['photo']['tmp_name']);
					if (!$input_img) exit;
					$orig_ratio = $orig_width / $orig_height;
					if (($orig_width > self::MAX_PHOTO_WIDTH) || ($orig_height > self::MAX_PHOTO_HEIGHT)) {
						if (self::MAX_PHOTO_WIDTH > self::MAX_PHOTO_HEIGHT) {
							// resize to fit width then crop to fit height
							$new_width = self::MAX_PHOTO_WIDTH;
							$new_height = min(self::MAX_PHOTO_HEIGHT, $new_width / $orig_ratio);
							$src_x = 0;
							$src_w = $orig_width;
							$src_h = $new_height * ($orig_width / $new_width);
							$src_y = (int)max(0, ($orig_height - $src_h) / 2);
						} else {
							// resize to fit height then crop to fit width
							$new_height = self::MAX_PHOTO_HEIGHT;
							$new_width = min(self::MAX_PHOTO_WIDTH, $new_height * $orig_ratio);
							$src_y = 0;
							$src_h = $orig_height;
							$src_w = $new_width * ($orig_height / $new_height);
							$src_x = (int)max(0, ($orig_width - $src_w) / 2);
						}
						$output_img = imagecreatetruecolor($new_width, $new_height);
						imagecopyresized($output_img, $input_img, 0, 0, $src_x, $src_y, $new_width, $new_height, $src_w, $src_h);
						imagedestroy($input_img);
					} else {
						$output_img = $input_img;
					}
					$fn = 'image'.$ext;
					$fn($output_img, $_FILES['photo']['tmp_name']);
				}
				$this->_photo_data = file_get_contents($_FILES['photo']['tmp_name']);
				unlink($_FILES['photo']['tmp_name']);
			}
		}
		return $res;
	}

	function printFieldInterface($name, $prefix='')
	{
		switch ($name) {
			case 'photo':
				?>
				<input type="file" name="photo" />
				<?php
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

	private function &getCustomFields()
	{
		if (!isset($this->_tmp['custom_fields'])) {
			$this->_tmp['custom_fields'] = $GLOBALS['system']->getDBObjectData('custom_field', Array(), 'OR', 'rank');
		}
		return $this->_tmp['custom_fields'];
	}

	public function setCustomValue($fieldid, $newVal, $addToExisting=FALSE)
	{
		$fields = $this->getCustomFields();
		$oldVal = array_get($this->_custom_values, $fieldid, '');
		if ((!empty($oldVal) || !empty($newVal)) && ($oldVal != $newVal)) {
			$this->_old_custom_values[$fieldid] = $oldVal;
			if ($fields[$fieldid]['allow_multiple'] && $addToExisting && $oldVal) {
				$this->_custom_values[$fieldid] = array_merge((array)$oldVal, $newVal);
			} else {
				$this->_custom_values[$fieldid] = $newVal;
			}
		}
	}


}
