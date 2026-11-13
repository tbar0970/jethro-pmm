<?php
/*
 * JETHRO PMM
 *
 * This file is part of Jethro PMM - https://github.com/tbar0970/jethro-pmm
 *
 * Jethro PMM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jethro PMM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jethro PMM.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Headless core of the CSV person importer.
 *
 * Parses a CSV file into a plan (new families, new persons, family and person updates,
 * group memberships) and then applies that plan in a single transaction. The admin UI
 * (View_Admin__Import) renders and drives it; scripts/import_persons.php drives it
 * from cron.
 *
 * See docs/docs/developer/reference/import.md
 */
class Person_Importer
{
	/**
	 * @var array<string,string> Headers accepted as aliases for the canonical column names.
	 * Form exports commonly use the shorter names.
	 */
	private static $_column_aliases = [
		'mobile' => 'mobile_tel',
		'notes' => 'note',
	];

	/**
	 * @var list<string> Columns that belong to the family rather than the person.
	 */
	private static $_family_fields = ['family_name', 'home_tel', 'address_street', 'address_suburb', 'address_state', 'address_postcode'];

	/**
	 * @var array<string,mixed> Group selection, matching rules and overwrite policy.
	 */
	private $_options = [];

	/**
	 * @var array<string,mixed> Parsed plan. Keys match the old $_SESSION['import'] layout.
	 */
	private $_plan = [];

	/**
	 * @var array<int,list<string>> Errors captured per row during parse().
	 */
	private $_captured_errors = [];

	/**
	 * @var int
	 */
	private $_error_index = 0;

	/**
	 * @var array<int,list<string>> Errors captured during the last parse().
	 */
	private $_row_errors = [];

	/**
	 * @var list<array{text:string,type:string}> Messages for the caller to display or log.
	 */
	private $_messages = [];

	/**
	 * @var array<string,Person_Group> Groups created or matched by this import, keyed by string key.
	 */
	private $_groups = [];

	/**
	 * @var Person_Group|false|null
	 */
	private $_total_group = NULL;

	/**
	 * @var Family
	 */
	private $_dummy_family;

	/**
	 * @var Person
	 */
	private $_dummy_person;

	/**
	 * @var int Staff account ID used to stamp records created outside a web request.
	 */
	private $_creator_id = 0;

	/**
	 * @param array<string,mixed> $options Recognised keys: groupid, new_group_name,
	 *        new_group_categoryid, overwrite_existing, match_existing, match_email,
	 *        match_mobile_tel, creator_userid.
	 */
	public function __construct($options = [])
	{
		$this->_options = array_merge([
			'groupid' => 0,
			'new_group_name' => '',
			'new_group_categoryid' => 0,
			'overwrite_existing' => 1,
			'match_existing' => FALSE,
			'match_email' => FALSE,
			'match_mobile_tel' => FALSE,
			'creator_userid' => 0,
		], $options);
	}

	/**
	 * @return array<string,mixed> The plan built by parse().
	 */
	public function getPlan()
	{
		return $this->_plan;
	}

	/**
	 * Use a plan built elsewhere (e.g. restored from a session) for apply().
	 *
	 * @param array<string,mixed> $plan
	 * @return void
	 */
	public function setPlan($plan)
	{
		$this->_plan = $plan;
	}

	/**
	 * @return array<int,list<string>> Per-row errors from the last parse().
	 */
	public function getRowErrors()
	{
		return $this->_row_errors;
	}

	/**
	 * @return list<array{text:string,type:string}> Messages from the last parse().
	 */
	public function getMessages()
	{
		return $this->_messages;
	}

	/**
	 * Parse a CSV file into the plan.
	 *
	 * @param string $datafile Path to a readable CSV file
	 * @param string $separator Field separator, e.g. ',' or "\t"
	 * @return bool TRUE when the file can be applied; FALSE when it must be rejected
	 */
	public function parse($datafile, $separator = ',')
	{
		$GLOBALS['system']->includeDBClass('family');
		$GLOBALS['system']->includeDBClass('person');
		$GLOBALS['system']->includeDBClass('person_group');
		$GLOBALS['system']->includeDBClass('congregation');
		$GLOBALS['system']->includeDBClass('person_status');

		$this->_messages = [];
		$this->_row_errors = [];
		$this->_captured_errors = [];
		$this->_plan = [
			'overwrite_existing' => $this->_options['overwrite_existing'],
			'groupid' => (int)$this->_options['groupid'],
			'new_group_name' => $this->_options['new_group_name'],
			'new_group_categoryid' => (int)$this->_options['new_group_categoryid'],
			'matched_groups' => [],
			'new_groups' => [],
			'new_families' => [],
			'total_new_persons' => 0,
			'new_persons' => [],
			'person_updates' => [],
			'family_updates' => [],
			'used_custom_fields' => [],
		];

		$this->_dummy_family = new Family();
		$this->_dummy_person = new Person();
		$family_fields = self::$_family_fields;

		$custom_fields = [];
		foreach (Person::getCustomFields() as $id => $detail) {
			$custom_fields[self::_stringToKey($detail['name'])] = $id;
		}

		$fp = fopen($datafile, 'r');
		if (!$fp) {
			$this->_addMessage(_("There was a problem reading your CSV file."), 'error');
			return FALSE;
		}
		$map = fgetcsv($fp, 0, $separator, '"', "");
		$sample_header = self::getSampleHeader();
		foreach ($map as $k => $v) {
			if ($v == '') continue;
			if (0 !== strpos($v, 'groupif')) {
				$v = self::_stringToKey($v);
				if ($v == 'id') $v = 'personid'; // Jethro's CSV exports head the person ID column "ID"
				$v = array_get(self::$_column_aliases, $v, $v); // form exports use shorter column names
				if (!in_array($v, $sample_header)) {
					$this->_addMessage("Unrecognised column \"".$v.'" will be ignored', 'warning');
				}
			}
			$map[$k] = $v;
			if ($cfid = array_get($custom_fields, $v)) {
				$this->_plan['used_custom_fields'][$cfid] = $v;
			}
		}
		$has_required_cols = TRUE;
		foreach (self::getSampleHeader(TRUE) as $hcol) {
			if (!in_array($hcol, $map)) {
				$this->_addMessage("Your file must contain a $hcol column", 'error');
				$has_required_cols = FALSE;
			}
		}
		if (!$has_required_cols) {
			fclose($fp);
			return FALSE;
		}

		$current_new_family_data = [];
		$current_existing_family_data = NULL;
		$has_family_name_column = in_array('family_name', $map, TRUE);

		$i = 0;
		while ($rawrow = fgetcsv($fp, 0, $separator, '"', "")) {
			$i++;
			$row = [];
			foreach ($map as $index => $fieldname) {
				$cell_group = NULL; // group name relevant to this cell's data
				if ($fieldname == 'group') {
					// Cell value is the exact group name
					if (strlen($g = array_get($rawrow, $index, ''))) {
						$cell_group = $g;
					}
				} else if (preg_match("/groupif(\{([^}]+)\})?(!\{([^}]+)\})?\[([^]]+)\]/m", $fieldname, $matches)) {
					$search_phrase = strtolower(array_get($matches, 2));
					$exclude_phrase = strtolower(array_get($matches, 4));
					$test_val = strtolower(array_get($rawrow, $index, ''));
					$include = FALSE;
					if (strlen($search_phrase) == 0) {
						// groupif[toilet cleaners] means put them in 'toilet cleaners' if the cell has any non-empty value
						$include = (strlen($test_val) > 0);
					} else {
						// groupif{cleaning}[toilet cleaners] - if the cell contains 'cleaning', put them in 'toilet cleaners'
						$include = (FALSE !== strpos($test_val, $search_phrase));
					}
					if ($exclude_phrase) {
						// groupif{men's ministry}!{women's}[men's ministry team] - if it contains 'men's ministry' but NOT 'women's ...
						if (FALSE !== strpos($test_val, $exclude_phrase)) {
							$include = FALSE;
						}
					}
					if ($include) {
						$cell_group = $matches[5];
					}

				} else if ($fieldname == 'note') {
					if (strlen($n = array_get($rawrow, $index, ''))) $row['_note'] = $n;
				} else {
					$row[$fieldname] = trim(array_get($rawrow, $index, ''));
				}
				if ($cell_group) { // there is a group to add the person to, based on this cell
					$cell_group = trim($cell_group);
					$row['_groups'][] = $cell_group;
					$gk = self::_stringToKey($cell_group);
					if (!isset($this->_plan['matched_groups'][$gk])) {
						if ($gp = Person_Group::findByName($cell_group)) {
							$this->_plan['matched_groups'][$gk] = $gp->id;
						} else {
							$this->_plan['new_groups'][$gk] = $cell_group;
						}
					}

				}
			}

			if (!$has_family_name_column && !empty($row['last_name'])) {
				// Form exports usually carry a surname only; use it for the family too.
				$row['family_name'] = $row['last_name'];
			}

			if ($this->_isEmptyRow($row)
					|| (empty($current_existing_family_data) && $this->_isNewFamily($row, $current_new_family_data))
					|| (empty($current_new_family_data) && $this->_isNewFamily($row, $current_existing_family_data))
			) {
				// Start a new family for the next row
				if (!empty($current_new_family_data['members'])) {
					$this->_plan['new_families'][] = $current_new_family_data;
				}
				$current_new_family_data = [];
				$current_existing_family_data = [];
			}
			if ($this->_isEmptyRow($row)) {
				continue;
			}

			$ei = $i;
			$name = array_get($row, 'first_name', '').' '.array_get($row, 'last_name', '');
			if (strlen(trim($name))) $ei .= ' ('.$name.')';
			$this->_captureErrors($ei);

			if (!empty($row['congregation'])) {
				$row['congregationid'] = Congregation::findByName($row['congregation']);
			}

			$family_row = $this->_filterArrayKeys($row, $family_fields);
			$person_row = $this->_arrayDiffAssoc($row, $family_row);

			if ($existingPerson = $this->_findExistingPerson($row)) {
				// SCENARIO 1 - WE ARE UPDATING AN EXISTING PERSON (AND FAMILY)

				// Try updating the person fields
				$person_row['first_name'] = $existingPerson->getValue('first_name'); // avoid case munging
				$person_row['last_name'] = $existingPerson->getValue('last_name'); // avoid case munging
				$existingPerson->fromCsvRow($person_row, $this->_plan['overwrite_existing']);

				// Update the family fields (looking out for family import data already in play)
				if (!empty($current_new_family_data['members'])) {
					// a previous import row started creating a new family.
					// put the existing family members in the list of new members for this existing family
					foreach ($current_new_family_data['members'] as $member) {
						$member['familyid'] = $existingPerson->getValue('familyid');
						$this->_plan['new_persons'][] = $member;
					}
					unset($current_new_family_data['members']);

					// Grab any family data supplied in the previous rows
					$this->_pushIntoArray($current_new_family_data, $family_row);
					$current_new_family_data = [];
				}

				// Try updating the family fields
				$family_row['status'] = 'current';
				$familyObj = $existingPerson->getFamily();
				$family_row['family_name'] = $familyObj->getValue('family_name'); // avoid case munging
				$familyObj->fromCsvRow($family_row, $this->_plan['overwrite_existing']);
				if (empty($current_existing_family_data) || ($existingPerson->getValue('familyid') != $current_existing_family_data['id'])) {
					$current_existing_family_data = $family_row + ['id' => $familyObj->id];
				} else {
					$this->_pushIntoArray($family_row, $current_existing_family_data);
				}

				if (!$this->_haveErrors($i)) {
					if (isset($this->_plan['person_updates'][$existingPerson->id])) {
						// a previous import row also matched this same person.
						// Later rows take precedence over earlier rows, but data should be combined where possible (eg groups).
						$already_groups = array_get($this->_plan['person_updates'][$existingPerson->id], '_groups', []);
						$this->_pushIntoArray($this->_plan['person_updates'][$existingPerson->id], $person_row);
						$this->_plan['person_updates'][$existingPerson->id] = $person_row;
						if (!empty($person_row['_groups'])) {
							$this->_plan['person_updates'][$existingPerson->id]['_groups'] = array_unique(array_merge($already_groups, $person_row['_groups']));
						}

					} else {
						$this->_plan['person_updates'][$existingPerson->id] = $person_row;
					}
					$this->_plan['family_updates'][$existingPerson->getValue('familyid')] = $current_existing_family_data;
				}
			} else {

				// SCENARIO 2 - WE ARE CREATING A NEW PERSON,

				if (empty($person_row['status'])) {
					// A blank status is allowed in the file; new persons get the default status.
					$person_row['status'] = Person_Status::getDefault();
				}

				// Try pulling details into person object - will throw errors on bad data
				$this->_dummy_person->reset();
				$this->_dummy_person->setValue('familyid', '-1');
				$this->_dummy_person->fromCsvRow($person_row);

				$this->_dummy_family->reset();
				$this->_dummy_family->setValue('status', 'current');
				if (!empty($current_existing_family_data)) {
					// 2A) THE NEW PERSON IS TO BE ADDED TO AN EXISTING FAMILY

					// Add to the existing family data any extra details supplied in this row
					$this->_pushIntoArray($family_row, $current_existing_family_data);

					// Try setting values - will throw errors if bad data
					$this->_dummy_family->fromCsvRow($current_existing_family_data);

					if (!$this->_haveErrors($i)) {
						$person_row['familyid'] = $current_existing_family_data['id'];
						$this->_plan['new_persons'][] = $person_row;
						$this->_plan['family_updates'][$current_existing_family_data['id']] = $current_existing_family_data;
					}
				} else if (!empty($current_new_family_data)) {
					// 2B) THE NEW PERSON IS TO BE ADDED TO A NEW FAMILY ALREADY STARTED IN THIS IMPORT

					// Add to the new family data any extra details supplied in this row
					$this->_pushIntoArray($family_row, $current_new_family_data);

					// Try setting values - will throw errors if bad data
					$this->_dummy_family->fromCsvRow($current_new_family_data);

					if (!$this->_haveErrors($i)) {
						$current_new_family_data['members'][] = $person_row;
					}


				} else {
					// 2C) THE NEW PERSON IS TO BE ADDED TO A WHOLE NEW FAMILY NOT YET SEEN IN THIS IMPORT

					$current_new_family_data = $family_row;

					// Try setting values - will throw errors if bad data
					$this->_dummy_family->fromCsvRow($current_new_family_data);

					if (!$this->_haveErrors($i)) {
						$current_new_family_data = $family_row;
						$current_new_family_data['members'][] = $person_row;
					}
				}
			}
		}

		// Add any outstanding new family to the pile
		if (!empty($current_new_family_data['members'])) {
			$this->_plan['new_families'][] = $current_new_family_data;
		}

		foreach ($this->_plan['new_families'] as $f) {
			$this->_plan['total_new_persons'] += count($f['members']);
		}

		fclose($fp);
		$this->_row_errors = $this->_getErrors();
		return empty($this->_row_errors);
	}

	/**
	 * Apply the plan in a single transaction.
	 *
	 * @param callable|null $progress function(int $done, int $total): void
	 * @return array{ok:bool,errors:array<int,list<string>>,group_id:int|null}
	 */
	public function apply($progress = NULL)
	{
		ini_set('memory_limit', '256M');
		ini_set('max_execution_time', 60*10);

		$GLOBALS['system']->includeDBClass('family');
		$GLOBALS['system']->includeDBClass('person');
		$GLOBALS['system']->includeDBClass('person_group');
		$GLOBALS['system']->includeDBClass('person_note');

		$this->_groups = [];
		$this->_total_group = NULL;
		$this->_creator_id = (int)$GLOBALS['user_system']->getCurrentPerson('id');
		if (!$this->_creator_id) {
			$this->_creator_id = (int)$this->_options['creator_userid'];
		}

		$GLOBALS['system']->doTransaction('BEGIN');
		$done = 0;
		$todo = $this->_plan['total_new_persons']
			+ count($this->_plan['new_persons'])
			+ count($this->_plan['family_updates'])
			+ count($this->_plan['person_updates']);

		// Create new groups and load up existing groups to be ready to receive persons.
		foreach ($this->_plan['new_groups'] as $key => $name) {
			$g = new Person_Group();
			$g->setValue('name', $name);
			$g->setValue('categoryid', NULL);
			if (!$g->create()) {
				$GLOBALS['system']->doTransaction('ROLLBACK');
				return ['ok' => FALSE, 'errors' => [0 => ["Could not create group $name"]], 'group_id' => NULL];
			}
			$this->_groups[$key] = $g;
		}
		foreach ($this->_plan['matched_groups'] as $key => $groupid) {
			$this->_groups[$key] = new Person_Group($groupid);
		}

		$this->_captureErrors();

		foreach ($this->_plan['new_families'] as $familyrow) {
			$members = $familyrow['members'];
			unset($familyrow['members']);
			$family = new Family();
			$family->fromCSVRow($familyrow);
			$this->_stampCreator($family);
			if (!$family->create()) {
				trigger_error('Family: '.$familyrow['family_name']." not created");
				continue;
			}
			foreach ($members as $member) {
				$member['familyid'] = $family->id;
				$person = new Person();
				$person->fromCSVRow($member);
				$this->_stampCreator($person);
				if (!$person->create()) {
					trigger_error("Person: ".$member['first_name'].' '.$member['last_name'].' not created', E_USER_WARNING);
					continue;
				}
				$this->_finalisePerson($person, $member); // adds note and group
				if ($progress) call_user_func($progress, $done++, $todo);
				unset($person);
			}

		}
		foreach ($this->_plan['new_persons'] as $row) {
			if (!isset($row['gender'])) $row['gender'] = 'Unknown';
			$person = new Person();
			$person->fromCSVRow($row);
			$this->_stampCreator($person);
			if (!$person->create()) {
				trigger_error("Person: ".$person['first_name'].' '.$person['last_name'].' not created', E_USER_WARNING);
				continue;
			}
			$this->_finalisePerson($person, $row); // adds note and group
			if ($progress) call_user_func($progress, $done++, $todo);
			unset($person);
		}
		foreach ($this->_plan['family_updates'] as $familyid => $familyrow) {
			$family = new Family($familyid);
			if (!$family->acquireLock()) {
				trigger_error("Could not update ".$familyrow['family_name'].' because another user is editing it. Please try again later.', E_USER_WARNING);
				continue;
			}
			$family->fromCSVRow($familyrow, $this->_plan['overwrite_existing']);
			if (!$family->save()) {
				trigger_error('Family: '.$familyrow['family_name']." not updated");
			}
			$family->releaseLock();
			if ($progress) call_user_func($progress, $done++, $todo);
			unset($family);
		}
		foreach ($this->_plan['person_updates'] as $personid => $row) {
			$person = new Person($personid);
			if (!$person->acquireLock()) {
				trigger_error("Could not update ".$person->toString().' because another user is editing them. Please try again later.', E_USER_WARNING);
				continue;
			}
			$person->fromCSVRow($row, $this->_plan['overwrite_existing']);
			if (!$person->save()) {
				trigger_error("Person: ".$row['first_name'].' '.$row['last_name'].' not created', E_USER_WARNING);
				continue;
			}
			$this->_finalisePerson($person, $row); // adds note and group
			if ($progress) call_user_func($progress, $done++, $todo);
		}

		$errors = $this->_getErrors();
		if ($errors) {
			$GLOBALS['system']->doTransaction('ROLLBACK');
			return ['ok' => FALSE, 'errors' => $errors, 'group_id' => NULL];
		}

		$GLOBALS['system']->doTransaction('COMMIT');
		$total_group = $this->_getTotalGroup();
		return ['ok' => TRUE, 'errors' => [], 'group_id' => $total_group ? $total_group->id : NULL];
	}

	/**
	 * Column headers recognised by the importer, in sample-file order.
	 * personid is optional; it leads the full header so it lines up with the "ID" column of Jethro's CSV exports.
	 * See docs/docs/developer/reference/import.md
	 *
	 * @param bool $required_fields_only Whether to return only the columns every import file must contain
	 * @return list<string>
	 */
	public static function getSampleHeader($required_fields_only=FALSE)
	{
		$header = [
			'family_name',
			'last_name',
			'first_name',
			'congregation',
			'status',
			'age_bracket',
		];
		if ($required_fields_only) {
			// family_name falls back to last_name, blank status uses the default status for new
			// persons, and congregation may be blank for statuses that allow it.
			return ['last_name', 'first_name', 'age_bracket'];
		}
		$header = array_merge(['personid'], $header, [
			'gender',
			'email',
			'mobile_tel',
			'work_tel',
			'home_tel',
			'address_street',
			'address_suburb',
			'address_state',
			'address_postcode',
		]);
		$custom_fields = Person::getCustomFields();
		foreach ($custom_fields as $field) {
			$header[] = self::_stringToKey($field['name']);
		}
		$header[] = 'note';
		$header[] = 'group';
		$header[] = 'group';
		$header[] = 'group';
		return $header;
	}

	//////////////////////////////// PRIVATE HELPERS ////////////////////////////////

	/**
	 * @param string $text
	 * @param string $type 'error'|'warning'
	 * @return void
	 */
	private function _addMessage($text, $type = 'warning')
	{
		$this->_messages[] = ['text' => $text, 'type' => $type];
	}

	/**
	 * Stamp created records with the acting staff account. Without this, the
	 * NOT NULL creator columns receive NULL when no staff user is logged in
	 * (e.g. cron), and the abstract_note FK rejects it.
	 *
	 * @param DB_Object $obj
	 * @return void
	 */
	private function _stampCreator($obj)
	{
		$obj->setValue('creator', $this->_creator_id);
	}

	private function _finalisePerson($person, $row)
	{
		$text = array_get($row, '_note', '');
		if (strlen($text)) {
			$note = new Person_Note();
			$note->setValue('subject', 'Import note');
			$note->setValue('details', $text);
			$note->setValue('personid', $person->id);
			$this->_stampCreator($note);
			$note->createIfNew();
		}

		if (!empty($row['_groups'])) {
			foreach ($row['_groups'] as $groupname) {
				$gkey = self::_stringToKey($groupname);
				$this->_groups[$gkey]->addMember($person->id);
			}
		}

		if ($total_group = $this->_getTotalGroup()) {
			$total_group->addMember($person->id);
		}
	}

	/**
	 * @return Person_Group|false Group that all new/updated persons should be added to
	 */
	private function _getTotalGroup()
	{
		if ($this->_total_group === NULL) {
			$this->_total_group = FALSE;
			if ($this->_plan['groupid']) {
				$this->_total_group = new Person_Group($this->_plan['groupid']);
			} else if ($this->_plan['new_group_name']) {
				$this->_total_group = new Person_Group();
				$this->_total_group->setValue('name', $this->_plan['new_group_name']);
				if (!empty($this->_plan['new_group_categoryid'])) {
					$this->_total_group->setValue('categoryid', $this->_plan['new_group_categoryid']);
				}
				$this->_total_group->create();
			}
		}
		return $this->_total_group;
	}

	private function _filterArrayKeys($dataArray, $desiredKeys)
	{
		foreach ($dataArray as $k => $v) {
			if (!in_array($k, $desiredKeys)) unset($dataArray[$k]);
		}
		return $dataArray;
	}

	/**
	 * Returns an array with all the key-value pairs from $x where the key DOESN'T exist in $y
	 * @param array $x
	 * @param array $y
	 * @return array
	 */
	private function _arrayDiffAssoc($x, $y)
	{
		foreach ($x as $k => $v) {
			if (array_key_exists($k, $y)) unset($x[$k]);
		}
		return $x;
	}

	private function _pushIntoArray($from, &$to)
	{
		foreach ($from as $k => $v) {
			if (is_string($v) && strlen($v) && array_key_exists($k, $to) && !strlen($to[$k])) {
				// $to has this element but it's empty
				$to[$k] = $v;
			}
		}
	}

	private static function _stringToKey($string)
	{
		return str_replace(' ', '_', strtolower($string));
	}

	private function _isEmptyRow($row)
	{
		foreach ($row as $x) {
			if (!empty($x)) return FALSE;
		}
		return TRUE;
	}

	private function _isNewFamily($row, $current_family)
	{
		foreach (['family_name', 'address_street', 'address_suburb', 'address_state', 'home_tel'] as $field) {
			if (!empty($row[$field]) && !empty($current_family[$field])) {
				if ($field == 'home_tel') {
					if (preg_replace('/[^0-9]/', '', $row[$field]) != preg_replace('/[^0-9]/', '', $current_family[$field])) {
						return TRUE;
					}
				} else {
					if (strtolower($row[$field]) != strtolower($current_family[$field])) {
						return TRUE;
					}
				}
			}
		}
		return FALSE;
	}

	/**
	 * Look for an existing person record matching the supplied import row.
	 *
	 * A non-blank personid matches exactly that person (archived or not), bypassing name/email/mobile checks.
	 * Otherwise, persons are matched by first & last name, subject to the match_email and match_mobile_tel options.
	 * Nothing is matched unless match_existing is set.
	 * Problems with a supplied personid are raised as E_USER_WARNING, which the caller captures as row errors.
	 * See docs/docs/developer/reference/import.md
	 *
	 * @param array<string,string> $row Data from import CSV
	 * @return Person|null
	 */
	private function _findExistingPerson($row)
	{
		$idValue = trim(array_get($row, 'personid', ''));
		if (strlen($idValue)) {
			if (!ctype_digit($idValue) || !(int)$idValue) {
				trigger_error('Invalid person ID "'.$idValue.'"', E_USER_WARNING);
				return NULL;
			}
			if (empty($this->_options['match_existing'])) {
				trigger_error('Person ID '.$idValue.' was supplied but updating existing persons is not enabled', E_USER_WARNING);
				return NULL;
			}
			$person = $GLOBALS['system']->getDBObject('person', (int)$idValue);
			if (!$person) {
				trigger_error('There is no person with ID '.$idValue, E_USER_WARNING);
			}
			return $person;
		}
		if (empty($this->_options['match_existing'])) return NULL;
		$params = [
			'first_name' => $row['first_name'],
			'last_name' => $row['last_name'],
		];
		$matches = $GLOBALS['system']->getDBObjectData('person', $params, 'AND');
		$row['mobile_tel'] = preg_replace('/[^0-9]/', '', array_get($row, 'mobile_tel', ''));
		foreach (['email', 'mobile_tel'] as $fieldName) {
			if (!empty($this->_options['match_'.$fieldName]) && strlen(array_get($row, $fieldName, ''))) {
				foreach ($matches as $id => $details) {
					if (strlen($details[$fieldName]) && strtolower($row[$fieldName]) != strtolower($details[$fieldName])) {
						// existing and imported values are both non-blank and are different
						unset($matches[$id]);
					}
				}
			}
		}
		if (count($matches) > 1) {
			$this->_addMessage('"'.$row['first_name'].' '.$row['last_name'].'" could not be matched to an existing record because there are already several persons with that name', 'warning');
		}
		if (count($matches) == 1) {
			reset($matches);
			return $GLOBALS['system']->getDBObject('person', key($matches));
		}

		return NULL;
	}

	private function _captureErrors($i = 0)
	{
		if (empty($this->_captured_errors)) $this->_captured_errors = [];
		$this->_error_index = $i;
		set_error_handler([$this, '_handleError']);
	}

	/**
	 * Error handler used while applying the import. Public so set_error_handler() can call it.
	 *
	 * @return void
	 */
	public function _handleError($errno, $errstr, $errfile, $errline)
	{
		if (in_array($errno, [E_USER_NOTICE, E_USER_WARNING])) {
			$this->_captured_errors[$this->_error_index][] = $errstr;
		} else {
			$GLOBALS['system']->_handleError($errno, $errstr, $errfile, $errline);
		}
	}

	private function _getErrors()
	{
		$res = $this->_captured_errors;
		$this->_captured_errors = [];
		restore_error_handler();
		return $res;
	}

	private function _haveErrors($i)
	{
		return !empty($this->_captured_errors[$i]);
	}
}
