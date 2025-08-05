<?php
include_once 'include/db_object.class.php';
class Person_Query extends DB_Object
{
	private $_field_details = Array();
	private $_query_fields = Array('p.status', 'p.congregationid', 'p.age_bracketid', 'p.gender', 'f.address_suburb', 'f.address_state', 'f.address_postcode', 'p.creator', 'p.created', 'p.status_last_changed');
	private $_show_fields = Array(
		'p.first_name', 'p.last_name', 'f.family_name', 'p.age_bracketid', 'p.gender', 'p.status', 'p.congregationid', NULL,
		'p.email', 'p.mobile_tel', 'p.work_tel', 'f.home_tel', 'p.remarks',
		'f.address_street', 'f.address_suburb', 'f.address_state', 'f.address_postcode', NULL,
		'p.id', 'f.id', 'p.creator', 'p.created', 'f.created', 'p.status_last_changed', );
	private $_dummy_family = NULL;
	private $_dummy_person = NULL;
	private $_dummy_custom_field = NULL;
	private $_group_chooser_options_cache = NULL;

	private $_custom_fields = Array();

	const CUSTOMFIELDVAL_SEP = '__next__';
	const CUSTOMFIELD_PREFIX = 'CUSTOMFIELD---';

	function __construct($id=0)
	{
		if (empty($GLOBALS['JETHRO_INSTALLING']) && !empty($GLOBALS['system'])) {
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
			$this->_field_details['p.id'] = Array('label' => 'Person ID');
			$this->_field_details['f.id'] = Array('label' => 'Family ID');

			$this->_custom_fields = $GLOBALS['system']->getDBObjectData('custom_field', Array(), 'OR', 'rank');
			$this->_dummy_custom_field = new Custom_Field();
		}
		return parent::__construct($id);
	}

	function getInitSQL($table_name=NULL)
	{
		return "
			CREATE TABLE `person_query` (
			  `id` int(11) NOT NULL auto_increment,
			  `name` varchar(255) NOT NULL default '',
			  `creator` int(11) NOT NULL default '0',
			  `created` timestamp NOT NULL default CURRENT_TIMESTAMP,
			  `owner` int(11) DEFAULT NULL,
			  `params` text NOT NULL,
			  `mailchimp_list_id` varchar(255) NOT NULL default '',
			  `show_on_homepage` varchar(12) not null default '',
			  PRIMARY KEY  (`id`)
			) ENGINE=InnoDB ;
		";
	}

	protected static function _getFields()
	{
		$default_params = Array(
							'rules'			=> Array('p.status' => Array()),
							'show_fields'	=> Array('p.first_name', 'p.last_name', '', '', 'view_link', 'checkbox'),
							'group_by'		=> '',
							'sort_by'		=> 'p.last_name',
							'include_groups'	=> Array(),
							'exclude_groups'	=> Array(),
						  );
		if (empty($GLOBALS['JETHRO_INSTALLING'])) {
			foreach (Person_Status::getActive(FALSE) as $sid => $details) {
				$default_params['rules']['p.status'][] = $sid;
			}
		}
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
			'owner'			=> Array(
									'type'			=> 'reference',
									'references'	=> 'staff_member',
									'show_in_summary'	=> false,
								   ),
			'params'			=> Array(
									'type'			=> 'serialise',
									'editable'		=> false,
									'show_in_summary'	=> false,
									'default'		=> $default_params,

								   ),
			'mailchimp_list_id' => Array(
									'type'		=> 'text',
									'editable' => true,
									'default' => '',
									'placeholder' => '('._('Optional').')',
									'tooltip' => _('If you have a MailChimp list you would like to synchronise with the results of this report, enter the relevant List ID here and wait until the sync script runs.'),
			),
			'show_on_homepage' => Array(
									'type' => 'select',
									'editable'=> true,
									'default' => NULL,
									'options' => Array(
													'' => 'No',
													'auth' => 'Show for users with access to this report',
													'all' => 'Show for all users'),

			),
		);
	}

	function toString()
	{
		return $this->values['name'];
	}


	function printForm($prefix='', $fields=NULL)
	{
		$GLOBALS['system']->includeDBClass('person_group');
		$params = $this->_convertParams($this->getValue('params'));
		if (!$GLOBALS['user_system']->havePerm(PERM_MANAGEREPORTS) && ($this->getValue('owner') == NULL)) {
			// we are editing a shared report, but don't have permission to save a shared report
			// so we treat this as if it's a new private report
			$this->id = 0;
		}
		?>
		<h3>Find me people...</h3>

		<h4>whose person/family record matches these rules:</h4>
		<table class="table table-border table-auto-width indent-left table-condensed">
		<?php
		foreach ($this->_query_fields as $i) {
			$v = $this->_field_details[$i];
			unset($v['filter']); // remove the holds_persons restriction for congregations; there might be inactive congregations we want to analyse.
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
		if ($this->_custom_fields) {
			?>

			<h4>who have
				<?php
				$dlParams = Array(
					'type' => 'select',
					'options' => Array('OR' => 'any', 'AND' => 'all'),
				);
				print_widget('params_custom_field_logic', $dlParams, array_get($params, 'custom_field_logic', 'AND'));
				?>
				of the following custom fields...</h4>

			<table class="table table-border table-auto-width indent-left table-condensed">

			<?php
			if (empty($params['custom_fields'])) $params['custom_fields'] = Array();
			$dummyField = new Custom_Field();
			foreach ($this->_custom_fields as $fieldid => $fieldDetails) {
				$dummyField->populate($fieldid, $fieldDetails);
				?>
					<tr>
						<td>
							<label class="checkbox">
								<input autofocus="1" type="checkbox" name="enable_custom_field[]"
									   value="<?php echo $fieldid; ?>"
									   id="enable_custom_<?php echo $fieldid; ?>"
									   class="select-rule-toggle"
									   data-toggle="enable"
									   data-target="#custom-value-<?php echo $fieldid; ?> *"
									   <?php if (isset($params['custom_fields'][$fieldid])) echo 'checked="checked" '; ?>
								/>
								<strong><?php echo ents($fieldDetails['name']); ?></strong>
							</label>
						</td>
						<td id="custom-value-<?php echo $fieldid; ?>">
							<div class="select-rule-options" <?php if (!isset($params['custom_fields'][$fieldid])) echo 'style="display: none" '; ?>>
							<?php
							$value = array_get($params['custom_fields'], $fieldid, Array());
							switch ($fieldDetails['type']) {
								case 'date':
									$cparams = Array(
												'type' => 'select',
												'options' => Array(
													'any' => 'filled in with any value',
													'empty' => 'not filled in at all',
													'exact' => 'with exact value within the period...',
													'anniversary' => 'with exact value or anniversary within the period...',
													'not' => 'withOUT a value within the period...',
												),
												'attrs' => Array(
													'data-toggle' => 'visible',
													'data-target' => 'row .datefield-rule-period',
													'data-match-attr' => 'data-select-rule-type'
												),
											);
									print_widget('params_custom_field_'.$fieldid.'_criteria', $cparams, array_get($value, 'criteria'));
									?>
									<div class="datefield-rule-period" data-select-rule-type="exact anniversary not">
										<table class="table-no-borders">
											<tr>
												<td class="narrow valign-middle">from</td>
												<td><?php $this->_printDateRangeBoundaryChooser('params_custom_field_'.$fieldid.'_from', array_get($value, 'from')); ?></td>
											</tr>
											<tr>
												<td class="narrow valign-middle">to</td>
												<td><?php $this->_printDateRangeBoundaryChooser('params_custom_field_'.$fieldid.'_to', array_get($value, 'to')); ?></td>
											</tr>
										</table>
									</div>

									<?php
									break;
								case 'select':
									$cparams = Array(
												'type' => 'select',
												'options' => Array(
													'any' => 'filled in with any value',
													'empty' => 'not filled in',
													'contains' => 'with value that contains',
												),
												'attrs' => Array(
													'data-toggle' => 'visible',
													'data-target' => 'row .multi-select',
													'data-match-attr' => 'data-select-rule-type'
												),
											);
									print_widget('params_custom_field_'.$fieldid.'_criteria', $cparams, array_get($value, 'criteria'));
									$vparams = Array(
										'type' => 'select',
										'options' => $dummyField->getOptions(),
										'allow_multiple' => true,
										'attrs' => Array(
											'data-select-rule-type' => 'contains'
										)
									);
									if (!empty($fieldDetails['params']['allow_other'])) {
										$vparams['options'][0] = '[Other]';
									}
									print_widget(
										'params_custom_field_'.$fieldid.'_val',
										$vparams,
										array_get($value, 'val')
									);
									break;
								case 'text':
								case 'link':
									$cparams = Array(
												'type' => 'select',
												'options' => Array(
													'any' => 'filled in with any value',
													'empty' => 'not filled in',
													'equal' => 'with value equal to',
												),
												'attrs' => Array(
													'data-toggle' => 'visible',
													'data-target' => 'row input[data-select-rule-type]',
													'data-match-attr' => 'data-select-rule-type'
												),
											);
									print_widget('params_custom_field_'.$fieldid.'_criteria', $cparams, array_get($value, 'criteria'));
									$vparams = Array(
										'type' => 'text',
										'attrs' => Array(
											'data-select-rule-type' => 'equal'
										)
									);
									print_widget(
										'params_custom_field_'.$fieldid.'_val',
										$vparams,
										array_get(array_get($params['custom_fields'], $fieldid, Array()), 'val')
									);
									break;
							}
							?>
							</div>
						</td>
					</tr>
					<?php
			}
			?>
			</table>
			<?php
		}
		?>

		<h4>who <strong>are</strong> in one or more of these groups:
			<i class="clickable icon-question-sign" data-toggle="visible" data-target="#grouptooltip"></i><div class="help-block custom-field-tooltip" id="grouptooltip" style="display: none; font-weight: normal">(This rule ignores any archived groups)</div>
		</h4>
		<div class="indent-left">


			<?php
			$gotGroups = Person_Group::printMultiChooser('include_groupids', array_get($params, 'include_groups', Array()), Array(), TRUE);

			if ($gotGroups) {
				?>
				<div class="indent-left">
					<label class="checkbox" style="margin-top: 1ex">
						<input type="checkbox" name="enable_group_membership_status" value="1"
							   data-toggle="visible" data-target="#group-membership-status"
								<?php if (!empty($params['group_membership_status'])) echo 'checked="checked"'; ?>
						/>
						with membership status of...
					</label>
					<span id="group-membership-status"
							<?php if (empty($params['group_membership_status'])) echo 'style="display:none"'; ?>
					>
						<?php Person_Group::printMembershipStatusChooser('group_membership_status', array_get($params, 'group_membership_status'), true); ?>
					</span>

					<label class="checkbox" style="margin-top: 1ex">
						<input type="checkbox" name="enable_group_join_date" value="1"
							   data-toggle="visible" data-target="#group-join-dates"
								<?php if (!empty($params['group_join_date_from'])) echo 'checked="checked"'; ?>
						/>
						and joined the group between...
					</label>
					<span id="group-join-dates"
								<?php if (empty($params['group_join_date_from'])) echo 'style="display:none"'; ?>
						  >
					<?php print_widget('group_join_date_from', Array('type' => 'date'), array_get($params, 'group_join_date_from')); ?>
					and <?php print_widget('group_join_date_to', Array('type' => 'date'), array_get($params, 'group_join_date_to')); ?>
					</span>
				</div>
				<?php
			}
			?>
		</div>

	<?php
	if ($gotGroups) {
		?>
		<h4>
			who are <strong>not</strong> in any of these groups:
			<i class="clickable icon-question-sign" data-toggle="visible" data-target="#grouptooltip2"></i><div class="help-block custom-field-tooltip" id="grouptooltip2" style="display: none; font-weight: normal">(This rule ignores any archived groups)</div>
		</h4>
		<div class="indent-left">
			<?php
			Person_Group::printMultiChooser('exclude_groupids', array_get($params, 'exclude_groups', Array()), Array(), TRUE);
			?>
			<div class="indent-left">
				<label class="checkbox" style="margin-top: 1ex">
					<input type="checkbox" name="enable_exclude_group_membership_status" value="1"
						   data-toggle="visible" data-target="#exclude-group-membership-status"
							<?php if (!empty($params['exclude_group_membership_status'])) echo 'checked="checked"'; ?>
					/>
					with membership status of...
				</label>
				<span id="exclude-group-membership-status"
						<?php if (empty($params['exclude_group_membership_status'])) echo 'style="display:none"'; ?>
				>
					<?php Person_Group::printMembershipStatusChooser('exclude_group_membership_status', array_get($params, 'exclude_group_membership_status'), true); ?>
				</span>
			</div>
		</div>
		<?php
	}
?>

        <h4>who <strong>have a family member</strong> in one or more of these groups:
            <i class="clickable icon-question-sign" data-toggle="visible" data-target="#grouptooltip3"></i><div class="help-block custom-field-tooltip" id="grouptooltip3" style="display: none; font-weight: normal">For example, find the family of Youth Group members. In the "Show me" section, you may add 'Names of family member in the specified group' to see the family member who is in the picked group.</div>
        </h4>
        <div class="indent-left">


			<?php
            // Logic identical to 'include_groups' above, but with 'familymember' in field names
			$gotGroups = Person_Group::printMultiChooser('include_familymember_groupids', array_get($params, 'include_familymember_groups', Array()), Array(), TRUE);

			if ($gotGroups) {
				?>
                <div class="indent-left">
                    <label class="checkbox" style="margin-top: 1ex">
                        <input type="checkbox" name="enable_familymember_group_membership_status" value="1"
                               data-toggle="visible" data-target="#familymember-membership-status"
							<?php if (!empty($params['familymember_group_membership_status'])) echo 'checked="checked"'; ?>
                        />
                        with membership status of...
                    </label>
                    <span id="familymember-membership-status"
							<?php if (empty($params['familymember_group_membership_status'])) echo 'style="display:none"'; ?>
					>
						<?php Person_Group::printMembershipStatusChooser('familymember_group_membership_status', array_get($params, 'familymember_group_membership_status'), true); ?>
					</span>

                    <label class="checkbox" style="margin-top: 1ex">
                        <input type="checkbox" name="enable_familymember_group_join_date" value="1"
                               data-toggle="visible" data-target="#familymember-group-join-dates"
							<?php if (!empty($params['familymember_group_join_date_from'])) echo 'checked="checked"'; ?>
                        />
                        and joined the group between...
                    </label>
                    <span id="familymember-group-join-dates"
								<?php if (empty($params['familymember_group_join_date_from'])) echo 'style="display:none"'; ?>
						  >
					<?php print_widget('familymember_group_join_date_from', Array('type' => 'date'), array_get($params, 'familymember_group_join_date_from')); ?>
					and <?php print_widget('familymember_group_join_date_to', Array('type' => 'date'), array_get($params, 'familymember_group_join_date_to')); ?>
					</span>
                </div>
				<?php
			}
			?>
        </div>

		<?php
		if ($GLOBALS['user_system']->havePerm(PERM_VIEWNOTE)) {
		?>
		<h4>who have a person/family note containing the phrase:</h4>
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
				'options' => Array(null => '', '__cong__' => 'their congregation'),
				'attrs' => Array('data-toggle' => 'enable', 'data-target' => '.attendance-input'),
			);
			$groups = $GLOBALS['system']->getDBObjectData('person_group', Array('!attendance_recording_days' => 0, 'is_archived' => 0), 'AND');
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
			<input name="attendance_percent" type="number" size="2" class="attendance-input" value="<?php echo (int)array_get($params, 'attendance_percent', 50); ?>" />%
			<i class="clickable icon-question-sign" data-toggle="visible" data-target="#attendancetooltip"></i><div class="help-block custom-field-tooltip" id="attendancetooltip" style="display: none; font-weight: normal">Note: Percentages are based on when people are marked 'present' vs 'absent'. Dates with <i>blank</i> attendance are ignored altogether.</div>


			<br />over the last <input name="attendance_weeks" type="number" size="2" class="attendance-input" value="<?php echo (int)array_get($params, 'attendance_weeks', 2); ?>" /> weeks
		</div>
		<?php
	}
	?>

		<h3 id="showme">For each person found, show me...</h3>
		<?php
		$show_fields = array_get($params, 'show_fields', Array());
		?>

		<table class="table-condensed expandable indent-left">
		<?php
		foreach ($show_fields as $chosen_field) {
			?>
			<tr>
				<td>
					<div class="insert-row-below" title="Click to insert a field here"></div>
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

					if ($GLOBALS['user_system']->havePerm(PERM_VIEWATTENDANCE)) {
						$options['--Z'] = '-----';
						$options['attendance_percent'] = 'Attendance rate during specified period';
						$options['attendance_numabsences'] = 'Number of absences since last marked present';
					}

					if ($GLOBALS['user_system']->havePerm(PERM_VIEWNOTE)) {
						$options['--Y'] = '-----';
						$options['notes.subjects'] = 'Person/family notes matching the phrase above';
						$options['actionnotes.subjects'] = 'Person/family notes requiring action';
					}

					if ($this->_custom_fields) {
						$options['--A'] = '-----';
						foreach ($this->_custom_fields as $fieldid => $fielddetails) {
							$options[self::CUSTOMFIELD_PREFIX.$fieldid] = ucfirst($fielddetails['name']);
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
					$options['familymember_group_members'] = 'Names of family member in the specified group';
					if ($GLOBALS['system']->featureEnabled('PHOTOS')) {
						$options['photo'] = 'Photo';
					}
					$options['--D'] = '-----';
					$options['view_link'] = 'A link to view person details';
					$options['edit_link'] = 'A link to edit the person';
					if ($GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
						$options['note_link'] = 'A link to add a note';
					}
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

		<h3>Group the results by...</h3>
		<div class="indent-left">
			<?php
			$options = Array(
						'' => _('Nothing - one big group'),
						'groupid' => _('Which person groups they are in'),
			);
			foreach ($this->_query_fields as $i) {
				$v = $this->_field_details[$i];
				if (in_array($v['type'], Array('select', 'reference'))) {
					$options[$i] = _($v['label']);
				}
			}
			foreach ($this->_custom_fields as $id => $f) {
				if ($f['type'] != 'select') continue; // restrict it to option fields for now.
				$options['custom-'.$id] = $f['name'];
			}
			print_widget(
					'group_by',
					Array('type' => 'select', 'options' => $options),
					array_get($params, 'group_by', '')
			);
			?>
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
		<option value="attendance_numabsences"<?php if ($sb == "attendance_numabsences") echo ' selected="selected"'; ?>>Number of absences since last marked present</option>
		<option disabled="disabled">------</option>
		<option value="membershipstatus"<?php if ($sb == "membershipstatus") echo ' selected="selected"'; ?>>Group membership status</option>
		<?php
		if ($this->_custom_fields) {
			?>
			<option disabled="disabled">------</option>
			<?php
			foreach ($this->_custom_fields as $fieldid => $fielddetails) {
				$selected = ($sb == self::CUSTOMFIELD_PREFIX.$fieldid) || ($sb == 'date---'.$fieldid)
				?>
				<option value="<?php echo self::CUSTOMFIELD_PREFIX.$fieldid; ?>"<?php if ($selected) echo ' selected="selected"'; ?>><?php echo ents($fielddetails['name']); ?></option>
				<?php
			}
		}

		?>
		</select>

		<h3>I want to save this report...</h3>
		<div class="indent-left">
			<p>
			<label type="radio">
				<input type="radio" name="save_option" value="new" id="save_option_new"
					 data-toggle="enable"
				/>
				as a new report
			</label>
		<?php
		if (($this->id != 0) && ($this->canSave())) {
			?>
			<label type="radio">
				<input type="radio" name="save_option" value="replace" id="save_option_replace" <?php if ($this->id && ($this->id != 'TEMP')) echo 'checked="checked"'; ?>
					 data-toggle="enable"
					 />
				in place of its previous version
			</label>
			<?php
		}
		?>

			<label type="radio">
				<input type="radio" name="save_option"
					   value="temp"
					   id="save_option_temp"
						  <?php if (empty($this->id) || $this->id == 'TEMP') echo ' checked="checked"'; ?>
					   data-toggle="disable"
					   data-target="#save-options input, #save-options select"
				/>
				only temporarily as an ad-hoc report
			</label>
			</p>

			<table id="save-options">
				<tr>
					<td>Report title &nbsp;</td>
					<td>
						<?php $this->printFieldInterface('name'); ?>
					</td>
				</tr>
				<tr>
					<td>Visibility</td>
					<td>
						<?php
						if ($GLOBALS['user_system']->havePerm(PERM_MANAGEREPORTS)) {
							$visibilityParams = Array(
								'type' => 'select',
								'options' => Array(0 => 'Visible to everyone', 1 => 'visible only to me'),
							);
							print_widget('is_private', $visibilityParams, $this->getValue('owner') !== NULL);
						} else {
							echo _('Only visible to me');
						}
						?>
					</td>
				</tr>
				<tr>
					<td>Show on home page?</td>
					<td>
						<?php
						$this->printFieldInterface('show_on_homepage');
						?>
					</td>
				</tr>
			<?php
			if (strlen(ifdef('MAILCHIMP_API_KEY')) && $GLOBALS['user_system']->havePerm(PERM_SYSADMIN)) {
				?>
				<tr>
					<td>Mailchimp List ID</td>
					<td><?php $this->printFieldInterface('mailchimp_list_id'); ?></td>
				</tr>
				<?php
			}
			?>
			</table>
		</div>
		<?php


	}


	private function _printDateRangeBoundaryChooser($fieldname, $value)
	{
		static $counter = 0;
		$counter++;
		
		// NOTE: we just put the raw value as the value of the option element,
		// and the javascript takes it from there - setting the visible label and populating all the inputs within the dropdown.
		$textValue = '(Choose date)'; // Will be updated by JS if there is an existing value.
		$exactDate = date('Y-m-d');
		?>
		<span class="dropdown date-range-picker" id="<?php echo $fieldname; ?>-picker">
			<select name="<?php echo $fieldname; ?>" class="dropdown-toggle" data-toggle="dropdown">
				<option value="<?php echo ents($value); ?>"><?php echo $textValue; ?></option>
			</select>
			<div class="dropdown-menu">
			<table>
				<tr>
					<td>
						<label class="radio nowrap">
							<input type="radio" name="drp_<?php echo $counter; ?>_val-type" value="any">
							<strong>any date</strong>
						</label>
					</td>
					<td>(open ended)</td>
				</tr>
				<tr>
					<td>
						<label class="radio nowrap">
						<input type="radio" name="drp_<?php echo $counter; ?>_val-type" value="exact">
						<strong>exact date</strong>
						</label>
					</td>
					<td><?php print_widget('drp_exact', Array('type'=>'date'), $exactDate); ?></td>
				</tr>
				<tr>
					<td>
						<label class="radio nowrap">
							<input type="radio" name="drp_<?php echo $counter; ?>_val-type" value="relative">											
							<strong>relative date</strong>
						</label>
					</td>
					<td class="relative">
						<input type="number" name="drp_relative_y"> years, <input  style="width: 4.5ex !important"  name="drp_relative_m" type="number"> months and <input name="drp_relative_d" type="number"> days 
						<br />
						<?php print_widget('drp_relative_direction',
											Array(
												'type'=>'select',
												'options' => Array('-'=>'before', '+'=>'after')
											),
											null
							);
						?>
						the report date
					</td>
				</tr>
				</table>
				<button type="button" class="btn cancel">Cancel</button>
				<button type="button" class="btn btn-primary save">Save</button>																							
			</div>
		</span>	
		<?php
	}

	function processForm($prefix='', $fields=NULL)
	{
		switch ($_POST['save_option']) {
			case 'new':
				$this->populate(0, Array());
				$this->processFieldInterface('name');
				if ($GLOBALS['user_system']->havePerm(PERM_SYSADMIN)) {
					$this->processFieldInterface('mailchimp_list_id');
				}
				$this->setValue('owner', $GLOBALS['user_system']->getCurrentUser('id'));
				if ($GLOBALS['user_system']->havePerm(PERM_MANAGEREPORTS)) {
					// Only those with mange-reports permission can save shared reports.
					if (empty($_POST['is_private'])) {
						$this->setValue('owner', NULL);
					}
				}
				$this->processFieldInterface('show_on_homepage');
				break;
			case 'replace':
				if (($this->getValue('owner') === NULL) && !$GLOBALS['user_system']->havePerm(PERM_MANAGEREPORTS)) {
					trigger_error("You do not have permission to overwrite saved reports", E_USER_ERROR); exit;
				}
				$this->processFieldInterface('name');
				if ($GLOBALS['user_system']->havePerm(PERM_SYSADMIN)) {
					$this->processFieldInterface('mailchimp_list_id');
				}
				$this->setValue('owner', $GLOBALS['user_system']->getCurrentUser('id'));
				if ($GLOBALS['user_system']->havePerm(PERM_MANAGEREPORTS)) {
					// Only those with mange-reports permission can save shared reports.
					if (empty($_POST['is_private'])) {
						$this->setValue('owner', NULL);
					}
				}
				$this->processFieldInterface('show_on_homepage');
				break;
			case 'temp':
				$this->id = 'TEMP';
			break;
		}


		$params = $this->_convertParams($this->getValue('params'));

		// FIELD RULES
		$rules = Array();
		if (!empty($_POST['enable_rule'])) {
			foreach ($_POST['enable_rule'] as $field) {
				$rules[$field] = $this->_processRuleDetails($field);
			}
		}
		$params['rules'] = $rules;

		// CUSTOM FIELD RULES
		$params['custom_fields'] = Array();
		foreach ($this->_custom_fields as $fieldid => $fieldDetails) {
			if (in_array($fieldid, array_get($_REQUEST, 'enable_custom_field', Array()))) {
				switch ($this->_custom_fields[$fieldid]['type']) {
					case 'date':
						$params['custom_fields'][$fieldid] = Array(
							'criteria' => $_REQUEST['params_custom_field_'.$fieldid.'_criteria'],
							'from' => $_REQUEST['params_custom_field_'.$fieldid.'_from'],
							'to' => $_REQUEST['params_custom_field_'.$fieldid.'_to'],
						);
						break;
					case 'select':
					case 'text':
					case 'link':
						$params['custom_fields'][$fieldid] = Array(
							'criteria' => $_REQUEST['params_custom_field_'.$fieldid.'_criteria'],
							'val' => array_get($_REQUEST, 'params_custom_field_'.$fieldid.'_val')
						);
						break;
				}
			}
		}
		if (!empty($_REQUEST['params_custom_field_logic'])) {
			$params['custom_field_logic'] = $_REQUEST['params_custom_field_logic'] == 'OR' ? 'OR' : 'AND';
		}

		// GROUP RULES
		$params['include_groups'] = array_remove_empties(array_get($_POST, 'include_groupids', Array()));
		if (!empty($_REQUEST['enable_group_membership_status'])) {
			$params['group_membership_status'] = array_get($_POST, 'group_membership_status');
		} else {
			$params['group_membership_status'] = Array();
		}
		$params['group_join_date_from'] = empty($_POST['enable_group_join_date']) ? NULL : process_widget('group_join_date_from', Array('type' => 'date'));
		$params['group_join_date_to'] = empty($_POST['enable_group_join_date']) ? NULL : process_widget('group_join_date_to', Array('type' => 'date'));

		$params['exclude_groups'] = array_remove_empties(array_get($_POST, 'exclude_groupids', Array()));
		if (!empty($_REQUEST['enable_exclude_group_membership_status'])) {
			$params['exclude_group_membership_status'] = array_get($_POST, 'exclude_group_membership_status');
		} else {
			$params['exclude_group_membership_status'] = Array();
		}

        // FAMILY MEMBER GROUP RULES
		$params['include_familymember_groups'] = array_remove_empties(array_get($_POST, 'include_familymember_groupids', Array()));
		if (!empty($_REQUEST['enable_familymember_group_membership_status'])) {
			$params['familymember_group_membership_status'] = array_get($_POST, 'familymember_group_membership_status');
		} else {
			$params['familymember_group_membership_status'] = Array();
		}
		$params['familymember_group_join_date_from'] = empty($_POST['enable_familymember_group_join_date']) ? NULL : process_widget('familymember_group_join_date_from', Array('type' => 'date'));
		$params['familymember_group_join_date_to'] = empty($_POST['enable_familymember_group_join_date']) ? NULL : process_widget('familymember_group_join_date_to', Array('type' => 'date'));
        

		// NOTE RULES
		$params['note_phrase'] = array_get($_POST, 'note_phrase');

		// ATTENDANCE RULES
		$params['attendance_groupid'] = array_get($_POST, 'attendance_groupid');
		$params['attendance_operator'] = array_get($_POST, 'attendance_operator');
		$params['attendance_percent'] = array_get($_POST, 'attendance_percent');
		$params['attendance_weeks'] = array_get($_POST, 'attendance_weeks');

		// SHOW FIELDS
		$params['show_fields'] = array_unique(array_remove_empties($_POST['show_fields']));

		// GROUP BY
		$params['group_by'] = $_POST['group_by'];
		if (empty($params['include_groups']) && ($params['group_by'] == 'groupid')) {
			add_message('No groups were chosen, so results will be shown all together', 'error');
			$params['group_by'] = '';
		}

		// SORT BY
		$params['sort_by'] = $_POST['sort_by'];
		if (in_array($params['sort_by'], Array('attendance_percent', 'attendance_numabsences', 'membershipstatus'))) {
			if (!in_array($params['sort_by'], $params['show_fields'])) {
				add_message("In order to sort by the requested field, it will also be displayed as a column", 'notice');
				$params['show_fields'][] = $params['sort_by'];
			}
		}
		if (empty($params['include_groups']) && ($params['sort_by'] == 'membershipstatus')) {
			add_message('No groups were chosen, so results cannot be ordered by membership status', 'error');
			$params['sort_by'] = '';
		}
		$this->setValue('params', $params);
	}

	function _processRuleDetails($field)
	{
		$res = Array();
		switch ($this->_field_details[$field]['type']) {
			case 'datetime':
				// todo: add fancy date range support here
				$res['from'] = process_widget('params_'.str_replace('.', '_', $field).'_from', Array('type' => 'date'));
				$res['to'] = process_widget('params_'.str_replace('.', '_', $field).'_to', Array('type' => 'date'));
				break;
			case 'select':
			case 'reference':
				$res = array_remove_empties(array_get($_POST, 'params_'.str_replace('.', '_', $field), Array()));
				break;
			default:
				$res = array_get($_POST, 'params_'.str_replace('.', '_', $field));
			break;
		}
		return $res;
	}

	/**
	 * Return a formatted version of custom field values
	 * @param string	$str		Raw value(s) of custom field from DB query
	 * @param int		$fieldid	Custom field ID
	 * @return string
	 */
	private function _formatCustomFieldValue($str, $fieldid)
	{
		$out = Array();
		if (!strlen($str)) return ;
		$this->_dummy_custom_field->populate($fieldid, $this->_custom_fields[$fieldid]);
		$rows = explode(self::CUSTOMFIELDVAL_SEP, $str);
		foreach ($rows as $row) {
			$out[] = $this->_dummy_custom_field->formatValue($row);
		}
		return implode("\n", $out);
	}

	/**
	 * Print HTML version of a custom field value
	 * @param string	$str		Raw value(s) of custom field from DB query
	 * @param int		$fieldid	Custom field ID
	 * @return void
	 */
	private function _printCustomFieldValue($str, $fieldid)
	{
		if (!strlen($str)) return ;
		$this->_dummy_custom_field->populate($fieldid, $this->_custom_fields[$fieldid]);
		$rows = explode(self::CUSTOMFIELDVAL_SEP, $str);
		foreach ($rows as $i => $row) {
			if ($i > 0) echo "<br />";
			$this->_dummy_custom_field->printFormattedValue($row);
		}
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

		$res = '('.implode(' OR ', $groupid_comps).')';


		if (!empty($from_date)) {
			// restrict the join date too
			$res = '('.$res.') AND pgm.created BETWEEN '.$db->quote($from_date).' AND '.$db->quote($to_date);
		}

		if (!empty($membership_status)) {
			$res .= ' AND pgm.membership_status IN ('.implode(',', array_map(Array($db, 'quote'), (array)$membership_status)).')';
		}

		return $res;
	}


	function getSQL($custom_select_fields=NULL)
	{
		$db =& $GLOBALS['db'];

		$params = $this->_convertParams($this->getValue('params'));
		if (empty($params)) return null;
		$query = Array();
		$query['from'] = 'person p
						JOIN family f ON p.familyid = f.id
						';
		$query['where'] = Array();
		$query['group_by'] = Array('p.id');

		// BASIC FILTERS
		foreach ($params['rules'] as $field => $values) {
			if ($field == 'date') {
				continue;

			} else if (is_array($values) && isset($values['from'])) {
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
						$query['where'][] = '(('.$field.' = 0) OR ('.$field.' IS NULL))';
						break;
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

		// CUSTOM FIELD FILTERS
		$customFieldWheres = Array();
		foreach (array_get($params, 'custom_fields', Array()) as $fieldid => $values) {
			$query['from'] .= ' LEFT JOIN custom_field_value pd'.$fieldid.' ON pd'.$fieldid.'.personid = p.id AND pd'.$fieldid.'.fieldid = '.(int)$fieldid."\n";
			switch ($this->_custom_fields[$fieldid]['type']) {
				case 'date':
					if ($values['criteria'] == 'between') {
						$values['criteria'] = $values['anniversary'] ? 'anniversary' : 'exact';
					}
					switch ($values['criteria']) {
						case 'any':
							$customFieldWheres[] = 'pd'.$fieldid.'.`personid` IS NOT NULL';
							break;

						case 'empty':
							$customFieldWheres[] = 'pd'.$fieldid.'.personid IS NULL';
							break;

						case 'exact':
						case 'anniversary':

							$from = $to = NULL;
							foreach (Array('from','to') as $k) {
								$v = $values[$k];
								$matches = Array();
								if ($v == '*') {
									$$k = NULL;
								} else if (preg_match(("/([-+])(\d+)y(\d+)m(\d+)d/"), $v, $matches)) {
									// relative date - convert it to an absolute now.
									$sym=$matches[1]; // + or -
									$$k = date('Y-m-d', strtotime($sym.($matches[2] ?? 0).' years '.$sym.($matches[3] ?? 0).' months '.$sym.($matches[4] ?? 0).' days'));
								} else {
									// absolute date
									$$k = $v;
								}
								//bam("$k date $v = ".$$k);
							}

						    $valExp = 'pd'.$fieldid.'.value_date';
						    if ($from && $to) {
								$betweenExp = 'BETWEEN '.$db->quote($from).' AND '.$db->quote($to);
							} elseif ($from) {
								$betweenExp = '>= '.$db->quote($from);
							} elseif ($to) {
								$betweenExp = '<= '.$db->quote($to);
							} else {
								// from unlimited to unlimited
								$betweenExp = ' IS NOT NULL';

							}
							$w = Array();
							$w[] = "$valExp NOT LIKE '-%' AND $valExp $betweenExp";
							if ($values['criteria'] == 'anniversary') {
								$qFromYear = $db->quote(substr($from, 0, 4));
								$qToYear = $db->quote(substr($to, 0, 4));

								$w[] = "$valExp LIKE '-%' AND (
											CONCAT($qFromYear, $valExp) $betweenExp
											OR CONCAT($qToYear, $valExp) $betweenExp
										)";
								$w[] = "$valExp NOT LIKE '-%' AND (
											CONCAT($qFromYear, RIGHT($valExp, 6)) $betweenExp
											OR CONCAT($qToYear, RIGHT($valExp, 6)) $betweenExp
										)";
							}
							$customFieldWheres[] = '(('.implode("\n) OR (\n", $w).'))';
							break;

					}
					break;

				case 'select':
					switch (array_get($values, 'criteria', 'contains')) {
						case 'contains':
							if ($values['val']) {
								$ids = implode(',', array_map(Array($db, 'quote'), $values['val']));
								$xrule = '(pd'.$fieldid.'.value_optionid IN ('.$ids.'))';
								if (in_array(0, $values['val'])) {
									// 'other' option
									$xrule = '('.$xrule.' OR (pd'.$fieldid.'.value_text IS NOT NULL))';
								}
								$customFieldWheres[] = $xrule;
							} else {
								// No options were picked for a select list custom field. Same as 'empty' ('not filled in')
								$customFieldWheres[] = '(pd'.$fieldid.'.value_optionid IS NULL AND pd'.$fieldid.'.value_text IS NULL)';
							}
								break;
							case 'any':
								$customFieldWheres[] = '(pd'.$fieldid.'.value_optionid IS NOT NULL OR pd'.$fieldid.'.value_text IS NOT NULL)';
								break;
							case 'empty':
								$customFieldWheres[] = '(pd'.$fieldid.'.value_optionid IS NULL AND pd'.$fieldid.'.value_text IS NULL)';
								break;
						}
					break;

				case 'text':
				case 'link':
					switch (array_get($values, 'criteria', 'equals')) {
						case 'equal':
							$customFieldWheres[] = '(pd'.$fieldid.'.value_text = '.$db->quote($values['val']).')';
							break;
						case 'any':
							$customFieldWheres[] = '(pd'.$fieldid.'.value_text IS NOT NULL)';
							break;
						case 'empty':
							$customFieldWheres[] = '(pd'.$fieldid.'.value_text IS NULL)';
							break;
					}
					break;
					break;
			}
		}
		if (!empty($customFieldWheres)) {
			$logic = array_get($params, 'custom_field_logic', 'AND');
			$query['where'][] = '(('.implode(') '.$logic.' (', $customFieldWheres).'))';
		}

		// GROUP MEMBERSHIP FILTERS
		if (!empty($params['include_groups'])) {

			$include_groupids_clause = $this->_getGroupAndCategoryRestrictionSQL(
												$params['include_groups'],
												array_get($params, 'group_join_date_from'),
												array_get($params, 'group_join_date_to'),
												array_get($params, 'group_membership_status'));
			$group_members_sql = 'SELECT personid
								FROM person_group_membership pgm
								JOIN person_group pg ON pgm.groupid = pg.id
								WHERE ('.$include_groupids_clause.')';
			$query['where'][] = 'p.id IN ('.$group_members_sql.')';
		}
        
		if (!empty($params['include_familymember_groups'])) {
            // "have a family member in one or more of these groups". E.g. family members (e.g. parents) of Youth Group members. #1104

            // Logic identical to 'include_groups' above
			$include_familymember_groupids_clause = $this->_getGroupAndCategoryRestrictionSQL(
												$params['include_familymember_groups'],
												array_get($params, 'familymember_group_join_date_from'),
												array_get($params, 'familymember_group_join_date_to'),
												array_get($params, 'familymember_group_membership_status'));

            // subquery returning <personid, familymember_name> for every familymember in the <include_familymember_groups> groups.
            // E.g. if little Jimmy (id 3) in Youth Group has parents with ids 1 and 2, this returns (1,"Jimmy"),(2,"Jimmy").
            // Note, Jimmy himself is excluded, i.e. we treat "being the family member in the group" (Jimmy) as different to "having a family member in the group" (his parents), and assume we usually want the latter.
			$familymember_group_members_sql = "SELECT p.id AS personid, concat(familyperson.first_name, ' ',familyperson.last_name) AS familymember_name
								FROM person p
								JOIN person familyperson USING (familyid)
								JOIN person_group_membership pgm ON pgm.personid = familyperson.id
								JOIN person_group pg ON pgm.groupid = pg.id
								WHERE 
								p.id <> familyperson.id
								AND (".$include_familymember_groupids_clause.')';

			$query['from'] .= ' JOIN ('.$familymember_group_members_sql.') familymember_in_required_group ON (familymember_in_required_group.personid = p.id)';
//			$query['where'][] = 'familymember_in_required_group IN ('.$familymember_group_members_sql.')';
		}

		if (!empty($params['exclude_groups'])) {

			$exclude_groupids_clause = $this->_getGroupAndCategoryRestrictionSQL(
												$params['exclude_groups'],
												NULL, NULL,
												array_get($params, 'exclude_group_membership_status'));
			$query['where'][] = 'p.id NOT IN (
									SELECT personid
									FROM person_group_membership pgm
									JOIN person_group pg ON pgm.groupid = pg.id
									WHERE ('.$exclude_groupids_clause.')
								)';
		}

		//NOTE FILTERS
		if (!empty($params['note_phrase'])) {
			$note_sql = 'SELECT p.id as personid, GROUP_CONCAT(an.Subject SEPARATOR "\n") as subjects
						FROM abstract_note an
						LEFT JOIN person_note pn ON pn.id = an.id
						LEFT JOIN family_note fn ON fn.id = an.id
						LEFT JOIN person p ON (p.id = pn.personid) OR (p.familyid = fn.familyid)
						WHERE an.details LIKE '.$GLOBALS['db']->quote('%'.$params['note_phrase'].'%').'
						OR an.subject LIKE '.$GLOBALS['db']->quote('%'.$params['note_phrase'].'%').'
						GROUP BY p.id';
			$query['from'] .= ' JOIN ('.$note_sql.') notes ON (notes.personid = p.id)';
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
		$grouping_order = '';
		$grouping_field = '';
		if (!$this->hasGroupingField($params)) {
			$grouping_field = '';
		} else if ($params['group_by'] == 'groupid') {
			if (!empty($params['include_groups'])) {
				$grouping_field = 'CONCAT(pg.name, '.$db->quote(' (#').', pg.id, '.$db->quote(')').'), ';
				$query['from'] .= ' JOIN person_group_membership pgm ON p.id = pgm.personid
									JOIN person_group pg ON pg.id = pgm.groupid
									';
				$query['where'][] = $this->_getGroupAndCategoryRestrictionSQL(
											$params['include_groups'],
											array_get($params, 'group_join_date_from'),
											array_get($params, 'group_join_date_to'),
											array_get($params, 'group_membership_status')
									);
				$grouping_order = 'pg.name, ';
				$query['group_by'][] = 'pg.id';
			} else {
				$grouping_field = '';
			}
		} else if (0 === strpos($params['group_by'], 'custom-')) {
			list($null, $fieldid) = explode('-', $params['group_by']);
			$query['from'] .= ' LEFT JOIN custom_field_value cfvgroup
									ON cfvgroup.personid = p.id
										AND cfvgroup.fieldid = '.(int)$fieldid.'
							';
			$query['from'] .= ' LEFT JOIN custom_field_option cfogroup
									ON cfogroup.id = cfvgroup.value_optionid
								';
			$query['from'] .= ' LEFT JOIN custom_field cfgroup ON cfgroup.id = cfvgroup.fieldid
									';
			$grouping_order = 'IF(cfvgroup.personid IS NULL, 1, 0), '.Custom_Field::getSortValueSQLExpr('cfvgroup', 'cfogroup').', ';
			$grouping_field = Custom_Field::getRawValueSQLExpr('cfvgroup', 'cfgroup').', ';
			$query['group_by'][] = Custom_Field::getRawValueSQLExpr('cfvgroup', 'cfgroup');
		} else if ($params['group_by'] == 'p.congregationid') {
			$query['from'] .= ' LEFT JOIN congregation csort ON csort.id = p.congregationid
								';
			$grouping_order = 'csort.meeting_time, ';
			$grouping_field = $params['group_by'].', ';
		} else if ($params['group_by'] == 'p.age_bracketid') {
			$grouping_order = 'absort.`rank`, ';
			$grouping_field = 'p.age_bracketid, ';
		} else if ($params['group_by'] == 'p.status') {
			$query['from'] .= ' JOIN person_status psgsort ON psgsort.id = p.status
								';
			$grouping_order = 'psgsort.`rank`, ';
			$grouping_field = 'p.status, ';
		} else {
			// by some core field
			$grouping_order = $grouping_field = $params['group_by'].', ';
		}

		// DISPLAY FIELDS
		$joined_groups = FALSE;
		if ($custom_select_fields) {
			$select_fields = $custom_select_fields;
		} else {
			/*
			 * If the user chose to sort by Attendance or Absences but didn't
			 * include them in the list of required columns, just add them to the
			 * results.  There is client-side code to deal with this,
			 * but this check here is for extra robustness.
			 */
			if (($params['sort_by'] == 'attendance_percent') && !in_array('attendance_percent', $params['show_fields'])) {
				array_push($params['show_fields'],'attendance_percent');
			} else if (($params['sort_by'] == 'attendance_numabsences') && !in_array('attendance_numabsences', $params['show_fields'])){
				array_push($params['show_fields'],'attendance_numabsences');
			}
			if (empty($params['show_fields'])) {
				$params['show_fields'] = Array('p.first_name', 'p.last_name');
			}
			foreach ($params['show_fields'] as $field) {
				if (substr($field, 0, 2) == '--') continue; // they selected a separator
				switch ($field) {

					case 'groups':
					case 'membershipstatus':
						if (empty($params['include_groups'])) continue 2; // https://www.php.net/manual/en/migration73.incompatible.php

						if ($params['group_by'] == 'groupid') {
							/* pg and pgm already joined for grouping purposes */
							if ($field == 'groups') {
								$query['select'][] = 'GROUP_CONCAT(pg.name ORDER BY pg.name SEPARATOR "\n") as person_groups';
							} else if ($field == 'membershipstatus') {
								$query['from'] .= ' LEFT JOIN person_group_membership_status pgms ON pgms.id = pgm.membership_status';
								$query['select'][] = 'pgms.label as `Membership Status`';
							}
						} else {
							if (!$joined_groups) {
								$query['from'] .= ' LEFT JOIN person_group_membership pgm ON p.id = pgm.personid
													JOIN person_group pg ON pg.id = pgm.groupid
													';
								$query['where'][] = $this->_getGroupAndCategoryRestrictionSQL(
															$params['include_groups'],
															array_get($params, 'group_join_date_from'),
															array_get($params, 'group_join_date_to')
													);
								$joined_groups = TRUE;
							}
							if ($field == 'groups') {
								$query['select'][] = 'GROUP_CONCAT(DISTINCT pg.name ORDER BY pg.name SEPARATOR "\n") as person_groups';
							} else if ($field == 'membershipstatus') {
								$query['from'] .= ' LEFT JOIN person_group_membership_status pgms ON pgms.id = pgm.membership_status';
								$query['select'][] = 'GROUP_CONCAT(DISTINCT pgms.label ORDER BY pg.name SEPARATOR "\n") as `Membership Status`';
							}
						}

						break;
					case 'view_link':
					case 'edit_link':
					case 'note_link':
					case 'checkbox':
					case 'photo':
						$query['select'][] = 'p.id as '.$field;
						break;
					case 'all_members':
						$query['from'] .= '
										JOIN (
											SELECT familyid, IF (
												GROUP_CONCAT(DISTINCT last_name) = ff.family_name,
												GROUP_CONCAT(first_name ORDER BY ab.`rank`, gender DESC SEPARATOR ", "),
												GROUP_CONCAT(CONCAT(first_name, " ", last_name) ORDER BY ab.`rank`, gender DESC SEPARATOR ", ")
											  ) AS `names`
											FROM person pp
											JOIN person_status ps ON ps.id = pp.status
											JOIN age_bracket ab ON ab.id = pp.age_bracketid
											JOIN family ff ON pp.familyid = ff.id
											WHERE (NOT ps.is_archived)
											GROUP BY familyid
										) all_members ON all_members.familyid = p.familyid
										   ';
						$query['select'][] = 'all_members.names as `All Family Members`';
						break;
					case 'familymember_group_members':
						$query['select'][] = 'GROUP_CONCAT(DISTINCT familymember_in_required_group.familymember_name SEPARATOR ", ")  as `Family Members In Group`';
						break;
					case 'adult_members':
						/*
						 * For a left join to be efficient we need to
						 * create a temp table with an index rather than
						 * just joining a subquery.
						 */
						$r1 = $GLOBALS['db']->query('CREATE TEMPORARY TABLE _family_adults'.$this->id.' (
													familyid int(10) not null primary key,
													names varchar(512) not null
													)');
						$r2 = $GLOBALS['db']->query('INSERT INTO _family_adults'.$this->id.' (familyid, names)
											SELECT familyid, IF (
												GROUP_CONCAT(DISTINCT last_name) = ff.family_name,
												GROUP_CONCAT(first_name ORDER BY ab.`rank`, gender DESC SEPARATOR ", "),
												GROUP_CONCAT(CONCAT(first_name, " ", last_name) ORDER BY ab.`rank`, gender DESC SEPARATOR ", ")
											  )
											FROM person pp
											JOIN person_status ps ON ps.id = pp.status
											JOIN age_bracket ab ON pp.age_bracketid = ab.id
											JOIN family ff ON pp.familyid = ff.id
											WHERE (NOT ps.is_archived) AND ab.is_adult
											GROUP BY familyid');
						$query['from'] .= ' LEFT JOIN _family_adults'.$this->id.' ON _family_adults'.$this->id.'.familyid = p.familyid
											';
						$query['select'][] = '_family_adults'.$this->id.'.names as `Adult Family Members`';
						break;
					case 'attendance_percent':
						$groupid = $params['attendance_groupid'] == '__cong__' ? 0 : $params['attendance_groupid'];
						$min_date = date('Y-m-d', strtotime('-'.(int)$params['attendance_weeks'].' weeks'));
						$query['select'][] = '(SELECT ROUND(SUM(present)/COUNT(*)*100)
												FROM attendance_record ar
												WHERE date >= '.$GLOBALS['db']->quote($min_date).'
												AND groupid = '.(int)$groupid.'
												AND personid = p.id) AS `Attendance`';
						$query['from'] .= ' LEFT JOIN planned_absence pa
												ON pa.personid = p.id
												AND pa.start_date <= NOW()
												AND pa.end_date >= '.$GLOBALS['db']->quote($min_date).'
											';
						$query['select'][] = 'IFNULL(MAX(pa.id),0) as `_has_planned_absence`';

						break;
					case 'attendance_numabsences':
						/* The number of "absents" recorded since the last "present".*/
						$groupid = $params['attendance_groupid'] == '__cong__' ? 0 : $params['attendance_groupid'];
						$query['select'][] = '(SELECT COUNT(*)
												FROM attendance_record ar
												WHERE  groupid = '.(int)$groupid.'
												AND personid = p.id
												AND date > (SELECT COALESCE(MAX(date), "2000-01-01") FROM attendance_record ar2 WHERE ar2.personid = ar.personid AND present = 1 AND groupid='.(int)$groupid.')) AS `Running Absences`';
						break;
					case 'actionnotes.subjects':
						$query['select'][] = '(SELECT GROUP_CONCAT(CONCAT(subject, " [", substr(asn.first_name, 1, 1), substr(asn.last_name, 1, 1),"]") SEPARATOR "\n")
												FROM abstract_note an
												LEFT JOIN person_note pn ON an.id = pn.id
												LEFT JOIN family_note fn ON an.id = fn.id
												LEFT JOIN person asn on asn.id = an.assignee
												WHERE ((pn.personid = p.id) OR (fn.familyid = p.familyid))
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
						$customFieldID = NULL;
						if (substr($field, 0, 7) == 'date---') {
							// backwards compat
							$customFieldID = substr($field, 7);
						} else if (0 === strpos($field, self::CUSTOMFIELD_PREFIX)) {
							$customFieldID = substr($field, 14);
						}
						if ($customFieldID) {
							if (isset($this->_custom_fields[$customFieldID])) {
								$field = new Custom_Field();
								$field->populate($customFieldID, $this->_custom_fields[$customFieldID]);
								$query['from'] .= ' LEFT JOIN custom_field_value cfv'.$customFieldID.' ON cfv'.$customFieldID.'.personid = p.id AND cfv'.$customFieldID.'.fieldid = '.$db->quote($customFieldID)."\n";
								$query['from'] .= ' LEFT JOIN custom_field cf'.$customFieldID.' ON cfv'.$customFieldID.'.fieldid = cf'.$customFieldID.'.id '."\n";

								$query['select'][] = 'GROUP_CONCAT(DISTINCT '.Custom_Field::getRawValueSQLExpr('cfv'.$customFieldID, 'cf'.$customFieldID).' ORDER BY '.Custom_Field::getRawValueSQLExpr('cfv'.$customFieldID, 'cf'.$customFieldID).' SEPARATOR "'.self::CUSTOMFIELDVAL_SEP.'") as '.$db->quote(self::CUSTOMFIELD_PREFIX.$customFieldID)."\n";
							}
						} else {
							$query['select'][] = $this->_quoteAliasAndColumn($field).' AS '.$db->quote($field);
						}
				}
			}
			$select_fields = $grouping_field.'p.id as ID, '.implode(', ', $query['select']);
		}

		// ORDER BY
		$query['from'] .= '
			JOIN age_bracket absort ON absort.id = p.age_bracketid ';
		if ($custom_select_fields) {
			// Make sure the ORDER BY isn't relying on some fancy column from the original query (Issue #592)
			$query['order_by'] = '1';
		} else {
			$customOrder = NULL;
			if (substr($params['sort_by'], 0, 7) == 'date---') {
				// backwards compatibility
				$customOrder = substr($params['sort_by'], 8);
			} else if (0 === strpos($params['sort_by'], self::CUSTOMFIELD_PREFIX)) {
				$customOrder = substr($params['sort_by'], 14);
			}
			if ($customOrder) {
				$query['from'] .= ' LEFT JOIN custom_field_value cfvorder ON cfvorder.personid = p.id AND cfvorder.fieldid = '.$db->quote($customOrder)."\n";
				$query['from'] .= " LEFT JOIN custom_field_option cfoorder ON cfoorder.id = cfvorder.value_optionid \n";
				$order = Array();
				$order[] = 'IF(cfvorder.personid IS NULL, 1, 0)'; // put those without a value last
				if ($this->_custom_fields[$customOrder]['type'] == 'date') {
					$order[] = 'IF(cfvorder.value_date LIKE "-%", 1, 0)'; // put full dates before partial dates
				}
				$order[] = 'GROUP_CONCAT('.Custom_Field::getSortValueSQLExpr('cfvorder', 'cfoorder').')';
				$query['order_by'] = implode(', ', $order);
			} else if ($params['sort_by'] == 'p.congregationid') {
				// Order by congregation meeting time then congregation name
				$query['from'] .= '
					LEFT JOIN congregation cord ON p.congregationid = cord.id ';
				$query['order_by'] = 'IF(cord.id IS NULL, 1, 0), IF(LENGTH(cord.meeting_time)>0, 0, 1), cord.meeting_time, cord.name';
			} else if ($params['sort_by'] == 'p.age_bracketid') {
				$query['order_by'] = 'absort.`rank`';
			} else if ($params['sort_by'] == 'p.status') {
				$query['from'] .= '
					JOIN person_status pssort ON pssort.id = p.status ';
				$query['order_by'] = 'pssort.`rank`';
			} else {
				$query['order_by'] = $this->_quoteAliasAndColumn($params['sort_by']);
			}

			if ($grouping_order) {
				$query['order_by'] = $grouping_order.$query['order_by'];
			}

			if ($params['sort_by'] == 'f.family_name') {
				// Stop members of identically-named families from being intermingled
				// and make sure kids follow adults even if their last names are earlier
				$query['order_by'] .= ', f.id,  absort.`rank`, IF (absort.is_adult, gender, 1) DESC';
			}

			/*
			 * We can order by attendances or absences safely,
			 * because we have already ensured they will appear
			 * the select clause.
			 */
			$rewrites = Array(
						'`attendance_percent`' => '`Attendance` ASC',
						'`attendance_numabsences`' => '`Running Absences` DESC',
						'`membershipstatus`' => 'pgms.`rank`',
			);
			$query['order_by'] = str_replace(array_keys($rewrites), array_values($rewrites), $query['order_by']);
			if (!strlen(trim($query['order_by'], '`'))) $query['order_by'] = 1;
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
		$sql .= "\nGROUP BY ".implode(', ', $query['group_by']);
		$sql .= "\nORDER BY ".$query['order_by'].', p.last_name, p.familyid, absort.`rank`, IF (absort.is_adult, p.gender, 1) DESC, p.first_name';

		return $sql;
	}


	function getResultCount()
	{
		$db =& $GLOBALS['db'];
		$sql = $this->getSQL();
		if (is_null($sql)) return 0;
		$res = $db->query($sql);
		$result = $res->numRows();
		return $result;
	}


	function getResultPersonIDs()
	{
		$db =& $GLOBALS['db'];
		$sql = $this->getSQL('p.id');
		if (is_null($sql)) return Array();
		$res = $db->queryCol($sql);
		return $res;
	}


	function printResults($format='html')
	{
		$db =& $GLOBALS['db'];
		$params = $this->_convertParams($this->getValue('params'));

		$sql = $this->getSQL();
		if (is_null($sql)) return;

		if ($format == 'html' && in_array('checkbox', $params['show_fields'])) {
			echo '<form method="post" enctype="multipart/form-data" class="bulk-person-action">';
		}

		$data = array();

		if (!empty($_REQUEST['debug'])) {
			bam($params);
			bam($sql);
		}


		if (!$this->hasGroupingField($params)) {
			$res = $db->queryAll($sql, null, null, true, true);
			if (array_get($_REQUEST, 'debug') > 1) bam($res);
			if ($format == 'array') {
				$data = $this->_printResultSet($res, $format);
			} else {
				$this->_printResultSet($res, $format);
			}
		} else {
			$res = $db->queryAll($sql, null, null, true, false, true);
			if (array_get($_REQUEST, 'debug') > 1) bam($res);
			if ($format == 'array') {
				$data = $this->_printResultGroups($res, $params, $format);
			} else {
				$this->_printResultGroups($res, $params, $format);
			}
		}

		if ($res && ($format == 'html') && in_array('checkbox', $params['show_fields'])) {
			echo '<div class="no-print">';
			include 'templates/bulk_actions.template.php';
			echo '</div>';
			echo '</form>';
		}

		if ($format == 'array') {
			return $data;
		} 
	}

	function _printResultGroups($res, $params, $format)
	{
		if ($format == 'array') {
			$data = array();
		}
		foreach ($res as $i => $v) {
			if (0 === strpos($params['group_by'], 'custom-')) {
				$gb_bits = explode('-', $params['group_by']);
				$field = $GLOBALS['system']->getDBObject('custom_field', end($gb_bits));
				$heading = $field->formatValue($i);
				if (!strlen($heading)) $heading = '(Blank)';
			} else if ($params['group_by'] == 'groupid') {
				$heading = $i;
			} else {
				$var = $params['group_by'][0] == 'p' ? '_dummy_person' : '_dummy_family';
				$fieldname = substr($params['group_by'], 2);
				$this->$var->setValue($fieldname, $i);
				$heading = $this->$var->getFormattedValue($fieldname);
			}
			// We need to get the person ID as the array keys before calling _printResultSet
			$set = Array();
			foreach ($v as $vv) {
				$set[$vv['ID']] = $vv;
				unset($set[$vv['ID']]['ID']);
			}
			if ($format == 'array') {
				$data[] = $this->_printResultSet($set, $format, $heading);
			} else {
				$this->_printResultSet($set, $format, $heading);
			}
		}
		if ($format == 'array') {
			return $data;
		}
	}


	/*
	 * @param $dataset	Results keyed by personID
	 * @param $format	csv, array or html
	 * @param $heading
	 */
	function _printResultSet($dataset, $format, $heading=NULL)
	{
		if ($format == 'csv') {
			$this->_printResultSetCsv($dataset, $heading);
		} elseif ($format == 'array') {
			return $this->_printResultSetArray($dataset, $heading);
		} else {
			$this->_printResultSetHtml($dataset, $heading);
		}
	}

	function _printResultSetCsv($x, $groupingname)
	{
		$fp = fopen('php://output', 'w');
		if (empty($x)) return;
		static $headerprinted = false;
		if (!$headerprinted) {
			$hr = Array();
			$headers = array_keys(reset($x));
			if (reset($headers) == 'ID') {
				// https://superuser.com/questions/210027/why-does-excel-think-csv-files-are-sylk
				fputs($fp, '"ID",');
				array_shift($headers);
			}

			foreach ($headers as $heading) {
				if (in_array($heading, Array('view_link', 'edit_link', 'note_link', 'checkbox'))) continue;
				switch($heading) {
					case 'person_groups':
						$hr[] = 'Groups';
						break;
					case 'notes.subjects':
					case 'actionnotes.subjects':
						$hr[] = 'Notes';
						break;
					default:
						if (isset($this->_field_details[$heading])) {
							$hr[] = $this->_field_details[$heading]['label'];
						} else if (0 === strpos($heading, self::CUSTOMFIELD_PREFIX)) {
							$hr[] = $this->_custom_fields[substr($heading, strlen(self::CUSTOMFIELD_PREFIX))]['name'];
						} else {
							$hr[] = ucfirst($heading);
						}
				}
			}
			if ($groupingname) $hr[] = 'GROUPING';

			fputcsv($fp, $hr);
			$headerprinted = TRUE;
		}
		foreach ($x as $row) {
			$r = Array();
			foreach ($row as $label => $val) {
				if (in_array($label, Array('view_link', 'edit_link', 'note_link', 'checkbox'))) continue;
				if (isset($this->_field_details[$label])) {
					$var = $label[0] == 'p' ? '_dummy_person' : '_dummy_family';
					$fieldname = substr($label, 2);
					if ($fieldname == 'id') {
							$r[] = $val;
					} else {
							$r[] = $this->$var->getFormattedValue($fieldname, $val);
					}
				} else if (0 === strpos($label, self::CUSTOMFIELD_PREFIX)) {
					$r[] = $this->_formatCustomFieldValue($val, substr($label, strlen(self::CUSTOMFIELD_PREFIX)));
				} else {
					$r[] = $val;
				}
			}
			if ($groupingname) $r[] = str_replace('"', '""', $groupingname);
			fputcsv($fp, $r);
		}
		fclose($fp);
	}

	function _printResultSetArray($x, $groupingname)
	{
		if (empty($x)) return;
//		return $x;
		$data = array();
		$hr = Array();
		$headers = array_keys(reset($x));
		$personid_found = false;
		$i = 0;
		foreach ($headers as $heading) {
			if (in_array($heading, Array('view_link', 'edit_link', 'checkbox'))) continue;
			switch($heading) {
				case 'person_groups':
					$i++;
					$hr[$i] = 'Groups';
					break;
				case 'notes.subjects':
				case 'actionnotes.subjects':
					$i++;
					$hr[$i] = 'Notes';
					break;
				default:
					$i++;
					if (isset($this->_field_details[$heading])) {
						$hr[$i] = $this->_field_details[$heading]['label'];
					} else if (0 === strpos($heading, self::CUSTOMFIELD_PREFIX)) {
						$hr[$i] = $this->_custom_fields[substr($heading, strlen(self::CUSTOMFIELD_PREFIX))]['name'];
					} else {
						$hr[$i] = ucfirst($heading);
					}
					if ($hr[$i] == 'Person ID') {
						$personid_found = true;
					}
			}
		}
		if ($groupingname) {
			$i++;
			$hr[$i] = 'GROUPING';
		}

		foreach ($x as $id => $row) {
			$r = Array();
			$i = 0;
			foreach ($row as $label => $val) {
				if (in_array($label, Array('view_link', 'edit_link', 'checkbox'))) continue;
				if (isset($this->_field_details[$label])) {
					$var = $label[0] == 'p' ? '_dummy_person' : '_dummy_family';
					$fieldname = substr($label, 2);
					if ($fieldname == 'id') {
							$i++;
							$r[$hr[$i]] = $val;
					} else {
							$i++;
							$r[$hr[$i]] = $this->$var->getFormattedValue($fieldname, $val);
					}
				} else if (0 === strpos($label, self::CUSTOMFIELD_PREFIX)) {
					$i++;
					$r[$hr[$i]] = $this->_formatCustomFieldValue($val, substr($label, strlen(self::CUSTOMFIELD_PREFIX)));
				} else {
					$i++;
					$r[$hr[$i]] = $val;
				}
			}
			if ($groupingname) {
				$i++;
				$r[$hr[$i]] = str_replace('"', '""', $groupingname);
			}
			if (! $personid_found) {
				$r['Person ID'] = $id;
			}
			$data[] = $r;
		}

		return $data;
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
		$headers = array_keys(reset($x));
		?>
		<table class="table table-striped table-condensed table-hover table-min-width clickable-rows query-results">
			<thead>
				<tr>
				<?php
				foreach ($headers as $heading) {
					if ($heading[0] == '_') continue;
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
							case 'note_link':
								break;
							case 'checkbox':
								echo '<input type="checkbox" class="select-all" title="Select all" />';
								break;
							default:
								if (isset($this->_field_details[$heading])) {
									echo $this->_field_details[$heading]['label'];
								} else if (0 === strpos($heading, self::CUSTOMFIELD_PREFIX)) {
									echo ents($this->_custom_fields[substr($heading, strlen(self::CUSTOMFIELD_PREFIX))]['name']);
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
			foreach ($x as $personid => $row) {
				?>
				<tr data-personid="<?php echo $personid; ?>">
				<?php
				foreach ($row as $label => $val) {
					if ($label[0] == '_') continue;
					?>
					<td <?php echo $this->_getColClasses($label); ?>>
						<?php
						switch ($label) {
							case 'edit_link':
								?>
								<a class="med-popup no-print" href="?view=_edit_person&personid=<?php echo $row[$label]; ?>&then=refresh_opener"><i class="icon-wrench"></i>Edit</a>
								<?php
								break;
							case 'view_link':
								?>
								<a class="med-popup no-print" href="?view=persons&personid=<?php echo $row[$label]; ?>"><i class="icon-user"></i>View</a>
								<?php
								break;
							case 'note_link':
								// if notes are shown on this report, we want to refresh after adding a new one
								$then = in_array('Notes', $headers) ? '&then=refresh_opener' : '';
								if ($GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
									?>
									<a class="med-popup no-print" href="?view=_add_note_to_person&personid=<?php echo $row[$label].$then ?>"><i class="icon-pencil"></i>Add&nbsp;Note</a>
									<?php
								}
								break;
							case 'checkbox':
								?>
								<input name="personid[]" type="checkbox" value="<?php echo $row[$label]; ?>" class="no-print" />
								<?php
								break;
							case 'photo':
								?>
								<a class="med-popup" href="?view=persons&personid=<?php echo $row[$label]; ?>">
								<img height="60" src="?call=photo&personid=<?php echo $row[$label]; ?>" />
								</a>
								<?php
								break;
							case 'Attendance':
								if ($row['_has_planned_absence']) {
									echo '<span class="nowrap" title="Includes planned absence(s) in this period">';
								}
								echo $val.'%';
								if ($row['_has_planned_absence']) {
									echo '&nbsp;<b>✱</b></span>';
								}
								break;
							case 'p.id':
							case 'f.id':
								echo $val;
								break;
							case 'Notes':
								$val = ents($val);
								$val = preg_replace('/(\[[A-Z][A-Z]\])/', "<small class=\"soft\">$1</small>", $val);
								echo nl2br($val);
								break;
							default:
								if (isset($this->_field_details[$label])) {
									$this->_dummy_person->id = $personid;
									$var = $label[0] == 'p' ? '_dummy_person' : '_dummy_family';
									$fieldname = substr($label, 2);
									$this->$var->setValue($fieldname, $val);
									$this->$var->printFieldValue($fieldname);
								} else if (0 === strpos($label, self::CUSTOMFIELD_PREFIX)) {
									$this->_printCustomFieldValue($val, substr($label, strlen(self::CUSTOMFIELD_PREFIX)));
								} else {
									echo nl2br(ents($val));
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
			<tfoot>
				<tr>
					<td class="report-summary no-tsv" colspan="<?php echo count($headers); ?>">
						<span class="pull-right no-print">
							Copy: 
							<span class="clickable" title="plain table to paste elsewhere" data-action="copy-table">Table</span> 
							<span class="clickable" title="tab-separated for spreadsheet" data-action="copy-tsv">TSV</span>
						</span>
						<i><?php echo count($x); ?> persons listed</i>
					</td>
				</tr>
			</tfoot>
		</table>
		<?php
	}


	function validateFields()
	{
		if (!parent::validateFields()) return FALSE;

		return TRUE;
	}


	private function canSave($throwErrors=FALSE)
	{
		if (!($this->getValue('owner'))
			&& (!$GLOBALS['user_system']->havePerm(PERM_MANAGEREPORTS))
		) {
			if ($throwErrors) trigger_error('You do not have permission to save shared reports', E_USER_ERROR);
			return FALSE;
		} else if (($this->getValue('owner')) && ($this->getValue('owner') != $GLOBALS['user_system']->getCurrentUser('id'))
		) {
			if ($throwErrors) trigger_error('Cannot save report that belongs to another user!', E_USER_ERROR);
			return FALSE;
		} else {
			return TRUE;
		}
	}


	function save()
	{
		if ($this->id == 'TEMP') {
			$_SESSION['saved_query'] = serialize($this);
			return TRUE;
		} else if (!$this->canSave(TRUE)) {
			exit;
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
			$res = parent::load($id);
			$user = $GLOBALS['user_system']->getCurrentUser('id');
			$owner = $this->getValue('owner');
			if (!empty($user) && !empty($owner) && ($owner != $user)) {
				// Somebody trying to open a report that belongs to another
				$this->reset();
				return FALSE;
			}
			return $res;
		}
	}

	function _getColClasses($heading)
	{
		$class_list = Array();
		switch ($heading) {
			case 'checkbox':
				$class_list[] = 'selector';
				// fallthrough
			case 'edit_link':
			case 'view_link':
			case 'note_link':
				$class_list[] = 'no-print';
				$class_list[] = 'narrow';
				break;
			case 'p.age_bracketid':
			case 'p.congregationid':
			case 'p.status':
			case 'p.first_name':
			case 'p.last_name':
			case 'f.family_name':
				$class_list[] = 'nowrap';
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

	/**
	 * Convert an older version of the params to new format
	 * and clean up any stupidities
	 */
	private function _convertParams($params)
	{
		if (empty($params)) return Array();
		if (!empty($params['dates'])) {
			foreach ($params['dates'] as $rule) {
				$params['custom_fields'][$rule['typeid']] = $rule;
				unset($params['custom_fields'][$rule['typeid']]['typeid']);
			}
			unset($params['dates']);
		}
		if (!empty($params['date_logic'])) {
			$params['custom_field_logic'] = $params['date_logic'];
			unset($params['date_logic']);
		}
		foreach (array_get($params, 'show_fields', Array()) as $i => $v) {
			if (0 === strpos($v, 'date---')) {
				$params['show_fields'][$i] = self::CUSTOMFIELD_PREFIX.substr($v, strlen('date---'));
			}
			if ($v == 'p.age_bracket') {
				$params['show_fields'][$i] = 'p.age_bracketid';
			}
		}

		if (0 === strpos($params['sort_by'], 'date---')) {
			$params['sort_by'] = self::CUSTOMFIELD_PREFIX.substr($params['sort_by'], strlen('date---'));
		}
		if ($params['sort_by'] == 'p.age_bracket') {
			$params['sort_by'] = 'p.age_bracketid';
		}

		if (
			($params['group_by'] == 'groupid')
			&& !count(array_remove_empties($params['include_groups']))
		) {
			$params['group_by'] = '';
		}
		if ($params['group_by'] == 'p.age_bracket') {
			$params['group_by'] = 'p.age_bracketid';
		}
		if (!isset($params['rules'])) $params['rules'] = Array();

		// Convert the old 'periodtype=relative/fixed' to new format.
		if (!empty($params['custom_fields'])) {
			foreach ($params['custom_fields'] as $k => &$rule) {
				if (array_get($rule, 'periodtype') == 'relative') {
					$days = $rule['periodlength'];
					$months = 0;
					$years = floor($days / 365); // never mind leap years
					$days = $days % 365;
					switch ($rule['periodanchor']) {
						case 'ending':
							$rule['to'] = '-0y0m0d';
							$rule['from'] = '-'.$years.'y0m'.$days.'d';
							break;
						case 'before':
							$rule['to'] = '-0y0m1d';
							$rule['from'] = '-'.$years.'y0m'.($days+1).'d';
							break;
						case 'starting':
							$rule['from'] = '+0y0m0d';
							$rule['to'] = '+'.$years.'y0m'.($days).'d';
							break;
						case 'after':
							$rule['from'] = '+0y0m1d';
							$rule['to'] = '+'.$years.'y0m'.($days+1).'d';
							break;
					}
				} else if (array_get($rule, 'periodtype') == 'fixed') {
					// make open-ended values explicit.
					if (empty($rule['from'])) $rule['from'] = '*';
					if (empty($rule['from'])) $rule['to'] = '*';
				}

				unset($rule['periodtype']); // Now captured with special values for 'from' and 'to'.
				unset($rule['periodlength']); 
				unset($rule['periodanchor']); 
			}
		}

		return $params;
	}

	/** Whether the person query has a *valid* group-by clause, i.e. if it's grouping by a custom field, check that the cf exists. */
	private function hasGroupingField(array $params): bool
	{
		$grouping_field = $params['group_by'];
		if (empty($grouping_field)) return false;
		if ($grouping_field) {
			if (0 === strpos($grouping_field, 'custom-')) {
				$gb_bits = explode('-', $params['group_by']);
				$field = $GLOBALS['system']->getDBObject('custom_field', end($gb_bits));
				if (empty($field)) return false;
			}
		}
		return true;
	}
}
