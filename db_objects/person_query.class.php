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
		'p.creator', 'p.created', 'f.created', 'p.status_last_changed', );
	private $_dummy_family = NULL;
	private $_dummy_person = NULL;
	private $_dummy_custom_field = NULL;
	private $_group_chooser_options_cache = NULL;

	private $_custom_fields = Array();

	const CUSTOMFIELDVAL_SEP = '__next__';
	const CUSTOMFIELD_PREFIX = 'CUSTOMFIELD---';

	function __construct($id=0)
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
													'empty' => 'not filled in',
													'exact' => 'with exact value within...',
													'anniversary' => 'with exact value or anniversary within...',
												),
												'attrs' => Array(
													'data-toggle' => 'visible',
													'data-target' => 'row .datefield-rule-period',
													'data-match-attr' => 'data-select-rule-type'
												),
											);
									print_widget('params_custom_field_'.$fieldid.'_criteria', $cparams, array_get($value, 'criteria'));
									$pts = Array('fixed' => '', 'relative' => '');
									$pts[array_get($value, 'periodtype', 'fixed')] = 'checked="checked"';
									?>
									<div class="datefield-rule-period" data-select-rule-type="exact anniversary">
										<label class="checkbox nowrap">
											<input type="radio" name="params_custom_field_<?php echo $fieldid; ?>_periodtype" value="fixed" <?php echo $pts['fixed']; ?> />
											the period from
											<?php print_widget('params_custom_field_'.$fieldid.'_from', Array('type' => 'date', 'allow_empty' => TRUE), array_get($value, 'from')); ?>
											to
											<?php print_widget('params_custom_field_'.$fieldid.'_to', Array('type' => 'date', 'allow_empty' => TRUE), array_get($value, 'to')); ?>
										</label>
										<label class="checkbox">
											<input type="radio" name="params_custom_field_<?php echo $fieldid; ?>_periodtype" value="relative"<?php echo $pts['relative']; ?> />
											the
											<?php print_widget('params_custom_field_'.$fieldid.'_periodlength', Array('type' => 'int'), array_get($value, 'periodlength', 14)); ?>
											day period
											<?php print_widget('params_custom_field_'.$fieldid.'_periodanchor',
													Array(
														'type' => 'select',
														'options' => Array(
																		'before' => 'before',
																		'ending' => 'ending on',
																		'starting' => 'starting on',
																		'after' => 'after',
																	)
													),
													array_get($value, 'periodanchor', 'ending')
											); ?>
											the day the report is executed
										</label>

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

		<h4>who <strong>are</strong> in one or more of these groups:</h4>
		<div class="indent-left">

			<?php
			$gotGroups = Person_Group::printMultiChooser('include_groupids', array_get($params, 'include_groups', Array()), Array(), TRUE);

			if ($gotGroups) {
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
				<?php
			}
			?>
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

			<br />over the last <input name="attendance_weeks" type="number" size="2" class="attendance-input" value="<?php echo (int)array_get($params, 'attendance_weeks', 2); ?>" /> weeks
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
						$options['notes.subjects'] = 'Notes matching the phrase above';
						$options['actionnotes.subjects'] = 'Notes requiring action';
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
		<?php
		if ($GLOBALS['user_system']->havePerm(PERM_MANAGEREPORTS)) {
			$visibilityParams = Array(
				'type' => 'select',
				'options' => Array('visible to everyone', 'visible only to me')
			);
			?>
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
			if ($this->id != 0) {
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
							print_widget('is_private', $visibilityParams, $this->getValue('owner') !== NULL);
							?>
						</td>
					</tr>
					<tr>
						<td></td>
						<td>Show on home page?
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
	}

	function processForm($prefix='', $fields=NULL)
	{
		if ($GLOBALS['user_system']->havePerm('PERM_MANAGEREPORTS')) {
			switch ($_POST['save_option']) {
				case 'new':
					$this->populate(0, Array());
					$this->processFieldInterface('name');
					if ($GLOBALS['user_system']->havePerm(PERM_SYSADMIN)) {
						$this->processFieldInterface('mailchimp_list_id');
					}
					$this->setValue('owner', $_POST['is_private'] ? $GLOBALS['user_system']->getCurrentUser('id') : NULL);
					$this->processFieldInterface('show_on_homepage');
					break;
				case 'replace':
					$this->processFieldInterface('name');
					if ($GLOBALS['user_system']->havePerm(PERM_SYSADMIN)) {
						$this->processFieldInterface('mailchimp_list_id');
					}
					$this->setValue('owner', $_POST['is_private'] ? $GLOBALS['user_system']->getCurrentUser('id') : NULL);
					$this->processFieldInterface('show_on_homepage');
					break;
				case 'temp':
					$this->id = 'TEMP';
				break;
			}
		} else {
			$this->id = 'TEMP';
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
							'periodtype' => $_REQUEST['params_custom_field_'.$fieldid.'_periodtype'],
							'periodlength' => $_REQUEST['params_custom_field_'.$fieldid.'_periodlength'],
							'periodanchor' => $_REQUEST['params_custom_field_'.$fieldid.'_periodanchor'],
							'from' => process_widget('params_custom_field_'.$fieldid.'_from', Array('type' => 'date')),
							'to' => process_widget('params_custom_field_'.$fieldid.'_to', Array('type' => 'date')),
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
		$params['group_join_date_from'] = empty($_POST['enable_group_join_date']) ? NULL : process_widget('group_join_date_from', Array('type' => 'date'));
		$params['group_join_date_to'] = empty($_POST['enable_group_join_date']) ? NULL : process_widget('group_join_date_to', Array('type' => 'date'));
		$params['exclude_groups'] = array_remove_empties(array_get($_POST, 'exclude_groupids', Array()));
		$params['group_membership_status'] = array_get($_POST, 'group_membership_status');

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


	function getSQL($select_fields=NULL)
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

							if (array_get($values, 'periodtype') == 'relative') {
								$length = $values['periodlength'];
								if (!preg_match('/^[0-9]+$/', $length)) $length = 0;
								$offsets = Array(
									'before' => Array(-$length-1, -1),
									'ending' => Array(-$length, 0),
									'starting' => Array(0, $length),
									'after' => Array(1, $length+1)
								);
								list($so, $eo) = $offsets[$values['periodanchor']];
								if ($so > 0) $so = "+$so";
								if ($eo > 0) $eo = "+$eo";
								$from = date('Y-m-d', strtotime("{$so} days"));
								$to = date('Y-m-d', strtotime("{$eo} days"));
							} else {
								$from = $values['from'];
								$to = $values['to'];
							}
							$betweenExp = 'BETWEEN '.$db->quote($from).' AND '.$db->quote($to);
							$valExp = 'pd'.$fieldid.'.value_date';
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
							$ids = implode(',', array_map(Array($db, 'quote'), $values['val']));
							$xrule = '(pd'.$fieldid.'.value_optionid IN ('.$ids.'))';
							if (in_array(0, $values['val'])) {
								// 'other' option
								$xrule = '('.$xrule.' OR (pd'.$fieldid.'.value_text IS NOT NULL))';
							}
							$customFieldWheres[] = $xrule;
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
		$grouping_order = '';
		$grouping_field = '';
		if (empty($params['group_by'])) {
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
		} else {
			// by some core field
			$grouping_order = $grouping_field = $params['group_by'].', ';
		}

		// DISPLAY FIELDS
		$joined_groups = FALSE;
		if (empty($select_fields)) {
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
						if (empty($params['include_groups'])) continue;

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
								$query['select'][] = 'GROUP_CONCAT(pg.name ORDER BY pg.name SEPARATOR "\n") as person_groups';
							} else if ($field == 'membershipstatus') {
								$query['from'] .= ' LEFT JOIN person_group_membership_status pgms ON pgms.id = pgm.membership_status';
								$query['select'][] = 'GROUP_CONCAT(pgms.label ORDER BY pg.name SEPARATOR "\n") as `Membership Status`';
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
						$query['from'] .= '
										JOIN (
											SELECT familyid, IF (
												GROUP_CONCAT(DISTINCT last_name) = ff.family_name,
												GROUP_CONCAT(first_name ORDER BY ab.rank, gender DESC SEPARATOR ", "),
												GROUP_CONCAT(CONCAT(first_name, " ", last_name) ORDER BY ab.rank, gender DESC SEPARATOR ", ")
											  ) AS `names`
											FROM person pp
											JOIN age_bracket ab ON ab.id = pp.age_bracketid
											JOIN family ff ON pp.familyid = ff.id
											WHERE pp.status <> "archived"
											GROUP BY familyid
										) all_members ON all_members.familyid = p.familyid
										   ';
						$query['select'][] = 'all_members.names as `All Family Members`';
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
												GROUP_CONCAT(first_name ORDER BY ab.rank, gender DESC SEPARATOR ", "),
												GROUP_CONCAT(CONCAT(first_name, " ", last_name) ORDER BY ab.rank, gender DESC SEPARATOR ", ")
											  )
											FROM person pp
											JOIN age_bracket ab ON pp.age_bracketid = ab.id
											JOIN family ff ON pp.familyid = ff.id
											WHERE pp.status <> "archived" AND ab.is_adult
											GROUP BY familyid');
						$query['from'] .= ' LEFT JOIN _family_adults'.$this->id.' ON _family_adults'.$this->id.'.familyid = p.familyid
											';
						$query['select'][] = '_family_adults'.$this->id.'.names as `Adult Family Members`';
						break;
					case 'attendance_percent':
							$groupid = $params['attendance_groupid'] == '__cong__' ? 0 : $params['attendance_groupid'];
							$min_date = date('Y-m-d', strtotime('-'.(int)$params['attendance_weeks'].' weeks'));
							$query['select'][] = '(SELECT ROUND(SUM(present)/COUNT(*)*100)
													FROM attendance_record
													WHERE date >= '.$GLOBALS['db']->quote($min_date).'
													AND groupid = '.(int)$groupid.'
													AND personid = p.id) AS `Attendance`';
						break;
					case 'attendance_numabsences':
						/* The number of "absents" recorded since the last "present".*/
							$groupid = $params['attendance_groupid'] == '__cong__' ? 0 : $params['attendance_groupid'];
							$query['select'][] = '(SELECT COUNT(*)
													FROM attendance_record ar
													WHERE groupid = '.(int)$groupid.'
													AND personid = p.id
													AND date > (SELECT COALESCE(MAX(date), "2000-01-01") FROM attendance_record ar2 WHERE ar2.personid = ar.personid AND present = 1)) AS `Running Absences`';
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
		$customOrder = NULL;
		if (substr($params['sort_by'], 0, 7) == 'date---') {
			// backwards compatibility
			$customOrder = substr($params['sort_by'], 8);
		} else if (0 === strpos($params['sort_by'], self::CUSTOMFIELD_PREFIX)) {
			$customOrder = substr($params['sort_by'], 14);
		}
		$query['from'] .= '
			JOIN age_bracket absort ON absort.id = p.age_bracketid ';
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
			$query['order_by'] = 'absort.rank';
		} else {
			$query['order_by'] = $this->_quoteAliasAndColumn($params['sort_by']);
		}

		if ($grouping_order) {
			$query['order_by'] = $grouping_order.$query['order_by'];
		}

		if ($params['sort_by'] == 'f.family_name') {
			// Stop members of identically-named families from being intermingled
			$query['order_by'] .= ', f.id';
		}

		/*
		 * We can order by attendances or absences safely,
		 * because we have already ensured they will appear
		 * the select clause.
		 */
		$rewrites = Array(
					'`attendance_percent`' => '`Attendance` ASC',
					'`attendance_numabsences`' => '`Running Absences` DESC',
					'`membershipstatus`' => 'pgms.rank',
		);
		$query['order_by'] = str_replace(array_keys($rewrites), array_values($rewrites), $query['order_by']);
		if (!strlen(trim($query['order_by'], '`'))) $query['order_by'] = 1;

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
		$sql .= "\nORDER BY ".$query['order_by'].', p.last_name, p.familyid, absort.rank, IF (absort.is_adult, p.gender, 1) DESC, p.first_name';

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

		$grouping_field = $params['group_by'];
		if (empty($grouping_field)) {
			$res = $db->queryAll($sql, null, null, true, true);
			$this->_printResultSet($res, $format);
		} else {
			$res = $db->queryAll($sql, null, null, true, false, true);
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
		$fp = fopen('php://output', 'w');
		if (empty($x)) return;
		static $headerprinted = false;
		if (!$headerprinted) {
			$hr = Array();
			foreach (array_keys(reset($x)) as $heading) {
				if (in_array($heading, Array('view_link', 'edit_link', 'checkbox'))) continue;
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
				if (in_array($label, Array('view_link', 'edit_link', 'checkbox'))) continue;
				if (isset($this->_field_details[$label])) {
					$var = $label[0] == 'p' ? '_dummy_person' : '_dummy_family';
					$fieldname = substr($label, 2);
					$r[] = $this->$var->getFormattedValue($fieldname, $val);
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
					?>
					<td <?php echo $this->_getColClasses($label); ?>>
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
								<img height="60" src="?call=photo&personid=<?php echo $row[$label]; ?>" />
								</a>
								<?php
								break;
							case 'Attendance':
								echo $val.'%';
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
		</table>
		<p class="report-summary"><?php echo count($x); ?> persons listed</p>
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
		$class_list = Array();
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

		return $params;
	}
}
?>
