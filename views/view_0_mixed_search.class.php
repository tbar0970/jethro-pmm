<?php
/**
 * Search across persons, families, groups and reports.
 *
 * The search form sits at the top of the page and searches live: as the user types, Datastar
 * fetches `?view=_mixed_search&fragment=results` with the form's fields and morphs the
 * `#mixed-search-results` region. Submitting the form (Enter) is a normal page load, which jumps
 * straight to the record when there is exactly one match.
 *
 * @see docs/docs/developer/reference/view-fragments.md
 */
class View__Mixed_Search extends View
{
	/** Id of the results region, the morph target of the `results` fragment. */
	const RESULTS_ID = 'mixed-search-results';

	private $_family_data = Array();
	private $_person_data = Array();
	private $_group_data = Array();
	private $_report_data = Array();
	/** The trimmed search term ('' when nothing was searched). */
	private string $_search = '';
	/** The validated "Search in" choice (a key of getSearchInOptions()). */
	private string $_search_in = '*';
	/** @var array{p: array<int, list<array{0: string, 1: string}>>, f: array<int, list<array{0: string, 1: string}>>} type => id => [field label, snippet HTML] for non-name fields that matched */
	private array $_matches = Array('p' => Array(), 'f' => Array());

	function processView()
	{
		$this->loadResults();
		if (is_null(self::requestedFragment())) {
			// Only a full page load jumps to a single match; live (fragment) results just show it.
			$this->redirectToSingleResult();
		}
		$this->findMatchedFields();
	}

	/**
	 * For each person and family result, work out which non-name field(s) matched the search, so
	 * the row can show "Field: …matched text…" under the name. Only the fields the chosen
	 * "Search in" option actually searched are checked, using the same matching rule as the query:
	 * exact for phone and email, start-of-word for custom fields and address, anywhere for remarks.
	 * Name matches are not annotated.
	 */
	private function findMatchedFields(): void
	{
		$search = $this->_search;
		$in = $this->_search_in;
		if ($search === '') return;
		$tel = self::localisePhone(preg_replace('/[^0-9]/', '', $search));
		$checkPhone = ($tel !== '') && in_array($in, Array('*', 'phone'));

		if ($this->_person_data) {
			$GLOBALS['system']->includeDBClass('person');
			$person = new Person();
			foreach ($this->_person_data as $id => $p) {
				if ($checkPhone) {
					foreach (Array('mobile_tel', 'work_tel') as $f) {
						if (preg_replace('/[^0-9]/', '', (string)$p[$f]) === $tel) {
							$this->addMatch('p', $id, $person->getFieldLabel($f), self::matchSnippet((string)$p[$f], 0, strlen((string)$p[$f])));
						}
					}
				}
				if (in_array($in, Array('*', 'email')) && $p['email'] !== '' && strcasecmp($p['email'], $search) === 0) {
					$this->addMatch('p', $id, $person->getFieldLabel('email'), self::matchSnippet($p['email'], 0, strlen($p['email'])));
				}
				if (in_array($in, Array('*', 'remarks'))) {
					$pos = stripos((string)$p['remarks'], $search);
					if ($pos !== FALSE) {
						$this->addMatch('p', $id, $person->getFieldLabel('remarks'), self::matchSnippet($p['remarks'], $pos, strlen($search)));
					}
				}
			}
			// Custom fields: '*' searches every searchable field, 'custom_<id>' just that one.
			$fieldIDs = Array();
			if ($in === '*') {
				$fieldIDs = array_keys($GLOBALS['system']->getDBObjectData('custom_field', Array('searchable' => 1)));
			} else if (preg_match('/^custom_(\d+)$/', $in, $m)) {
				$fieldIDs = Array((int)$m[1]);
			}
			if ($fieldIDs) {
				$labels = self::getSearchInOptions();
				$db = $GLOBALS['db'];
				$rows = $db->queryAll('
					SELECT personid, fieldid, value_text
					FROM custom_field_value
					WHERE personid IN ('.implode(',', array_map('intval', array_keys($this->_person_data))).')
					AND fieldid IN ('.implode(',', array_map('intval', $fieldIDs)).')
					ORDER BY personid, fieldid');
				foreach ($rows as $row) {
					$pos = self::wordStartPos((string)$row['value_text'], $search);
					if ($pos !== NULL) {
						$this->addMatch('p', (int)$row['personid'], $labels['custom_'.$row['fieldid']] ?? '', self::matchSnippet($row['value_text'], $pos, strlen($search)));
					}
				}
			}
		}

		if ($this->_family_data) {
			$GLOBALS['system']->includeDBClass('family');
			$family = new Family();
			foreach ($this->_family_data as $id => $f) {
				if ($checkPhone && preg_replace('/[^0-9]/', '', (string)$f['home_tel']) === $tel) {
					$this->addMatch('f', $id, $family->getFieldLabel('home_tel'), self::matchSnippet($f['home_tel'], 0, strlen($f['home_tel'])));
				}
				if (in_array($in, Array('*', 'address'))) {
					foreach (Array('address_street', 'address_suburb', 'address_postcode') as $field) {
						$pos = self::wordStartPos((string)$f[$field], $search);
						if ($pos !== NULL) {
							$this->addMatch('f', $id, $family->getFieldLabel($field), self::matchSnippet($f[$field], $pos, strlen($search)));
						}
					}
				}
			}
		}
	}

	/** Record that $field matched for result $type ('p'|'f') $id; $snippetHtml is already escaped. */
	private function addMatch(string $type, int $id, string $field, string $snippetHtml): void
	{
		$this->_matches[$type][$id][] = Array($field, $snippetHtml);
	}

	/**
	 * Byte offset where $term starts at the beginning of $value or of a word in it (the rule the
	 * queries use: LIKE 'term%' OR LIKE '% term%'), case-insensitively; NULL if it doesn't.
	 */
	private static function wordStartPos(string $value, string $term): ?int
	{
		if (preg_match('/(?:^| )('.preg_quote($term, '/').')/iu', $value, $m, PREG_OFFSET_CAPTURE)) {
			return $m[1][1];
		}
		return NULL;
	}

	/**
	 * HTML for "…before <b>match</b> after…": the matched bytes plus up to 30 characters of context
	 * either side, with an ellipsis where the value was cut.
	 */
	private static function matchSnippet(string $value, int $pos, int $len): string
	{
		$context = 30;
		$before = substr($value, 0, $pos);
		$match = substr($value, $pos, $len);
		$after = substr($value, $pos + $len);
		if (mb_strlen($before) > $context) $before = '…'.mb_substr($before, -$context);
		if (mb_strlen($after) > $context) $after = mb_substr($after, 0, $context).'…';
		return ents($before).'<b>'.ents($match).'</b>'.ents($after);
	}

	/**
	 * Print the "Field: …matched text…" lines for a result row, if any field other than its name
	 * matched. $indent is the row's own indent (persons nested under their family).
	 */
	private function printMatches(string $type, int $id, string $indent = ''): void
	{
		foreach ($this->_matches[$type][$id] ?? Array() as [$field, $snippetHtml]) {
			echo '<div class="search-match">'.$indent.ents($field).': '.$snippetHtml.'</div>';
		}
	}

	/**
	 * The "Search in" choices: value => label. '*' (everything) searches every field the other
	 * choices do: names, phone, email, address, contact remarks and searchable custom fields. Each
	 * searchable (text) custom field gets its own 'custom_<id>' choice.
	 *
	 * @return array<string, string>
	 */
	private static function getSearchInOptions(): array
	{
		$options = Array(
			'*' => 'everything',
			'name' => 'name',
			'phone' => 'phone',
			'email' => 'email',
			'address' => 'address',
			'remarks' => 'contact remarks',
		);
		foreach ($GLOBALS['system']->getDBObjectData('custom_field', Array('searchable' => 1), 'AND', 'rank') as $id => $f) {
			$options['custom_'.$id] = $f['name'];
		}
		return $options;
	}

	/**
	 * Run the search from the request's `search` (or `tel`), `searchtype` ("Search for") and
	 * `searchin` ("Search in") parameters. A type/field pair that can't match (e.g. groups by
	 * address) runs nothing, so it shows "No results".
	 */
	private function loadResults(): void
	{
		$GLOBALS['system']->includeDBClass('person');
		$search = trim(array_get($_REQUEST, 'search', array_get($_REQUEST, 'tel', '')));
		$this->_search = $search;
		$tel = preg_replace('/[^0-9]/', '', $search);
		$types = Array('p' => TRUE, 'f' => TRUE, 'g' => TRUE, 'r' => TRUE);
		$st = array_get($_REQUEST, 'searchtype');
		if ($st && $st !== '*') {
			foreach ($types as $k => $v) {
				$types[$k] = ($st == $k);
			}
		}
		$in = (string)array_get($_REQUEST, 'searchin', '*');
		if (!array_key_exists($in, self::getSearchInOptions())) $in = '*';
		$this->_search_in = $in;

		if ($search == '') return;

		if ($in === '*') {
			$this->loadEverything($search, $tel, $types);
			return;
		}
		switch ($in) {
			case 'name':
				if ($types['f']) $this->_family_data = $GLOBALS['system']->getDBObjectData('family', Array('_family_name' => $search), 'AND', 'family_name');
				if ($types['p']) $this->_person_data = Person::getPersonsBySearch($search, TRUE, Array('name'));
				if ($types['g']) $this->_group_data = $this->findGroups($search);
				if ($types['r']) $this->_report_data = $this->findReports($search);
				break;
			case 'phone':
				if ($tel === '') break;
				$tel = self::localisePhone($tel);
				if ($types['f']) $this->_family_data = $GLOBALS['system']->getDBObjectData('family', Array('home_tel' => $tel));
				if ($types['p']) $this->_person_data = $GLOBALS['system']->getDBObjectData('person', Array('mobile_tel' => $tel, 'work_tel' => $tel));
				break;
			case 'email':
				if ($types['p']) $this->_person_data = $GLOBALS['system']->getDBObjectData('person', Array('email' => $search));
				break;
			case 'address':
				if ($types['f']) $this->_family_data = $this->findFamiliesByAddress($search);
				break;
			default: // 'remarks' or 'custom_<id>'
				if ($types['p']) $this->_person_data = Person::getPersonsBySearch($search, TRUE, Array($in));
		}
	}

	/**
	 * "Search in: everything". Phone numbers match exactly; when none match (or the term has no
	 * digits), names, address, contact remarks, searchable custom fields, email, groups and reports
	 * are searched.
	 *
	 * @param array<string, bool> $types Enabled result types (p, f, g, r)
	 */
	private function loadEverything(string $search, string $tel, array $types): void
	{
		if (!empty($tel)) {
			$tel = self::localisePhone($tel);
			// Look for phone number matches
			if ($types['f']) {
				$this->_family_data = $GLOBALS['system']->getDBObjectData('family', Array('home_tel' => $tel));
			}
			if ($types['p']) {
				$this->_person_data = $GLOBALS['system']->getDBObjectData('person', Array('mobile_tel' => $tel, 'work_tel' => $tel));
			}
		}
		if (empty($tel) || (empty($this->_family_data) && empty($this->_person_data))) {
			// Look for family name, person name, group name, report name or person email
			if ($types['f']) {
				// Name matches first, then address matches (array + keeps the first occurrence).
				$this->_family_data = $GLOBALS['system']->getDBObjectData('family', Array('_family_name' => $search.'%'), 'AND', 'family_name')
					+ $this->findFamiliesByAddress($search);
			}
			if ($types['p']) {
				$this->_person_data = Person::getPersonsBySearch($search, TRUE, Array('name', 'remarks', 'custom'));
				if (FALSE !== strpos($search, '@')) {
					// Add email search
					$this->_person_data += $GLOBALS['system']->getDBObjectData('person', Array('email' => $search));
				}
			}
			if ($types['g']) {
				$this->_group_data = $this->findGroups($search);
			}
			if ($types['r']) {
				$this->_report_data = $this->findReports($search);
			}
		}
	}

	/**
	 * Convert an internationally-prefixed number (SMS_INTERNATIONAL_PREFIX) to local form.
	 */
	private static function localisePhone(string $tel): string
	{
		if ($prefix = preg_replace('[^0-9]', '', ifdef('SMS_INTERNATIONAL_PREFIX'))) {
			if (str_starts_with($tel, $prefix)) {
				$tel = SMS_LOCAL_PREFIX.substr($tel, strlen($prefix));
			}
		}
		return $tel;
	}

	/** @return array<int, array<string, mixed>> Families with a word in their street, suburb or postcode starting with $search. */
	private function findFamiliesByAddress(string $search): array
	{
		return $GLOBALS['system']->getDBObjectData('family', Array(
			'_address_street' => $search,
			'_address_suburb' => $search,
			'_address_postcode' => $search,
		), 'OR', 'family_name');
	}

	/** @return array<int, array<string, mixed>> Groups whose name has a word starting with $search. */
	private function findGroups(string $search): array
	{
		return $GLOBALS['system']->getDBObjectData('person_group', Array('_name' => $search.'%'), 'OR', 'name');
	}

	/** @return array<int, array<string, mixed>> Shared or own reports whose name has a word starting with $search. */
	private function findReports(string $search): array
	{
		return $GLOBALS['system']->getDBObjectData(
			'person_query',
			Array('_name' => $search.'%', '(owner' => Array(NULL, $GLOBALS['user_system']->getCurrentUser('id'))),
			'AND',
			'name'
		);
	}

	/**
	 * When the search found exactly one thing, redirect to it (exits); otherwise do nothing.
	 */
	private function redirectToSingleResult(): void
	{
		$numResults = count($this->_family_data) + count($this->_group_data) + count($this->_person_data) + count($this->_report_data);

		if ($numResults == 1) {
			// For a single result, just redirect to its detail view, don't show a list
			if (!empty($this->_person_data)) {
				add_message("One matching person found");
				redirect('persons', Array('search' => NULL, 'personid' => key($this->_person_data)));
			} else if (!empty($this->_family_data)) {
				add_message("One matching family found");
				redirect('families', Array('search' => NULL, 'familyid' => key($this->_family_data)));
			} else if (!empty($this->_group_data)) {
				add_message("One matching group found");
				redirect('groups', Array('search' => NULL, 'groupid' => key($this->_group_data)));
			} else if (!empty($this->_report_data)) {
				add_message("One matching report found");
				redirect('persons__reports', Array('queryid' => key($this->_report_data)));
			}
		}
	}

	function getTitle()
	{
		return 'Search results';
	}

	/**
	 * The layout's heading is suppressed so the search form can sit above it; printView() prints
	 * the heading itself.
	 */
	function getPageHeading()
	{
		return '';
	}

	function printView()
	{
		echo '<h3>System-Wide Search</h3>';
		self::printSearchForm(TRUE);
		echo '<h1>'.ents($this->getTitle()).'</h1>';
		$this->printResults();
		// Module scripts are deferred, so Datastar initialises after the form above is parsed.
		echo '<script type="module" src="'.BASE_URL.'/resources/js/datastar-1.0.4.min.js"></script>';
	}

	/**
	 * Fragments: `results` = the #mixed-search-results region.
	 */
	public function printFragment(string $name): void
	{
		if ($name === 'results') {
			$this->printResults();
		} else {
			parent::printFragment($name);
		}
	}

	/**
	 * Print the results region: the results table, a "no results" note, or nothing (empty while
	 * there is no search term, so it is still a morph target).
	 */
	private function printResults(): void
	{
		echo '<div id="'.self::RESULTS_ID.'">';
		if ($this->_search === '') {
			// Nothing searched yet.
		} else if (empty($this->_person_data) && empty($this->_family_data) && empty($this->_group_data) && empty($this->_report_data)) {
			echo '<p><i>No results were found.</i></p>';
		} else {
			?>
			<table class="table table-hover table-min-width table-condensed clickable-rows">
			<?php
			$this->printResultRows(FALSE);
			$this->printResultRows(TRUE);
			?>
			</table>
			<?php
			// The footnote describes "everything", which includes all searchable custom fields.
			$custom_fields = ($this->_search_in === '*') ? $GLOBALS['system']->getDBObjectData('custom_field', Array('searchable' => 1)) : Array();
			if ($custom_fields) {
				$msg = _("These results include matches on the searchable custom fields %s");
				foreach ($custom_fields as $f) $names[] = '"'.$f['name'].'"';
				echo '<p class="smallprint">'.sprintf($msg, implode(', ', $names)).'</p>';
			}
		}
		echo '</div>';
	}
	
	private function printResultRows($archivedStatus)
	{
		if (!empty($this->_group_data)) {
			foreach ($this->_group_data as $id => $values) {
				if ($values['is_archived'] != $archivedStatus) continue;
				$class = ($values['is_archived'])  ? 'class="archived"' : '';
				?>
				<tr <?php echo $class; ?>>
					<td><i class="icon-th"></i> <?php echo ents($values['name']); ?></td>
					<td class="narrow">
						<a href="?view=groups&groupid=<?php echo $id; ?>">View</a> &nbsp;
						<a class="hidden-phone" href="?view=_edit_group&groupid=<?php echo $id; ?>">Edit</a>
					</td>
				</tr>
				<?php
			}
		}
		
		// There's no such thing as achived reports
		if (!empty($this->_report_data) && ($archivedStatus == FALSE)) {
			foreach ($this->_report_data as $id => $values) {
				?>
				<tr>
					<td><i class="icon-list-alt"></i> <?php echo ents($values['name']); ?></td>
					<td class="narrow">
						<a href="?view=persons__reports&queryid=<?php echo $id; ?>">View</a> &nbsp;
						<a class="hidden-phone" href="?view=persons__reports&configure=1&queryid=<?php echo $id; ?>">Configure</a> &nbsp;
					</td>
				</tr>
				<?php
			}

		}
		// Persons and families in three tiers: name matches, then persons matched on other fields,
		// then families matched on other fields. In the name tier, a family that matched by name is
		// printed where its first matching member would be, with all its name-matched members
		// indented beneath it.
		$nameMatchPersons = Array();
		foreach ($this->_person_data as $id => $values) {
			if ($this->isPersonNameMatch($values) && $this->isPersonArchived($values) === $archivedStatus) {
				$nameMatchPersons[$id] = $values;
			}
		}
		$printedFamilies = Array();
		$printedPersons = Array();
		foreach ($nameMatchPersons as $id => $values) {
			if (isset($printedPersons[$id])) continue;
			$familyid = $values['familyid'];
			$family = $this->_family_data[$familyid] ?? NULL;
			if ($family && $this->isFamilyNameMatch($family) && (($family['status'] == 'archived') === $archivedStatus)) {
				$this->_printFamilyRow($familyid, $family);
				$printedFamilies[$familyid] = TRUE;
				foreach ($nameMatchPersons as $memberID => $member) {
					if ($member['familyid'] != $familyid) continue;
					$this->printPersonRow((int)$memberID, $member, '&nbsp;&nbsp;&nbsp;&nbsp;');
					$printedPersons[$memberID] = TRUE;
				}
			} else {
				$this->printPersonRow((int)$id, $values);
				$printedPersons[$id] = TRUE;
			}
		}
		foreach ($this->_family_data as $id => $values) {
			if (isset($printedFamilies[$id]) || !$this->isFamilyNameMatch($values) || (($values['status'] == 'archived') !== $archivedStatus)) continue;
			$this->_printFamilyRow($id, $values);
		}
		foreach ($this->_person_data as $id => $values) {
			if ($this->isPersonNameMatch($values) || $this->isPersonArchived($values) !== $archivedStatus) continue;
			$this->printPersonRow((int)$id, $values);
		}
		foreach ($this->_family_data as $id => $values) {
			if ($this->isFamilyNameMatch($values) || (($values['status'] == 'archived') !== $archivedStatus)) continue;
			$this->_printFamilyRow($id, $values);
		}
	}

	/** Whether $p matched by name (getPersonsBySearch() rank 1), not by phone, email, remarks or a custom field. */
	private function isPersonNameMatch(array $p): bool
	{
		return (int)($p['search_rank'] ?? 0) === 1;
	}

	/** Whether family $f matched by name: its family name has a word starting with the search. */
	private function isFamilyNameMatch(array $f): bool
	{
		return self::wordStartPos((string)$f['family_name'], $this->_search) !== NULL;
	}

	private function isPersonArchived(array $p): bool
	{
		return in_array($p['status'], Person_Status::getArchivedIDs());
	}

	private function printPersonRow(int $id, array $values, string $indent = ''): void
	{
		$class = $this->isPersonArchived($values) ? 'class="archived"' : '';
		?>
		<tr <?php echo $class; ?>>
			<td>
				<?php
				echo $indent;
				echo '<i class="icon-user"></i> ';
				echo ents($values['first_name']).' '.ents($values['last_name']);
				$this->printMatches('p', $id, $indent);
				?>
			</td>
			<td class="narrow">
				<a href="?view=persons&personid=<?php echo $id; ?>">View</a> &nbsp;
				<a class="hidden-phone" href="?view=_edit_person&personid=<?php echo $id; ?>">Edit</a>
			</td>
		</tr>
		<?php
	}

	
	private function _printFamilyRow($id, $values)
	{
		$class = ($values['status'] == 'archived') ? 'class="archived"' : '';
		?>
		<tr <?php echo $class; ?>>
			<td><i class="icon-home"></i> <?php echo ents($values['family_name']); ?> Family<?php $this->printMatches('f', (int)$id); ?></td>
			<td class="narrow">
				<a href="?view=families&familyid=<?php echo $id; ?>">View</a> &nbsp;
				<a class="hidden-phone" href="?view=_edit_family&familyid=<?php echo $id; ?>">Edit</a>
			</td>
		</tr>
		<?php		
	}
	
	
	/**
	 * Print the search form.
	 *
	 * @param bool $live When TRUE (the search results page), typing in the box or changing the
	 *                   search type fetches the `results` fragment and morphs it in place. The
	 *                   form stays a plain GET form, so Enter still does a full page load (which
	 *                   jumps to a single match) and it works without JavaScript. The home page
	 *                   passes FALSE, leaving a plain form.
	 */
	public static function printSearchForm(bool $live = FALSE)
	{
		$type = array_get($_GET, 'searchtype', '*');
		$checked = Array($type => 'checked="checked"');
		$search = (string)array_get($_GET, 'search');
		$live_attrs = '';
		if ($live) {
			// One handler on the form: it sees typing in the text box and "Search for"/"Search in" changes
			// (radio 'change' events bubble). A single character matches too much, so search from
			// two, but still clear the region when the box is emptied. The URL is kept in step, so
			// a reload or Back keeps the search; the form's hidden `view` field supplies the view.
			$live_attrs = ' data-on:input__debounce.100ms="'
				. 'history.replaceState(null, \'\', \'?\' + new URLSearchParams(new FormData(el)));'
				. ' (el.elements.search.value.trim().length >= 2 || el.elements.search.value.trim() === \'\')'
				. ' && @get(\'?fragment=results\', {contentType: \'form\'})"';
		}
		?>
		<form method="get" class="homepage-search"<?php echo $live_attrs; ?>>
			<input type="hidden" name="view" value="_mixed_search" />
			<span class="input-append fullwidth">
				<input type="text" 
					   name="search" 
					   value="<?php echo ents($search); ?>" 
					   placeholder="<?php echo _("Name, phone or email");?>"
					   <?php if ($search) echo 'autoselect="autoselect"'; ?>
				/>
				<button type="submit" class="btn"><i class="icon-search"></i></button>
			</span>
			<div class="homepage-search-options soft">
				<details>
					<summary>Search for...</summary>
					<div>
						<label class="checkbox"><input type="radio" name="searchtype" <?php echo array_get($checked, '*'); ?> value="*" /> everything</label>
						<label class="checkbox"><input type="radio" name="searchtype" <?php echo array_get($checked, 'f'); ?> value="f" /> families</label>
						<label class="checkbox"><input type="radio" name="searchtype" <?php echo array_get($checked, 'p'); ?> value="p" /> persons</label>
						<label class="checkbox"><input type="radio" name="searchtype" <?php echo array_get($checked, 'g'); ?> value="g" /> groups</label>
						<label class="checkbox"><input type="radio" name="searchtype" <?php echo array_get($checked, 'r'); ?> value="r" /> reports</label>
					</div>
				</details>
				<?php
				$in = (string)array_get($_GET, 'searchin', '*');
				$inOptions = self::getSearchInOptions();
				if (!array_key_exists($in, $inOptions)) $in = '*';
				?>
				<details<?php if ($in !== '*') echo ' open'; ?>>
					<summary>Search in...</summary>
					<div>
					<?php foreach ($inOptions as $value => $label) { ?>
						<label class="checkbox"><input type="radio" name="searchin" value="<?php echo ents($value); ?>"<?php if ($value === $in) echo ' checked="checked"'; ?> /> <?php echo ents($label); ?></label>
					<?php } ?>
					</div>
				</details>
			</div>
		</form>
		<?php
	}
}
