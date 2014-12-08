<?php
include_once 'include/db_object.class.php';
class Person_Query extends DB_Object
{
	var $_field_details = Array();
	var $_query_fields = Array('p.status', 'p.congregationid', 'p.age_bracket', 'p.gender', 'f.address_suburb', 'f.address_state', 'f.address_postcode', 'p.creator', 'p.created', 'p.status_last_changed');
	var $_show_fields = Array(
		'p.first_name', 'p.last_name', 'f.family_name', 'p.age_bracket', 'p.gender', 'p.status', 'p.congregationid', NULL,
		'p.email', 'p.mobile_tel', 'p.work_tel', 'f.home_tel', 'p.remarks',
		'f.address_street', 'f.address_suburb', 'f.address_state', 'f.address_postcode', NULL,
		'p.creator', 'p.created', 'f.created', 'p.status_last_changed', );
	var $_dummy_family = NULL;
	var $_dummy_person = NULL;
	var $_group_chooser_options_cache = NULL;

	function Person_Query($id=0)
	{
		if (!empty($GLOBALS['system'])) {
			$GLOBALS['system']->includeDBClass('person');
			$GLOBALS['system']->includeDBClass('family');
 			$this->_dummy_person = new Person();
			foreach ($this->_dummy_person->fields as $i => $v) {
				unset($this->_dummy_person->fields[$i]['readonly']);
			}
			$this->_dummy_family = new Family();
			foreach ($this->_dummy_family->fields as $i => $v) {
				unset($this->_dummy_family->fields[$i]['readonly']);
			}
			foreach ($this->_dummy_person->fields as $i => $v) {
				if ($v['type'] == 'serialise') {
					continue;
				}
				if ($i == 'familyid') continue;
				if (empty($v['label'])) $v['label'] = $this->_dummy_person->getFieldLabel($i);
				$this->_field_details['p.'.$i] = $v;
				$this->_field_details['p.'.$i]['allow_empty'] = true;
			}
			foreach ($this->_dummy_family->fields as $i => $v) {
				if ($v['type'] == 'serialise') {
					continue;
				}
				if (empty($v['label'])) $v['label'] = $this->_dummy_family->getFieldLabel($i);
				if (in_array($i, Array('status', 'created', 'creator'))) {
					$v['label'] = "Family's ".$v['label'];
				}
				$this->_field_details['f.'.$i] = $v;
				$this->_field_details['f.'.$i]['allow_empty'] = true;
			}
		}
		return $this->DB_Object($id);
	}

	function getInitSQL()
	{
		return "
			CREATE TABLE `person_query` (
			  `id` int(11) NOT NULL auto_increment,
			  `name` varchar(255) collate latin1_general_ci NOT NULL default '',
			  `creator` int(11) NOT NULL default '0',
			  `created` timestamp NOT NULL default CURRENT_TIMESTAMP,
			  `params` text collate latin1_general_ci NOT NULL,
			  PRIMARY KEY  (`id`)
			) ENGINE=InnoDB ;
		";
	}


	function _getFields()
	{
		$default_params = Array(
							'rules'			=> Array('p.status' => Array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'contact')),
							'show_fields'	=> Array('p.first_name', 'p.last_name', '', '', 'view_link', 'checkbox'),
							'group_by'		=> '',
							'sort_by'		=> 'p.last_name',
							'include_groups'	=> Array(),
							'exclude_groups'	=> Array(),
						  );
		return Array(
			'name'	=> Array(
									'type'		=> 'text',
									'width'		=> 30,
									'maxlength'	=> 128,
									'allow_empty'	=> false,
									'initial_cap'	=> true,
								   ),
			'created'			=> Array(
									'type'			=> 'datetime',
									'readonly'		=> true,
									'show_in_summary'	=> false,
								   ),
			'creator'			=> Array(
									'type'			=> 'reference',
									'editable'		=> false,
									'references'	=> 'staff_member',
									'show_in_summary'	=> false,
								   ),
			'params'			=> Array(
									'type'			=> 'serialise',
									'editable'		=> false,
									'show_in_summary'	=> false,
									'default'		=> $default_params,

								   ),

		);
	}

	function toString()
	{
		return $this->values['name'];
	}


	function printForm()
	{
		$GLOBALS['system']->includeDBClass('person_group');
		$params = $this->getValue('params');
		?>
		<h3>Find me people...</h3>

		<h4>whose person/family record matches these rules:</h4>
		<table class="table table-border table-auto-width indent-left table-condensed">
		<?php
		foreach ($this->_query_fields as $i) {
			$v = $this->_field_details[$i];
			if (in_array($v['type'], Array('select', 'reference', 'datetime', 'text'))
				&& !in_array($i, Array('p.first_name', 'p.last_name', 'f.family_name', 'p.remarks', 'p.email'))) {
				?>
				<tr>
					<td>
						<label class="checkbox">
							<input autofocus="1" type="checkbox" name="enable_rule[]" value="<?php echo $i; ?>" id="enable_rule_<?php echo $i; ?>" class="select-rule-toggle" <?php if (isset($params['rules'][$i])) echo 'checked="checked" '; ?>/>
							<strong><?php echo $v['label']; ?></strong>
							<?php
							if ($v['type'] == 'datetime') {
								echo 'is between...';
							} else {
								echo 'is...';
							}
							?>
						</label>
					</td>
					<td>
						<div class="select-rule-options" <?php if (!isset($params['rules'][$i])) echo 'style="display: none" '; ?>>
							<?php
							$key = str_replace('.', '_', $i);
							if ($v['type'] == 'datetime') {
								$value = array_get($params['rules'], $i, Array('from' => '2000-01-01', 'to' => date('Y-m-d')));
								print_widget('params_'.$key.'_from', Array('type' => 'date'), $value['from']);
								echo ' and ';
								print_widget('params_'.$i.'_to', Array('type' => 'date'), $value['to']);
							} else {
								$v['allow_multiple'] = TRUE;
								print_widget('params_'.$key, $v, array_get($params['rules'], $i, $v['type'] == 'select' ? Array() : ''));
							}
							?>
						</div>
					</td>
				</tr>
				<?php
			}
		}
		?>
		</table>
		
		<?php
		// DATE FIELD RULES
		if ($GLOBALS['system']->featureEnabled('DATES')) {
			if (empty($params['dates']) && !empty($params['rules']['date'])) {
				$params['dates'][] = $params['rules']['date'] + Array('criteria' => 'between');
				unset($params['rules']['date']);
			}
			
			?>
			<h4>who have date fields...</h4>
			<table class="table expandable indent-left">
			<?php
			$values = array_get($params, 'dates', Array());
			if (empty($values)) {
				$values[] = Array('typeid' => NULL, 'criteria' => 'any', 'anniversary' => TRUE, 'from' => '2000-01-01', 'to' => date('Y-m-d'));
			}
			foreach ($values as $i => $value) {
				?>
				<tr>
					<td>
						<?php					
						print_widget('params_date_'.$i.'_typeid', Array('type' => 'select', 'options' => Array(0 => '--Choose--') + Person::getDateTypes()), (string)$value['typeid']);
						?>
					</td>
					<td>
						<?php
						$cs = Array('any' => '', 'empty' => '', 'between' => '');
						if (!empty($value['criteria'])) {
							$cs[$value['criteria']] = 'checked="checked"';
						} else {
							$cs['any'] = 'checked="checked"';
						}
						?>
						<label class="radio">
							<input type="radio" name="params_date_<?php echo $i; ?>_criteria" value="any" <?php echo $cs['any']; ?> />
							filled in with any value
						</label>
						<label class="radio">
							<input type="radio" name="params_date_<?php echo $i; ?>_criteria" value="empty" <?php echo $cs['empty']; ?> />
							not filled in with any value
						</label>
						<label class="radio">
							<input type="radio" name="params_date_<?php echo $i; ?>_criteria" value="between" <?php echo $cs['between']; ?> />
							with
							<?php print_widget('params_date_'.$i.'_anniversary', Array('type' => 'select', 'options' => Array('exact value', 'exact value or anniversary')), (string)$value['anniversary']); ?>
							between
							<?php print_widget('params_date_'.$i.'_from', Array('type' => 'date'), $value['from']); ?>
							and
							<?php print_widget('params_date_'.$i.'_to', Array('type' => 'date'), $value['to']); ?>
						</label>
					</td>
				</tr>
				<?php
			}
			?>
			</table>
			<?php
		}
		?>

		<h4>who <strong>are</strong> in one or more of these groups:</h4>
		<div class="indent-left">

			<?php
			Person_Group::printMultiChooser('include_groupids', array_get($params, 'include_groups', Array()), Array(), TRUE);
			?>

			<label class="checkbox">
				<input type="checkbox" name="enable_group_membership_status" data-toggle="enable" data-target="#group-membership-status *" value="1"	<?php if (!empty($params['group_membership_status'])) echo 'checked="checked"'; ?> />
				with membership status of
			</label>
			<span id="group-membership-status"><?php Person_Group::printMembershipStatusChooser('group_membership_status', array_get($params, 'group_membership_status'), true); ?></span>

			<label class="checkbox">
				<input type="checkbox" name="enable_group_join_date" data-toggle="enable" data-target="#group-join-dates *" value="1"	<?php if (!empty($params['group_join_date_from'])) echo 'checked="checked"'; ?> />
				and joined the group between
			</label> 
			<span id="group-join-dates">
			<?php print_widget('group_join_date_from', Array('type' => 'date'), array_get($params, 'group_join_date_from')); ?>
			and <?php print_widget('group_join_date_to', Array('type' => 'date'), array_get($params, 'group_join_date_to')); ?>
			</span>
		</div>


		<h4>who are <strong>not</strong> in any of these groups:</h4>
		<div class="indent-left">
			<?php
			Person_Group::printMultiChooser('exclude_groupids', array_get($params, 'exclude_groups', Array()), Array(), TRUE);
			?>
		</div>
	
	<?php
	if ($GLOBALS['user_system']->havePerm(PERM_VIEWNOTE)) {
		?>
		<h4>who have a person note containing the phrase:</h4>
		<div class="indent-left">
			<input type="text" name="note_phrase" size="60" value="<?php echo (isset($params["note_phrase"])?$params["note_phrase"]:""); ?>">
		</div>
		<?php
	}

	if ($GLOBALS['user_system']->havePerm(PERM_VIEWATTENDANCE)) {
		?>
		<h4>whose attendance...</h4>
		<div class="indent-left">
			at 
			<?php
			$groupid_params = Array(
				'type' => 'select', 
				'options' => Array(null => '(Nothing)', '__cong__' => 'their congregation'),
				'attrs' => Array('data-toggle' => 'enable', 'data-target' => '.attendance-input'),
			);
			$groups = $GLOBALS['system']->getDBObjectData('person_group', Array('can_record_attendance' => 1, 'is_archived' => 0), 'AND');
			foreach ($groups as $id => $groupdata) {
				$groupid_params['options'][$id] = $groupdata['name'];
			}
			print_widget('attendance_groupid', $groupid_params, array_get($params, 'attendance_groupid', 0));
			?>
			<br />

			has been 

			<?php 
			$operator_params = Array(
							'type'		=> 'select',
							'options'	=> Array('<' => 'less than', '>' => 'more than'),
							'class' => 'attendance-input',
						   );
			print_widget('attendance_operator', $operator_params, array_get($params, 'attendance_operator', '<')); ?>
			<input name="attendance_percent" type="text" size="2" class="int-box attendance-input" value="<?php echo (int)array_get($params, 'attendance_percent', 50); ?>" />%

			<br />over the last <input name="attendance_weeks" type="text" size="2" class="int-box attendance-input" value="<?php echo (int)array_get($params, 'attendance_weeks', 2); ?>" /> weeks
		</div>
		<?php
	}
	?>

		<h3>For each person found, show me...</h3>
		<?php
		$show_fields = array_get($params, 'show_fields', Array());
		?>

		<table class="table-condensed expandable indent-left">
		<?php
		foreach ($show_fields as $chosen_field) {
			?>
			<tr>
				<td>
					<img src="<?php echo BASE_URL; ?>/resources/img/expand_up_down_green_small.png" class="icon insert-row-below" style="position: relative; top: 2ex" title="Create a blank entry here" />
				</td>
				<td>
					<?php
					$options = Array(
								''			=> '',
							   );
					foreach ($this->_show_fields as $i => $opt) {
						if (is_null($opt)) {
							$options['--'.$i] = '-----';
						} else {
							$options[$opt] = $this->_field_details[$opt]['label'];
						}
					}

					if ($GLOBALS['user_system']->havePerm(PERM_VIEWNOTE)) {
						$options['--Z'] = '-----';
						$options['attendance_percent'] = 'Attendance rate during specified period';
						$options['attendance_numabsences'] = 'Number of absences since last marked present';
					}

					if ($GLOBALS['user_system']->havePerm(PERM_VIEWATTENDANCE)) {
						$options['--Y'] = '-----';
						$options['notes.subjects'] = 'Notes matching the phrase above';
						$options['actionnotes.subjects'] = 'Notes requiring action';
					}

					if ($GLOBALS['system']->featureEnabled('DATES')) {
						$options['--A'] = '-----';
						foreach (Person::getDateTypes() as $typeid => $name) {
							$options['date---'.$typeid] = ucfirst($name).' Date';
						}
					}
					$options['--B'] = '-----';
					if ($GLOBALS['system']->featureEnabled('PHOTOS')) {
						$options['photo'] = 'Photo';
					}
					$options['groups']	= 'Which of the selected groups they are in';
					$options['membershipstatus'] = 'Group membership status';
					
					$options['all_members'] = 'Names of all their family members';
					$options['adult_members'] = 'Names of their adult family members';
					if ($GLOBALS['system']->featureEnabled('PHOTOS')) {
						$options['photo'] = 'Photo';
					}
					$options['--D'] = '-----';
					$options['view_link'] = 'A link to view their person record';
					$options['edit_link'] = 'A link to edit their person record';
					$options['checkbox'] = 'A checkbox for bulk actions';
					print_widget('show_fields[]', Array('options' => $options, 'type' => 'select', 'disabled_prefix' => '--'), $chosen_field);
					?>
				</td>
				<td class="nowrap">
					<img src="<?php echo BASE_URL; ?>/resources/img/arrow_up_thin_black.png" class="icon move-row-up" title="Move this item up" />
					<img src="<?php echo BASE_URL; ?>/resources/img/arrow_down_thin_black.png" class="icon move-row-down" title="Move this item down" />
					<img src="<?php echo BASE_URL; ?>/resources/img/cross_red.png" class="icon delete-row" title="Delete this item" />
				</td>
			</tr>
			<?php
		}
		?>
		</table>


		<h3>Group the results...</h3>
		<?php
		$gb = array_get($params, 'group_by', '');
		?>
		<div class="indent-left">
			<select name="group_by">
				<option value=""<?php if ($gb == '') echo ' selected="selected"'; ?>>all together</option>
				<option value="groupid"<?php if ($gb == 'groupid') echo ' selected="selected"'; ?>>by group membership</option>
			<?php
			foreach ($this->_query_fields as $i) {
				$v = $this->_field_details[$i];
				if (!in_array($v['type'], Array('select', 'reference'))) continue;
				?>
				<option value="<?php echo $i; ?>"<?php if ($gb == $i) echo ' selected="selected"'; ?>>by <?php echo $v['label']; ?></option>
				<?php
			}
			?>
			</select>
			<p class="smallprint">Note: Result groups that do not contain any persons will not be shown</p>
		</div>

		<h3>Sort the results by...</h3>

		<select name="sort_by" class="indent-left">
		<?php
		$sb = array_get($params, 'sort_by');
		foreach ($this->_show_fields as $name) {
			if (is_null($name)) {
				?>
				<option disabled="disabled">------</option>
				<?php
			} else {
				?>
				<option value="<?php echo $name; ?>"<?php if ($sb == $name) echo ' selected="selected"'; ?>><?php echo ents($this->_field_details[$name]['label']); ?></option>
				<?php
			}
		}
		?>
		<option disabled="disabled">------</option>
		<option value="attendance_percent"<?php if ($sb == "attendance_percent") echo ' selected="selected"'; ?>>Attendance rate during the specified period</option>
		<option value="attendance_numabsences""<?php if ($sb == "attendance_percent") echo ' selected="selected"'; ?>>Number of absences since last marked present</option>
		<?php
		if ($GLOBALS['system']->featureEnabled('DATES')) {
			?>
			<option disabled="disabled">------</option>
			<?php
			foreach (Person::getDateTypes() as $typeid => $name) {
				?>
				<option value="date---<?php echo $typeid; ?>"<?php if ($sb == 'date---'.$typeid) echo ' selected="selected"'; ?>><?php echo ents($name); ?> date</option>
				<?php
			}
		}

		?>
		</select>

		<?php
		if ($GLOBALS['user_system']->havePerm(PERM_MANAGEREPORTS)) {
			?>
			<h3>I want to save this report...</h3>
			<div class="indent-left">
				<label type="radio">
					<input type="radio" name="save_option" value="new" id="save_option_new" <?php if (empty($this->id)) echo 'checked="checked"'; ?> />
					as a new report called
					<input type="text" name="new_query_name" />
				</label>
		
				<label type="radio">
					<input type="radio" name="save_option" value="replace" id="save_option_replace" <?php if ($this->id && ($this->id != 'TEMP')) echo 'checked="checked"'; ?> />
					in place of an existing report
					<?php print_widget('replace_query_id', Array('type' => 'reference', 'references' => 'person_query'), $this->id); ?>
				</label>

				<label type="radio">
					<input type="radio" name="save_option" value="temp" id="save_option_temp"<?php if (empty($this->id) || $this->id == 'TEMP') echo ' checked="checked"'; ?> />
					only temporarily as an ad-hoc report
				</label>
			</div>
			<?php
		}
	}

	function processForm()
	{
		if ($GLOBALS['user_system']->havePerm('PERM_MANAGEREPORTS')) {
			switch ($_POST['save_option']) {
				case 'new':
					$this->populate(0, Array());
					$this->setValue('name', $_POST['new_query_name']);
					break;
				case 'replace':
					$this->load((int)$_POST['replace_query_id']);
					break;
				case 'temp':
					$this->id = 'TEMP';
				break;
			}
		} else {
			$this->id = 'TEMP';
		}

		$params = $this->getValue('params');

		// FIELD RULES
		$rules = Array();
		if (!empty($_POST['enable_rule'])) {
			foreach ($_POST['enable_rule'] as $field) {
				$rules[$field] = $this->_processRuleDetails($field);
			}
		}
		$params['rules'] = $rules;
		
		// DATE RULES
		$i = 0;
		$params['dates'] = Array();
		while (array_key_exists('params_date_'.$i.'_typeid', $_REQUEST)) {
			if (!empty($_REQUEST['params_date_'.$i.'_typeid'])) {
				$params['dates'][] = Array(
					'typeid' => (int)$_REQUEST['params_date_'.$i.'_typeid'],
					'criteria' => $_REQUEST['params_date_'.$i.'_criteria'],
					'anniversary' => (int)$_REQUEST['params_date_'.$i.'_anniversary'],
					'from' => process_widget('params_date_'.$i.'_from', Array('type' => 'date')),
					'to' => process_widget('params_date_'.$i.'_to', Array('type' => 'date')),
				);
			}
			$i++;
		}

		// GROUP RULES
		$params['include_groups'] = $this->_removeEmpties($_POST['include_groupids']);
		$params['group_join_date_from'] = empty($_POST['enable_group_join_date']) ? NULL : process_widget('group_join_date_from', Array('type' => 'date'));
		$params['group_join_date_to'] = empty($_POST['enable_group_join_date']) ? NULL : process_widget('group_join_date_to', Array('type' => 'date'));
		$params['exclude_groups'] = $this->_removeEmpties($_POST['exclude_groupids']);
		$params['group_membership_status'] = array_get($_POST, 'group_membership_status');

		// NOTE RULES
		$params['note_phrase'] = array_get($_POST, 'note_phrase');

		// ATTENDANCE RULES
		$params['attendance_groupid'] = array_get($_POST, 'attendance_groupid');
		$params['attendance_operator'] = array_get($_POST, 'attendance_operator');
		$params['attendance_percent'] = array_get($_POST, 'attendance_percent');
		$params['attendance_weeks'] = array_get($_POST, 'attendance_weeks');

		// SHOW FIELDS
		$params['show_fields'] = $this->_removeEmpties($_POST['show_fields']);

		// GROUP BY
		$params['group_by'] = $_POST['group_by'];
		if (empty($params['include_groups']) && ($params['group_by'] == 'groupid')) {
			add_message('No groups were chosen, so results will be shown all together');
			$params['group_by'] = '';
		}

		// SORT BY
		$params['sort_by'] = $_POST['sort_by'];
		$this->setValue('params', $params);
	}

	function _processRuleDetails($field)
	{
		$res = Array();
		switch ($this->_field_details[$field]['type']) {
			case 'datetime':
				$res['from'] = process_widget('params_'.str_replace('.', '_', $field).'_from', Array('type' => 'date'));
				$res['to'] = process_widget('params_'.str_replace('.', '_', $field).'_to', Array('type' => 'date'));
				break;
			case 'select':
			case 'reference':
				$res = $this->_removeEmpties(array_get($_POST, 'params_'.str_replace('.', '_', $field), Array()));
				break;
			default:
				$res = array_get($_POST, 'params_'.str_replace('.', '_', $field));
			break;
		}
		return $res;
	}

	function _removeEmpties($ar)
	{
		$res = Array();
		foreach ($ar as $x) {
			if (($x != '')) {
				$res[] = $x;
			}
		}
		return $res;
	}

	function _getGroupAndCategoryRestrictionSQL($submitted_groupids, $from_date=NULL, $to_date=NULL, $membership_status=NULL)
	{
		global $db;
		$int_groupids = Array();
		$int_categoryids = Array();

		// sepearate the group IDs from cateogry IDs
		foreach ($submitted_groupids as $groupid) {
			if (substr($groupid, 0, 1) == 'c') {
				$int_categoryids[] = (int)substr($groupid, 1);
			} else {
				$int_groupids[] = (int)$groupid;
			}
		}
		
		if (!empty($int_categoryids)) {
			// Add the IDs of subcategories too
			$prevsubids = $int_categoryids;
			do {
				$sql = 'SELECT id FROM person_group_category WHERE parent_category IN ('.implode(',', $prevsubids).')';
				$subids = $db->queryCol($sql);
				check_db_result($subids);
				foreach ($subids as $id) $int_categoryids[] = (int)$id;
				$prevsubids = $subids;
			} while (!empty($subids));
		}

		// assemble the SQL clause to restrict group and category IDs
		$groupid_comps = Array();
		if (!empty($int_groupids)) {
			$groupid_comps[] = '(pgm.groupid IN ('.implode(',', $int_groupids).'))';
		}
		if (!empty($int_categoryids)) {
			$groupid_comps[] = '(pg.categoryid IN ('.implode(',', $int_categoryids).') AND pg.is_archived = 0)';
		}

		$res = implode(' OR ', $groupid_comps);


		if (!empty($from_date)) {
			// restrict the join date too
			$res = '('.$res.') AND pgm.created BETWEEN '.$db->quote($from_date).' AND '.$db->quote($to_date);
		}

		if (!empty($membership_status)) {
			$res .= ' AND pgm.membership_status IN ('.implode(',', array_map(Array($db, 'quote'), (array)$membership_status)).')';
		}

		return $res;
	}


	function getSQL($select_fields=NULL)
	{
		$db =& $GLOBALS['db'];
		$params = $this->getValue('params');
		if (empty($params)) return null;
		$query = Array();
		$query['from'] = 'person p 
						JOIN family f ON p.familyid = f.id
						';
		$query['order_by'] = $this->_quoteAliasAndColumn($params['sort_by']);
		if ($params['sort_by'] == 'f.family_name') {
			$query['order_by'] .= ', f.id';
		}
		$query['where'] = Array();

		// BASIC FILTERS
		foreach ($params['rules'] as $field => $values) {
			if ($field == 'date') {
				continue;
		
			} else if (isset($values['from'])) {
				if (($this->_field_details[$field]['type'] == 'datetime') && (strlen($values['from']) == 10)) {
					// we're searching on a datetime field using only date values
					// so extend them to prevent boundary errors
					$values['from'] .= ' 00:00';
					$values['to'] .= ' 23:59';
				}
				$query['where'][] = $field.' BETWEEN '.$db->quote($values['from']).' AND '.$db->quote($values['to']);
			} else {
				$values = (array)$values;
				switch (count($values)) {
					case 0:
						$query['where'][] = $field.' = 0';
					case 1:
						$query['where'][] = $field.' = '.$db->quote(reset($values));
						break;
					default:
						$quoted_vals = Array();
						foreach ($values as $val) {
							$quoted_vals[] = $db->quote($val);
						}
						$query['where'][] = $field.' IN ('.implode(', ', $quoted_vals).')';
				}
			}
		}
		
		// DATE FIELD FILTERS
		if (empty($params['dates']) && !empty($params['rules']['date'])) {
			$params['dates'][] = $params['rules']['date'] + Array('criteria' => 'between');
		}
		
		foreach (array_get($params, 'dates', Array()) as $i => $values) {
			switch ($values['criteria']) {
				case 'empty':
					$query['from'] .= ' LEFT JOIN person_date pd'.$i.' ON pd'.$i.'.personid = p.id AND pd'.$i.'.typeid = '.(int)$values['typeid']."\n";						
					$query['where'][] = 'pd'.$i.'.personid IS NULL';
					break;

				case 'between':
					$between = 'BETWEEN '.$db->quote($values['from']).' AND '.$db->quote($values['to']);
					$w = Array();
					$w[] = '(pd'.$i.'.`date` NOT LIKE "-%" 
							AND pd'.$i.'.`date` '.$between.')';
					if ($values['anniversary']) {
						// Anniversary matches either have no year or a year before the 'to' year
						// AND their month-day fits the range either in the from year or the to year.
						$fromyearbetween = 'CONCAT('.$db->quote(substr($values['from'], 0, 4)).', RIGHT(pd'.$i.'.`date`, 6)) '.$between;
						$toyearbetween = 'CONCAT('.$db->quote(substr($values['to'], 0, 4)).', RIGHT(pd'.$i.'.`date`, 6)) '.$between;
						$w[] = '(pd'.$i.'.`date` LIKE "-%" AND '.$fromyearbetween.')';
						$w[] = '(pd'.$i.'.`date` LIKE "-%" AND '.$toyearbetween.')';
						$w[] = '(pd'.$i.'.`date` NOT LIKE "-%" AND pd'.$i.'.`date` < '.$db->quote($values['to']).' AND '.$fromyearbetween.')';
						$w[] = '(pd'.$i.'.`date` NOT LIKE "-%" AND pd'.$i.'.`date` < '.$db->quote($values['to']).' AND '.$toyearbetween.')';
					}
					$query['where'][] = '('.implode(' OR ', $w).')';
					// deliberate fallthrough...

				case 'any':
					$query['from'] .= ' JOIN person_date pd'.$i.' ON pd'.$i.'.personid = p.id AND pd'.$i.'.typeid = '.(int)$values['typeid']."\n";						
			}
		}

		// GROUP MEMBERSHIP FILTERS
		if (!empty($params['include_groups'])) {

			$include_groupids_clause = $this->_getGroupAndCategoryRestrictionSQL($params['include_groups'], $params['group_join_date_from'], $params['group_join_date_to'], array_get($params, 'group_membership_status'));
			$group_members_sql = 'SELECT personid 
								FROM person_group_membership pgm 
								JOIN person_group pg ON pgm.groupid = pg.id
								WHERE ('.$include_groupids_clause.')';
			$query['where'][] = 'p.id IN ('.$group_members_sql.')';
		}

		if (!empty($params['exclude_groups'])) {

			$exclude_groupids_clause = $this->_getGroupAndCategoryRestrictionSQL($params['exclude_groups']);
			$query['where'][] = 'p.id NOT IN (
									SELECT personid 
									FROM person_group_membership pgm
									JOIN person_group pg ON pgm.groupid = pg.id
									WHERE ('.$exclude_groupids_clause.')
								)';
		}

		//NOTE FILTERS
		if (!empty($params['note_phrase'])) {
			$note_sql = 'SELECT pn.personid, GROUP_CONCAT(an.Subject) as subjects
						FROM person_note pn
						JOIN abstract_note an ON an.id = pn.id
						WHERE an.details LIKE '.$GLOBALS['db']->quote('%'.$params['note_phrase'].'%').'
						OR an.subject LIKE '.$GLOBALS['db']->quote('%'.$params['note_phrase'].'%').'
						GROUP BY pn.personid';
			$query['from'] .= ' JOIN ('.$note_sql.') notes ON notes.personid = p.id ';
		}

		// ATTENDANCE FILTERS
		if (!empty($params['attendance_groupid'])) {
			$groupid = $params['attendance_groupid'] == '__cong__' ? 0 : $params['attendance_groupid'];
			$min_date = date('Y-m-d', strtotime('-'.(int)$params['attendance_weeks'].' weeks'));
			$operator = ($params['attendance_operator'] == '>') ? '>' : '<'; // nb whitelist because it will be used in the query directly
			$query['where'][] = '(SELECT SUM(present)/COUNT(*)*100 
									FROM attendance_record 
									WHERE date >= '.$GLOBALS['db']->quote($min_date).' 
									AND groupid = '.(int)$groupid.'
									AND personid = p.id) '.$operator.' '.(int)$params['attendance_percent'];
		}


		// GROUPING
		if (empty($params['group_by'])) {
			$grouping_field = '';
		} else if ($params['group_by'] == 'groupid') {
			if (!empty($params['include_groups'])) {
				$grouping_field = 'CONCAT(pg.name, '.$db->quote(' (#').', pg.id, '.$db->quote(')').'), ';
				$query['from'] .= ' JOIN person_group_membership pgm ON p.id = pgm.personid
									JOIN person_group pg ON pg.id = pgm.groupid
									';
				$query['where'][] = $this->_getGroupAndCategoryRestrictionSQL($params['include_groups'], $params['group_join_date_from'], $params['group_join_date_to'], $params['group_membership_status']);
				$query['order_by'] = 'pg.name, '.$query['order_by'];
			} else {
				$grouping_field = '';
			}
		} else {
			$grouping_field = $params['group_by'].', ';
			if (FALSE !== ($key = array_search($params['group_by'], $params['show_fields']))) {
				unset($params['show_fields'][$key]);
			}
			$query['order_by'] = $grouping_field.$query['order_by'];
		}

		// DISPLAY FIELDS
		$joined_groups = FALSE;
		if (empty($select_fields)) {
			foreach ($params['show_fields'] as $field) {
				if (substr($field, 0, 2) == '--') continue; // they selected a separator
				switch ($field) {
					
					case 'groups':
					case 'membershipstatus':
						if (empty($params['include_groups'])) continue;
						
						if ($params['group_by'] == 'groupid') {
							// pg and pgm already joined for grouping purposes
							if ($field == 'groups') {
								$query['select'][] = 'GROUP_CONCAT(pg.name ORDER BY pg.name SEPARATOR '.$db->quote('<br />').') as person_groups';
							} else if ($field == 'membershipstatus') {
								$query['from'] .= ' LEFT JOIN person_group_membership_status pgms ON pgms.id = pgm.membership_status';
								$query['select'][] = 'pgms.label as `Membership Status`';
							}							
						} else {
							if (!$joined_groups) {
								$query['from'] .= ' LEFT JOIN person_group_membership pgm ON p.id = pgm.personid
													JOIN person_group pg ON pg.id = pgm.groupid
													';
								$query['where'][] = $this->_getGroupAndCategoryRestrictionSQL($params['include_groups'], $params['group_join_date_from'], $params['group_join_date_to']);
								$joined_groups = TRUE;
							}
							if ($field == 'groups') {
								$query['select'][] = 'GROUP_CONCAT(pg.name ORDER BY pg.name SEPARATOR '.$db->quote('<br />').') as person_groups';
							} else if ($field == 'membershipstatus') {
								$query['from'] .= ' LEFT JOIN person_group_membership_status pgms ON pgms.id = pgm.membership_status';
								$query['select'][] = 'GROUP_CONCAT(pgms.label ORDER BY pg.name SEPARATOR '.$db->quote('<br />').') as `Membership Status`';
							}
						}

						break;
					case 'view_link':
					case 'edit_link':
					case 'checkbox':
					case 'photo':
						$query['select'][] = 'p.id as '.$field;
						break;
					case 'all_members':
						$query['from'] .= 'JOIN (
											SELECT familyid, IF (
												GROUP_CONCAT(DISTINCT last_name) = ff.family_name, 
												GROUP_CONCAT(first_name ORDER BY age_bracket, gender DESC SEPARATOR ", "),
												GROUP_CONCAT(CONCAT(first_name, " ", last_name) ORDER BY age_bracket, gender DESC SEPARATOR ", ")
											  ) AS `names`
											FROM person pp
											JOIN family ff ON pp.familyid = ff.id
											WHERE pp.status <> "archived"
											GROUP BY familyid
										   ) all_members ON all_members.familyid = p.familyid
										   ';
						$query['select'][] = 'all_members.names as `All Family Members`';
						break;
					case 'adult_members':
						// For a left join to be efficient we need to 
						// create a temp table with an index rather than
						// just joining a subquery.
						$r1 = $GLOBALS['db']->query('CREATE TEMPORARY TABLE _family_adults'.$this->id.' (
													familyid int(10) not null primary key,
													names varchar(512) not null
													)');
						check_db_result($r1);
						$r2 = $GLOBALS['db']->query('INSERT INTO _family_adults'.$this->id.' (familyid, names)
											SELECT familyid, IF (
												GROUP_CONCAT(DISTINCT last_name) = ff.family_name, 
												GROUP_CONCAT(first_name ORDER BY age_bracket, gender DESC SEPARATOR ", "),
												GROUP_CONCAT(CONCAT(first_name, " ", last_name) ORDER BY age_bracket, gender DESC SEPARATOR ", ")
											  )
											FROM person pp
											JOIN family ff ON pp.familyid = ff.id
											WHERE pp.status <> "archived" AND pp.age_bracket = 0
											GROUP BY familyid');
						check_db_result($r2);
						$query['from'] .= 'LEFT JOIN _family_adults'.$this->id.' ON _family_adults'.$this->id.'.familyid = p.familyid
											';
						$query['select'][] = '_family_adults'.$this->id.'.names as `Adult Family Members`';
						break;
					case 'attendance_percent':
						if ($params['attendance_groupid']) {
							$groupid = $params['attendance_groupid'] == '__cong__' ? 0 : $params['attendance_groupid'];
							$min_date = date('Y-m-d', strtotime('-'.(int)$params['attendance_weeks'].' weeks'));
							$query['select'][] = '(SELECT CONCAT(ROUND(SUM(present)/COUNT(*)*100), "%") 
													FROM attendance_record 
													WHERE date >= '.$GLOBALS['db']->quote($min_date).' 
													AND groupid = '.(int)$groupid.'
													AND personid = p.id) AS `Attendance`';
						}
						break;
					case 'attendance_numabsences':
						// The number of "absents" recorded since the last "present".
						if ($params['attendance_groupid']) {
							$groupid = $params['attendance_groupid'] == '__cong__' ? 0 : $params['attendance_groupid'];
							$query['select'][] = '(SELECT COUNT(*)
													FROM attendance_record ar
													WHERE groupid = '.(int)$groupid.'
													AND personid = p.id
													AND date > (SELECT COALESCE(MAX(date), "2000-01-01") FROM attendance_record ar2 WHERE ar2.personid = ar.personid AND present = 1)) AS `Running Absences`';
						}
						break;
					case 'actionnotes.subjects':
						$query['select'][] = '(SELECT GROUP_CONCAT(subject SEPARATOR ", ") 
												FROM abstract_note an 
												JOIN person_note pn ON an.id = pn.id 
												WHERE pn.personid = p.id
												AND an.status = "pending"
												AND an.action_date <= NOW()) AS `Notes`';
						break;

					case 'notes.subjects':
						if (empty($params['note_phrase'])) {
							$query['select'][] = '"" AS subjects';
							break;
						}
						// else deliberate fallthrough...
					default:
						if (substr($field, 0, 7) == 'date---') {
							$types = Person::getDateTypes();
							$dateid = substr($field, 7);
							if (isset($types[$dateid])) {
								$query['from'] .= 'LEFT JOIN person_date pd'.$dateid.' ON pd'.$dateid.'.personid = p.id AND pd'.$dateid.'.typeid = '.$db->quote($dateid)."\n";
								$query['select'][] = 'pd'.$dateid.'.`date` as '.$db->quote('DATE---'.$types[$dateid])."\n";
							}
						} else {
							$query['select'][] = $this->_quoteAliasAndColumn($field).' AS '.$db->quote($field);
						}
				}
			}
			$select_fields = $grouping_field.'p.id as ID, '.implode(', ', $query['select']);
		}

		// Order by
		if (substr($params['sort_by'], 0, 7) == 'date---') {
			$query['from'] .= 'LEFT JOIN person_date pdorder ON pdorder.personid = p.id AND pdorder.typeid = '.$db->quote(substr($query['order_by'], 7))."\n";
			// we want persons with a full date first, in chronological order.  Then persons with a yearless date, in order.  Then persons with no date.
			$query['order_by'] = 'IF (pdorder.`date` IS NULL, 3, IF (pdorder.`date` LIKE "-%", 2, 1)), pdorder.`date`';

		}

		if ($query['order_by'] == 'attendance_percent') {
			if (in_array('attendance_percent', $params['show_fields'])) {
				$query['order_by'] = '`Attendance` ASC';
			} else {
				// can't order by attendnace percent if it's not in the select  fall back to lastname.
				$query['order_by'] = 'p.last_name';
			}
		}
		if ($query['order_by'] == 'attendance_numabsences') {
			if (in_array('attendance_numabsences', $params['show_fields'])) {
				$query['order_by'] = '`Running Absences` DESC';
			} else {
				// can't order by attendnace absences if it's not in the select  fall back to lastname.
				$query['order_by'] = 'p.last_name';
			}
		}

		// Build SQL
		$sql = 'SELECT '.$select_fields.'
				FROM '.$query['from'].'
				';
		if (!empty($query['where'])) {
			$sql .= 'WHERE
					('.implode(")\n\tAND (", $query['where']).')
				';
		}
		$sql .= 'GROUP BY p.id ';
		if (array_get($params, 'group_by') == 'groupid') $sql .= ', pg.id ';
		$sql .= 'ORDER BY '.$query['order_by'].', p.last_name, p.first_name';
		return $sql;
	}


	function getResultCount()
	{
		$db =& $GLOBALS['db'];
		$sql = $this->getSQL();
		if (is_null($sql)) return 0;
		$res = $db->query($sql);
		check_db_result($res);
		return $res->numRows();
	}


	function getResultPersonIDs()
	{
		$db =& $GLOBALS['db'];
		$sql = $this->getSQL('p.id');
		if (is_null($sql)) return Array();
		$res = $db->queryCol($sql);
		check_db_result($res);
		return $res;
	}	


	function printResults($format='html')
	{
		$db =& $GLOBALS['db'];
		$params = $this->getValue('params');

		$sql = $this->getSQL();
		if (is_null($sql)) return;

		if ($format == 'html' && in_array('checkbox', $params['show_fields'])) {
			echo '<form method="post" class="bulk-person-action">';
		}
		
		$grouping_field = $params['group_by'];
		if (empty($grouping_field)) {
			$res = $db->queryAll($sql, null, null, true, true);
			check_db_result($res);
			$this->_printResultSet($res, $format);
		} else {
			$res = $db->queryAll($sql, null, null, true, false, true);
			check_db_result($res);
			$this->_printResultGroups($res, $params, $format);
		}

		if ($res && ($format == 'html') && in_array('checkbox', $params['show_fields'])) {
			echo '<div class="no-print">';
			include 'templates/bulk_actions.template.php';
			echo '</div>';
			echo '</form>';
		}
	}

	function _printResultGroups($res, $params, $format)
	{
		foreach ($res as $i => $v) {
			if ($params['group_by'] != 'groupid') {
					$var = $params['group_by'][0] == 'p' ? '_dummy_person' : '_dummy_family';
					$fieldname = substr($params['group_by'], 2);
					$this->$var->setValue($fieldname, $i);
					$heading = $this->$var->getFormattedValue($fieldname);
			} else {
					$heading = $i;
			}
			$this->_printResultSet($v, $format, $heading);
		}
	}


	function _printResultSet($x, $format, $heading=NULL)
	{
		if ($format == 'csv') {
			$this->_printResultSetCsv($x, $heading);
		} else {
			$this->_printResultSetHtml($x, $heading);
		}
	}

	function _printResultSetCsv($x, $groupingname)
	{
		if (empty($x)) return;
		static $headerprinted = false;
		if (!$headerprinted) {
			foreach (array_keys(reset($x)) as $heading) {
				if (in_array($heading, Array('view_link', 'edit_link', 'checkbox'))) continue;
				echo '"';
				switch($heading) {
					case 'person_groups':
						echo 'Groups';
						break;
					case 'notes.subjects':
					case 'actionnotes.subjects':
						echo 'Notes';
						break;
					default:
						if (isset($this->_field_details[$heading])) {
							echo $this->_field_details[$heading]['label'];
						} else if (substr($heading, 0, 7) == 'DATE---') {
							echo ucfirst(substr($heading, 7));
						} else {
							echo ucfirst($heading);
						}
				}
				echo '",';
			}
			if ($groupingname) echo 'GROUPING';
			echo "\r\n";
			$headerprinted = TRUE;
		}
		foreach ($x as $row) {
			foreach ($row as $label => $val) {
				if (in_array($label, Array('view_link', 'edit_link', 'checkbox'))) continue;
				echo '"';
				if (isset($this->_field_details[$label])) {
					$var = $label[0] == 'p' ? '_dummy_person' : '_dummy_family';
					$fieldname = substr($label, 2);
					echo str_replace('"', '""', $this->$var->getFormattedValue($fieldname, $val));
				} else if (substr($label, 0, 7) == 'DATE---') {
					echo $val ? format_date($val) : '';
				} else {
					echo str_replace('"', '""', $val);
				}
				echo '",';
			}
			if ($groupingname) echo str_replace('"', '""', $groupingname);
			echo "\r\n";
		}
	}

	function _printResultSetHtml($x, $heading)
	{
		if ($heading) {
			echo '<h3>'.$heading.'</h3>';
		}
		if (empty($x)) {
			?>
			<i>No matching persons were found</i>
			<?php
			return;
		}
		?>
		<table class="table table-striped table-condensed table-hover table-min-width clickable-rows query-results">
			<thead>
				<tr>
				<?php
				foreach (array_keys(reset($x)) as $heading) {
					?>
					<th<?php echo $this->_getColClasses($heading); ?>>
						<?php
						switch($heading) {
							case 'person_groups':
								echo 'Groups';
								break;
							case 'notes.subjects':
								echo 'Notes';
								break;
							case 'edit_link':
							case 'view_link':
								break;
							case 'checkbox':
								echo '<input type="checkbox" class="select-all" title="Select all" />';
								break;
							default:
								if (isset($this->_field_details[$heading])) {
									echo $this->_field_details[$heading]['label'];
								} else if (substr($heading, 0, 7) == 'DATE---') {
									echo ucfirst(substr($heading, 7));
								} else {
									echo ucfirst($heading);
								}
						}
						?>
					</th>
					<?php
				}
				?>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ($x as $row) {
				?>
				<tr>
				<?php
				foreach ($row as $label => $val) {
					?>
					<td<?php echo $this->_getColClasses($label); ?>>
						<?php
						switch ($label) {
							case 'edit_link':
								?>
								<a class="med-popup no-print" href="?view=_edit_person&personid=<?php echo $row[$label]; ?>"><i class="icon-wrench"></i>Edit</a>
								<?php
								break;
							case 'view_link':
								?>
								<a class="med-popup no-print" href="?view=persons&personid=<?php echo $row[$label]; ?>"><i class="icon-user"></i>View</a>
								<?php
								break;
							case 'checkbox':
								?>
								<input name="personid[]" type="checkbox" value="<?php echo $row[$label]; ?>" class="no-print" />
								<?php
								break;
							case 'photo':
								?>
								<a class="med-popup" href="?view=persons&personid=<?php echo $row[$label]; ?>">
								<img height="60" src="?call=person_photo&personid=<?php echo $row[$label]; ?>" />
								</a>
								<?php
								break;
							default:
								if (isset($this->_field_details[$label])) {
									$var = $label[0] == 'p' ? '_dummy_person' : '_dummy_family';
									$fieldname = substr($label, 2);
									$this->$var->setValue($fieldname, $val);
									$this->$var->printFieldValue($fieldname);
								} else if (substr($label, 0, 7) == 'DATE---') {
									echo $val ? format_date($val) : '';
								} else {
									echo ents($val);
								}
						}
						?>
					</td>
					<?php
				}
				?>
				</tr>
				<?php
			}
			?>
			</tbody>
		</table>
		<p><strong><?php echo count($x); ?> persons listed</strong></p>
		<?php
	}


	function validateFields()
	{
		if (!parent::validateFields()) return FALSE;

		return TRUE;
	}


	function save()
	{
		if ($this->id == 'TEMP') {
			$_SESSION['saved_query'] = serialize($this);
			return TRUE;
		} else {
			return parent::save();
		}
	}

	function load($id)
	{
		if ($id == 'TEMP') {
			if (!empty($_SESSION['saved_query'])) {
				$x = unserialize($_SESSION['saved_query']);
				$this->populate($x->id, $x->values);
			}
			return TRUE;
		} else {
			return parent::load($id);
		}
	}

	function _getColClasses($heading)
	{
		$class_list = '';
		if (in_array($heading, Array('edit_link', 'view_link', 'checkbox'))) {
			$class_list[] = 'no-print narrow';
		}
		if ($heading == 'checkbox') {
			$class_list[] = 'selector narrow';
		}
		$classes = empty($class_list) ? '' : ' class="'.implode(' ', $class_list).'"';
		return $classes;
	}
	
	private function _quoteAliasAndColumn($field)
	{
		$db = $GLOBALS['db'];
		$bits = explode('.', $field);
		if (count($bits) == 1) {
			return $db->quoteIdentifier($field);
		} else {
			list ($t, $f) = $bits;
			return $db->quoteIdentifier($t).'.'.$db->quoteIdentifier($f);
		}
	}


}
?>
