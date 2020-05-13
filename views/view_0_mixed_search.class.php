<?php
class View__Mixed_Search extends View
{
	private $_family_data = Array();
	private $_person_data = Array();
	private $_group_data = Array();
	private $_report_data = Array();

	function processView()
	{
		$GLOBALS['system']->includeDBClass('person');
		$this->_search_params = Array();
		$search = trim(array_get($_REQUEST, 'search', array_get($_REQUEST, 'tel', '')));
		$tel = preg_replace('/[^0-9]/', '', $search);

		if ($search == '') return;

		if (!empty($tel)) {
			// Look for phone number matches
			$this->_family_data = $GLOBALS['system']->getDBObjectData('family', Array('home_tel' => $tel));
			$this->_person_data = $GLOBALS['system']->getDBObjectData('person', Array('mobile_tel' => $tel, 'work_tel' => $tel));
		}
		if (empty($tel) || (empty($this->_family_data) && empty($this->_person_data))) {
			// Look for family name, person name, group name, report name or person email
			$this->_family_data = $GLOBALS['system']->getDBObjectData('family', Array('_family_name' => $search.'%'));
			$this->_person_data = Person::getPersonsBySearch($search);
			$this->_group_data = $GLOBALS['system']->getDBObjectData('person_group', Array('_name' => $search.'%'), 'OR', 'name');
			$this->_report_data = $GLOBALS['system']->getDBObjectData(
									'person_query',
									Array('_name' => $search.'%', '(owner' => Array(NULL, $GLOBALS['user_system']->getCurrentUser('id'))),
									'AND',
									'name'
								  );
			if (FALSE !== strpos($search, '@')) {
				// Add email search
				$this->_person_data += $GLOBALS['system']->getDBObjectData('person', Array('email' => $search));
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

		// Put all archived results at the end of the list
		foreach (Array('_person_data', '_family_data', '_group_data') as $var) {
			$archiveds = Array();
			$ref = &$this->$var;
			foreach ($ref as $k => $v) {
				if ((array_get($v, 'status') == 'archived') || array_get($v, 'is_archived')) {
					$archiveds[$k] = $v;
					unset($ref[$k]);
				}
			}
			foreach ($archiveds as $k => $v) {
				$ref[$k] = $v;
			}
		}
	}

	function getTitle()
	{
		return 'Search results';
	}


	function printView()
	{
		if (empty($this->_person_data) && empty($this->_family_data) && empty($this->_group_data)) {
			echo '<p><i>No matching persons or families were found.  Try searching again.</i></p>';
			echo '<form class="form form-horizontal"><input type="hidden" name="view" value="_mixed_search">';
			echo '<input type="text" name="search" placeholder="Name, Phone or Email" />';
			echo '<button type="submit" class="btn">Search</button></form>';
			return;
		}

		?>
		<table class="table table-hover table-striped table-min-width clickable-rows">
		<?php
		if (!empty($this->_group_data)) {
			foreach ($this->_group_data as $id => $values) {
				$class = ($values['is_archived'])  ? 'class="archived"' : '';
				?>
				<tr <?php echo $class; ?>>
					<td><?php echo ents($values['name']); ?></td>
					<td class="narrow">
						<a href="?view=groups&groupid=<?php echo $id; ?>"><i class="icon-list"></i>View</a> &nbsp;
						<a href="?view=_edit_group&groupid=<?php echo $id; ?>"><i class="icon-wrench"></i>Edit</a>
					</td>
				</tr>
				<?php
			}

		}
		if (!empty($this->_person_data)) {
			foreach ($this->_person_data as $id => $values) {
				$class = ($values['status'] == 'archived') ? 'class="archived"' : '';
				?>
				<tr <?php echo $class; ?>>
					<td><?php echo ents($values['first_name']).' '.ents($values['last_name']); ?></td>
					<td class="narrow">
						<a href="?view=persons&personid=<?php echo $id; ?>"><i class="icon-user"></i>View</a> &nbsp;
						<a href="?view=_edit_person&personid=<?php echo $id; ?>"><i class="icon-wrench"></i>Edit</a>
					</td>
				</tr>
				<?php
			}
		}
		if (!empty($this->_family_data)) {
			foreach ($this->_family_data as $id => $values) {
				$class = ($values['status'] == 'archived') ? 'class="archived"' : '';
				?>
				<tr <?php echo $class; ?>>
					<td><?php echo ents($values['family_name']); ?> Family</td>
					<td class="narrow">
						<a href="?view=families&familyid=<?php echo $id; ?>"><i class="icon-home"></i>View</a> &nbsp;
						<a href="?view=_edit_family&familyid=<?php echo $id; ?>"><i class="icon-wrench"></i>Edit</a>
					</td>
				</tr>
				<?php
			}
		}
		if (!empty($this->_report_data)) {
			foreach ($this->_report_data as $id => $values) {
				?>
				<tr <?php echo $class; ?>>
					<td><?php echo ents($values['name']); ?></td>
					<td class="narrow">
						<a href="?view=persons__reports&queryid=<?php echo $id; ?>"><i class="icon-list"></i>View</a> &nbsp;
						<a href="?view=persons__reports&configure=1&queryid=<?php echo $id; ?>"><i class="icon-wrench"></i>Configure</a> &nbsp;
					</td>
				</tr>
				<?php
			}

		}
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
}