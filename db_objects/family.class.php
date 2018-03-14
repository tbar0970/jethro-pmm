<?php
include_once 'include/db_object.class.php';
include_once 'include/size_detector.class.php';
class family extends db_object
{
	protected $_save_permission_level = PERM_EDITPERSON;
	private $_photo_data = NULL;

	protected static function _getFields()
	{

		$fields = Array(
			'family_name'		=> Array(
									'type'		=> 'text',
									'width'		=> 40,
									'maxlength'	=> 128,
									'allow_empty'	=> FALSE,
									'initial_cap'	=> TRUE,
									'class'			=> 'family-name autofocus',
									'trim'			=> TRUE,
								   ),
			'status'			=> Array(
									'type'			=> 'select',
									'options'		=> Array(
														'current'	=> 'Current',
														'archived'	=> 'Archived',
													   ),
									'default'		=> 'current',
								   ),
			'home_tel'			=> Array(
									'type'			=> 'phone',
									'formats'		=> ifdef('HOME_TEL_FORMATS', 'XXXX-XXXX'),
									'allow_empty'	=> TRUE,
								   ),
			'address_street'	=> Array(
									'type'		=> 'text',
									'width'		=> 40,
									'height'	=> 2,
									'maxlength'	=> 255,
									'label'		=> SizeDetector::isNarrow() ? 'Address' : 'Street Address',
									'trim'			=> TRUE,
									'divider_before' => TRUE,
								   ),
			'address_suburb'	=> Array(
									'type'		=> 'text',
									'width'		=> 40,
									'maxlength'	=> 128,
									'label'		=> ifdef('ADDRESS_SUBURB_LABEL', 'Suburb'),
									'initial_cap'	=> TRUE,
									'trim'			=> TRUE,
								   ),
			'address_state'		=> Array(
									'type'		=> 'text',
									'default'	=> ifdef('ADDRESS_STATE_DEFAULT', ''),
									'label'		=> ifdef('ADDRESS_STATE_LABEL', 'State'),
								   ),
			'address_postcode'	=> Array(
									'type'			=> 'text',
									'width'			=> ifdef('ADDRESS_POSTCODE_WIDTH', 4),
									'allow_empty'	=> TRUE,
									'label'		=> ifdef('ADDRESS_POSTCODE_LABEL', 'Postcode'),
								   ),
			'created'			=> Array(
									'type'			=> 'datetime',
									'readonly'		=> true,
									'show_in_summary'	=> false,
									'label' => 'Created Date',
								   ),
			'creator'			=> Array(
									'type'			=> 'reference',
									'editable'		=> false,
									'references'	=> 'person',
									'show_in_summary'	=> false,
								   ),
			'history'			=> Array(
									'type'			=> 'serialise',
									'editable'		=> false,
									'show_in_summary'	=> false,
								   ),
		);
		if (defined('ADDRESS_STATE_OPTIONS') && constant('ADDRESS_STATE_OPTIONS') != '') {
			$fields['address_state']['type'] = 'select';
			$fields['address_state']['options'] = array_combine(
														explode(',', ADDRESS_STATE_OPTIONS),
														explode(',', ADDRESS_STATE_OPTIONS)
												  );
		} else if (!defined('STATE_LABEL') || constant('STATE_LABEL') == '') {
			// No state options and no state label -> hide the field
			$fields['address_state']['show_in_summary'] = false;
			$fields['address_state']['editable'] = false;
		}

		if (defined('ADDRESS_POSTCODE_REGEX')) {
			$fields['address_postcode']['regex'] = constant('ADDRESS_POSTCODE_REGEX');
		}
		return $fields;
	}

	function __construct($id=NULL) {
		parent::__construct($id);
		if (!$this->id) {
			$this->fields['status']['editable'] = false;
		}
	}


	function getInitSQL($table_name=NULL)
	{
		return Array(
			 "
			CREATE TABLE `family` (
			  `id` int(11) NOT NULL auto_increment,
			  `family_name` varchar(128) NOT NULL default '',
			  `address_street` varchar(255) NOT NULL default '',
			  `address_suburb` varchar(128) NOT NULL default '',
			  `address_state` varchar(64) NOT NULL default '',
			  `address_postcode` varchar(10) NOT NULL default '',
			  `home_tel` varchar(12) NOT NULL default '',
			  `status` varchar(64) NOT NULL default '',
			  `created` timestamp NOT NULL default CURRENT_TIMESTAMP,
			  `creator` int(11) NOT NULL default '0',
			  `history` text NOT NULL,
			  PRIMARY KEY  (`id`),
			  KEY `family_name` (`family_name`,`address_suburb`,`address_postcode`,`home_tel`,`status`)
			) ENGINE=InnoDB;
			",
			"CREATE TABLE family_photo (
				familyid INT NOT NULL,
				photodata MEDIUMBLOB NOT NULL,
				CONSTRAINT `famliyphotofamilyid` FOREIGN KEY (`familyid`) REFERENCES `family` (`id`),
				PRIMARY KEY (familyid)
			 ) ENGINE=InnoDB;
			"
		);
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
					<img src="?call=photo&familyid=<?php echo (int)$this->id; ?>" />
				</div>
				<?php
			}
		}

		parent::printForm($prefix, $fields);

		unset($this->fields['photo']);
	}

	function processForm($prefix='', $fields=NULL)
	{
		$res = parent::processForm($prefix, $fields);
		$this->_photo_data = Photo_Handler::getUploadedPhotoData('photo');
		return $res;
	}

	function printFieldValue($name, $value=NULL)
	{
		if ($name == 'members') {
			$this->printMemberList(array_get($this->_tmp, 'abbreviate_member_list', FALSE));
			return;
		}
		if (is_null($value)) $value = $this->getValue($name);
		if (($name == 'address_street') && MAP_LOOKUP_URL) {
			parent::printFieldValue($name, $value);
			if (!empty($value) && ($value == $this->values['address_street'])) {
				$url = MAP_LOOKUP_URL;
				foreach ($this->values as $k => $v) {
					if (is_string($v)) 	$url = str_replace('__'.strtoupper($k).'__', urlencode($v), $url);
				}
				print ' <a class="smallprint no-print map" href="'.$url.'">map</a>';
			}
		} else {
			parent::printFieldValue($name, $value);
		}
	}

	function printMemberList($abbreviated=NULL)
	{
		$persons = $this->getMemberData();
		$show_actions = !empty($this->id); // hide actions if this is a "draft" family

		if (isset($this->_tmp['show_member_callback'])) {
			call_user_func($this->_tmp['show_member_callback'], $persons);

		} else if (!$abbreviated) {
			?>
			<div style="float: left" id="member-details-container">
			<?php
			// full blown version
			$special_fields = Array('congregation');
			if (!empty($this->_tmp['member_list_special_fields'])) {
				$special_fields = $this->_tmp['member_list_special_fields'];
			}
			include 'templates/person_list.template.php';
			?>
			</div>
			<?php
			if ($GLOBALS['system']->featureEnabled('PHOTOS') && $this->id) {
				?>
				<div style="float: left; " id="family-photos-container">
				<?php
				foreach ($persons as $personid => $details) {
					?>
					<a href="?view=persons&personid=<?php echo (int)$personid; ?>"><img title="<?php echo ents($details['first_name'].' '.$details['last_name']); ?>" src="?call=photo&personid=<?php echo (int)$personid; ?>" /></a>
					<?php
				}
				?>
				</div>
				<?php
			}
		} else {
			// abbreviated version
			$GLOBALS['system']->includeDBClass('person');
			$dummy_person = new Person();
			?>
			<table>
			<?php
			foreach ($persons as $id => $person) {
				$dummy_person->populate($id, $person);
				$tr_class = ($person['status'] == 'archived') ? ' class="archived"' : '';
				?>
				<tr<?php echo $tr_class; ?>>
					<td class="nowrap"><a href="?view=persons&personid=<?php echo $id; ?>"><?php echo ents($dummy_person->toString()); ?></a></td>
					<td><?php $dummy_person->printFieldValue('gender'); ?></td>
					<td><?php $dummy_person->printFieldValue('age_bracketid'); ?></td>
				</tr>
				<?php
			}
			?>
			</table>
			<?php
		}
	}

	function printFieldInterface($name, $prefix='')
	{
		if ($name == 'photo') {
			?>
			<input type="file" name="photo" />
			<?php
			return;
		}

		parent::printFieldInterface($name);

		$postcode_url = POSTCODE_LOOKUP_URL;
		if (($name == 'address_suburb') && !empty($postcode_url)) {
			?>
			<a class="smallprint hidden-phone postcode-lookup" href="<?php echo POSTCODE_LOOKUP_URL; ?>" tabindex="-1">Look up <?php echo strtolower(defined('ADDRESS_POSTCODE_LABEL') ? constant('ADDRESS_POSTCODE_LABEL') : 'postcode'); ?></a>
			<?php
		}
	}


	function toString()
	{
		return $this->values['family_name'].' Family';
	}


	function getAdultMemberNames()
	{
		$adults = $GLOBALS['system']->getDBObjectData('person', Array('familyid' => $this->id, '(age_bracketid' => Age_Bracket::getAdults(), '!status' => 'archived'), 'AND', 'ab.rank, gender DESC');
		if (count($adults) == 1) {
			$adult = reset($adults);
			return $adult['first_name'].' '.$adult['last_name'];
		}
		$common_name = TRUE;
		foreach ($adults as $a) {
			if ($a['last_name'] != $this->getValue('family_name')) {
				$common_name = FALSE;
			}
		}
		if ($common_name) {
			$firsts = Array();
			foreach ($adults as $a) {
				$firsts[] = $a['first_name'];
			}
			$final = array_pop($firsts);
			return implode(', ', $firsts).' and '.$final.' '.$this->getValue('family_name');
		} else {
			$adult_names_arr = Array();
			foreach ($adults as $adult) {
				$adult_names_arr[] = $adult['first_name'].' '.$adult['last_name'];
			}
			$last = array_pop($adult_names_arr);
			return implode(', ', $adult_names_arr).' and '.$last;
		}
	}

	function getPostalAddress()
	{
		if (!empty($this->values['address_street']) && !empty($this->values['address_suburb']) && !empty($this->values['address_postcode'])) {
			return $this->values['address_street']."\n".$this->values['address_suburb'].' '.$this->values['address_state'].' '.$this->values['address_postcode'];
		} else {
			return '';
		}
	}


	function getInstancesQueryComps($params, $logic, $order)
	{
		$res = parent::getInstancesQueryComps($params, $logic, $order);
		$res['select'][] = 'GROUP_CONCAT(p.first_name ORDER BY ab.rank ASC, p.gender DESC SEPARATOR \', \') as members';
		if (array_get($params, '!status') == 'archived') {
			// If we are excluding archived families, exclude archived members too
			$res['from'] .= ' JOIN person p ON family.id = p.familyid AND p.status <> "archived"'; // Families with no visible members will be excluded
		} else {
			$res['from'] .= ' JOIN person p ON family.id = p.familyid'; // Families with no visible members will be excluded
		}
		$res['from'] .= ' JOIN age_bracket ab ON ab.id = p.age_bracketid ';
		$res['group_by'] = 'family.id';
		return $res;
	}

	function printSummaryWithMembers($abbreviate_member_list=TRUE, $member_data=NULL)
	{
		$this->_tmp['abbreviate_member_list'] = $abbreviate_member_list;
		if (!empty($member_data)) {
			$this->_tmp['members'] = $member_data;
			$this->_tmp['member_list_special_fields'] = array_diff(array_keys(reset($member_data)), Array('first_name', 'last_name', 'familyid', 'gender', 'status', 'age_bracketid', 'congregationid'));
		}
		$this->fields['members'] = Array('divider_before' => 1);
		parent::printSummary();
		unset($this->fields['members']);
	}

	function printCustomSummary($showMembersCallback)
	{
		// TODO: test this in the mmebers interface
		$this->fields['members'] = Array('divider_before' => 1);
		$this->_tmp['show_member_callback'] = $showMembersCallback;
		parent::printSummary();
		unset($this->_tmp['show_member_callback']);
		unset($this->fields['members']);
	}

	function getMemberData()
	{
		if (!isset($this->_tmp['members'])) {
			$this->_tmp['members'] = $GLOBALS['system']->getDBObjectData('person', Array('familyid' => $this->id), 'OR', 'ab.rank, gender DESC');
		}
		return $this->_tmp['members'];
	}


	function getAllEmailAddrs()
	{
		$all_emails = Array();
		foreach ($this->getMemberData() as $person) {
			$e = $person['email'];
			if (!empty($e)) $all_emails[] = $person['first_name'].' '.$person['last_name'].' <'.$person['email'].'>';
		}
		return $all_emails;
	}

	function setValue($name, $value)
	{
		if ($name == 'address_postcode') {
			$value = strtoupper($value); // for the UK
		}
		return parent::setValue($name, $value);
	}

	function create()
	{
		if (parent::create()) {
			$this->savePhoto();
			return TRUE;
		}
		return FALSE;
	}

	function save($update_members=TRUE)
	{
		$msg = '';
		if ($update_members) {
			if (!empty($this->_old_values['status'])) {
				if ($this->getValue('status') == 'archived') {
					// Status has just been changed to 'archived' so archive members too
					$members = $this->getMemberData();
					if (!empty($members)) {
						$GLOBALS['system']->includeDBClass('person');
						$member = new Person();
						$all_members_archived = TRUE;
						foreach ($members as $id => $details) {
							$member->populate($id, $details);
							if ($member->canAcquireLock()) {
								$member->acquireLock();
								$member->setValue('status', 'archived');
								$member->save(FALSE);
								$member->releaseLock();
							} else {
								$all_members_archived = FALSE;
							}
						}
						if ($all_members_archived) {
							$msg = 'The '.count($members).' members of the family have also been archived';
						} else {
							$msg = 'Not all members of the family could be accordingly archived because another user holds the lock';
						}
					}
				} else if ($this->_old_values['status'] == 'archived') {
					// Status has just been changed from archived to something else
					$msg = 'NB Members of the family will need to be de-archived separately';
				}

			}
			if (!empty($this->_old_values['family_name'])) {
				// Family name has changed
				// We update all the members' last names to match if
				// (a) there is only one member, or
				// (b) all members' current last name = the old family name
				$members = $this->getMemberData();
				if (count($members) == 1) {
					$member = $GLOBALS['system']->getDBObject('person', key($members));
					if ($member->canAcquireLock()) {
						$member->acquireLock();
						$member->setValue('last_name', $this->getValue('family_name'));
						$member->save(FALSE);
						$member->releaseLock();
						$msg = 'The last name of the family\'s one member has also been set to "'.$this->getValue('family_name').'"';
					} else {
						$msg = 'The family\'s one member could not be updated accordingly because another user holds the lock';
					}
				} else if (!empty($members)) {
					$members_all_have_family_name = TRUE;
					foreach ($members as $id => $member) {
						if ($member['last_name'] != $this->_old_values['family_name']) {
							$members_all_have_family_name = FALSE;
							break;
						}
					}
					if ($members_all_have_family_name) {
						$all_members_updated = TRUE;
						$GLOBALS['system']->includeDBClass('person');
						$member = new Person();
						foreach ($members as $id => $details) {
							$member->populate($id, $details);
							if ($member->canAcquireLock()) {
								$member->acquireLock();
								$member->setValue('last_name', $this->getValue('family_name'));
								$member->save(FALSE);
								$member->releaseLock();
							} else {
								$all_members_updated = FALSE;
							}
						}
						if ($all_members_updated) {
							$msg = 'Each family member\'s last name has also been set to "'.$this->getValue('family_name').'"';
						} else {
							$msg = 'Not all family members could be updated with the new last name because another user holds the lock';
						}
					} else {
						$msg = 'Family members have not been updated because they already have different last names';
					}
				}
			}
		}
		$res = parent::save();
		$this->savePhoto();
		if ($msg) add_message($msg);
		return $res;
	}

	private function savePhoto() {
		$db =& $GLOBALS['db'];
		if ($this->_photo_data) {
			$SQL = 'REPLACE INTO family_photo (familyid, photodata)
					VALUES ('.(int)$this->id.', '.$db->quote($this->_photo_data).')';
			$res = $db->query($SQL);
		}
	}

	/* Find a family that looks like a duplicate of this one - if it has the same family name and a member with the same name
	*/
	public function findSimilarFamilies()
	{
		$res = Array();
		$same_names = $GLOBALS['system']->getDBObjectData('family', Array('family_name' => $this->getValue('family_name'), '!id' => $this->id), 'AND');
		if (!empty($same_names)) {
			foreach ($same_names as $familyid => $fdata) {
				$family = $GLOBALS['system']->getDBObject('family', $familyid);
				foreach ($family->getMemberData() as $dup_member) {
					foreach ($this->getMemberData() as $my_member) {
						if (($my_member['first_name'] == $dup_member['first_name'])
							&& ($my_member['last_name'] == $dup_member['last_name'])
							) {
								$res[] = $family;
							}
					}
				}
			}
		}
		return $res;
	}


	public static function getFamilyDataByMemberIDs($member_ids)
	{
		$quoted_ids = implode(',', array_map(Array($GLOBALS['db'], 'quote'), $member_ids));
		$sql = '
			SELECT f.*,
			allmembers.names as members,
			IFNULL(adultmembers.names, "") as adult_members,
			GROUP_CONCAT(p.first_name ORDER BY ab.rank ASC, p.gender, p.id DESC SEPARATOR ",") as selected_firstnames,
			GROUP_CONCAT(p.last_name ORDER BY ab.rank ASC, p.gender, p.id DESC SEPARATOR ",") as selected_lastnames
			FROM family f
			JOIN person p ON f.id= p.familyid
		    JOIN age_bracket ab ON ab.id = p.age_bracketid
			JOIN (
			   select f.id as familyid, GROUP_CONCAT(p.first_name ORDER BY ab.rank ASC, p.gender DESC SEPARATOR ", ") as names
			   FROM person p JOIN family f on p.familyid = f.id
			   JOIN age_bracket ab ON ab.id = p.age_bracketid
			   WHERE p.status <> "archived"
			   GROUP BY f.id
			) allmembers ON allmembers.familyid = f.id
			LEFT JOIN (
			   select f.id as familyid, GROUP_CONCAT(p.first_name ORDER BY ab.rank ASC, p.gender DESC SEPARATOR ", ") as names
			   FROM person p 
			   JOIN family f on p.familyid = f.id
			   JOIN age_bracket ab ON ab.id = p.age_bracketid
			   WHERE ab.is_adult and p.status <> "archived"
			   GROUP BY f.id
			) adultmembers ON adultmembers.familyid = f.id
			WHERE p.id IN ('.$quoted_ids.')
			GROUP BY f.id
			ORDER BY f.family_name';
		$res = $GLOBALS['db']->queryAll($sql, NULL, NULL, TRUE);
		return $res;
	}



	public static function printSingleFinder($name, $currentval=NULL)
	{
		$currentid = 0;
		$currentname = '';
		if (is_int($currentval) && $currentval) {
			$currentid = $currentval;
			$family = $GLOBALS['system']->getDBObject('family', $currentid);
			if ($family) {
				$currentname = $family->toString();
			} else {
				$currentid = 0;
			}
		} else if (is_array($currentval)) {
			list($currentid, $currentname) = each ($currentval);
		}
		$displayname = $currentid ? $currentname.' (#'.$currentid.')' : '';
		?>
		<input type="text" placeholder="Search families" id="<?php echo $name; ?>-input" class="family-search-single" value="<?php echo ents($displayname); ?>" />
		<input type="hidden" name="<?php echo $name; ?>" value="<?php echo $currentid; ?>" />
		<?php
	}

}
