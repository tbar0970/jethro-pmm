<?php
class View__Mixed_Search extends View
{
	private $_family_data = Array();
	private $_person_data = Array();
	private $_group_data = Array();
	private $_report_data = Array();
	private $_search_params = Array();

	function processView()
	{
		$GLOBALS['system']->includeDBClass('person');
		$this->_search_params = Array();
		$search = trim(array_get($_REQUEST, 'search', array_get($_REQUEST, 'tel', '')));
		$tel = preg_replace('/[^0-9]/', '', $search);
		$types = Array('p' => TRUE, 'f' => TRUE, 'g' => TRUE, 'r' => TRUE);
		$st = array_get($_REQUEST, 'searchtype');
		if ($st && $st !== '*') {
			foreach ($types as $k => $v) {
				$types[$k] = ($st == $k);
			}
		}

		if ($search == '') return;

		if (!empty($tel)) {
			if ($prefix = preg_replace('[^0-9]', '', ifdef('SMS_INTERNATIONAL_PREFIX'))) {
				if (strpos($tel, $prefix) === 0) {
					$tel = SMS_LOCAL_PREFIX.substr($tel, strlen($prefix));
				}
			}
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
				$this->_family_data = $GLOBALS['system']->getDBObjectData('family', Array('_family_name' => $search.'%'));
			}
			if ($types['p']) {
				$this->_person_data = Person::getPersonsBySearch($search);
				if (FALSE !== strpos($search, '@')) {
					// Add email search
					$this->_person_data += $GLOBALS['system']->getDBObjectData('person', Array('email' => $search));
				}
			}
			if ($types ['g']) {
				$this->_group_data = $GLOBALS['system']->getDBObjectData('person_group', Array('_name' => $search.'%'), 'OR', 'name');
			}
			if ($types['r']) {
				$this->_report_data = $GLOBALS['system']->getDBObjectData(
									'person_query',
									Array('_name' => $search.'%', '(owner' => Array(NULL, $GLOBALS['user_system']->getCurrentUser('id'))),
									'AND',
									'name'
								  );
			}
		}

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


	function printView()
	{
		if (empty($this->_person_data) && empty($this->_family_data) && empty($this->_group_data) && empty($this->_report_data)) {
			echo '<p><i>No results were found.  Try searching again.</i></p>';
			self::printSearchForm();
			return;
		}

		?>
		<table class="table table-hover table-min-width table-condensed clickable-rows">
		<?php
		$this->printResultRows(FALSE);
		$this->printResultRows(TRUE);	
		?>
		</table>

		<?php
		$custom_fields = $GLOBALS['system']->getDBObjectData('custom_field', Array('searchable' => 1));
		if ($custom_fields) {
			$msg = _("These results include matches on the searchable custom fields %s");
			foreach ($custom_fields as $f) $names[] = '"'.$f['name'].'"';
			echo '<p class="smallprint">'.sprintf($msg, implode(', ', $names)).'</p>';
		}
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
		if (!empty($this->_person_data)) {
			$lastFamilyID = 0;
			$indent = '';
			foreach ($this->_person_data as $id => $values) {
				if (in_array($values['status'], Person_Status::getArchivedIDs()) !== $archivedStatus) continue;
				if ($lastFamilyID != $values['familyid']) $indent = '';
				if (isset($this->_family_data[$values['familyid']])) {
					$this->_printFamilyRow($values['familyid'], $this->_family_data[$values['familyid']]);
					unset($this->_family_data[$values['familyid']]);
					$indent = '&nbsp;&nbsp;&nbsp;&nbsp;';
				}
				
				$class = ($values['status'] == 'archived') ? 'class="archived"' : '';
				?>
				<tr <?php echo $class; ?>>
					<td>
						<?php 
						echo $indent;
						echo '<i class="icon-user"></i> ';
						echo ents($values['first_name']).' '.ents($values['last_name']); 
						?>
					</td>
					<td class="narrow">
						<a href="?view=persons&personid=<?php echo $id; ?>">View</a> &nbsp;
						<a class="hidden-phone" href="?view=_edit_person&personid=<?php echo $id; ?>">Edit</a>
					</td>
				</tr>
				<?php
				$lastFamilyID = $values['familyid'];
			}
		}
		if (!empty($this->_family_data)) {
			foreach ($this->_family_data as $id => $values) {
				if (($values['status'] == 'archived') !== $archivedStatus) continue;		
				$this->_printFamilyRow($id, $values);
			}
		}
	}		
	
	
	private function _printFamilyRow($id, $values)
	{
		$class = ($values['status'] == 'archived') ? 'class="archived"' : '';
		?>
		<tr <?php echo $class; ?>>
			<td><i class="icon-home"></i> <?php echo ents($values['family_name']); ?> Family</td>
			<td class="narrow">
				<a href="?view=families&familyid=<?php echo $id; ?>">View</a> &nbsp;
				<a class="hidden-phone" href="?view=_edit_family&familyid=<?php echo $id; ?>">Edit</a>
			</td>
		</tr>
		<?php		
	}
	
	
	public static function printSearchForm()
	{
		$type = array_get($_GET, 'searchtype', '*');
		$checked = Array($type => 'checked="checked"');
		$autoselect = array_get($_GET, 'search')
		?>
		<form method="get" class="homepage-search">
			<input type="hidden" name="view" value="_mixed_search" />
			<span class="input-append fullwidth">
				<input type="text" 
					   name="search" 
					   value="<?php echo ents(array_get($_GET, 'search')); ?>" 
					   placeholder="<?php echo _("Name, phone or email");?>"
					   <?php if (array_get($_GET, 'search')) echo 'autoselect="autoselect"'; ?>
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
			</div>
		</form>
		<?php
	}
}