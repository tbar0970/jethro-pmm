<?php
class View_Admin__System_Configuration extends View {

	public function getTitle() {
		return 'System configuration';
	}

	static function getMenuPermissionLevel()
	{
		return PERM_SYSADMIN;
	}

	public function processView()
	{
		$saved = FALSE;
		foreach (Config_Manager::getSettings() as $symbol => $details) {
			switch ($symbol) {
				case 'DEFAULT_PERMISSIONS':
					if (isset($_REQUEST['permissions'])) {
						$sm = new Staff_Member();
						$sm->processFieldInterface('permissions');
						Config_Manager::saveSetting('DEFAULT_PERMISSIONS', $sm->getValue('permissions'));
						$saved = TRUE;
					}
					break;
				case 'GROUP_MEMBERSHIP_STATUS_OPTIONS':
					$this->processGroupMembershipStatusOptions();
					break;
				case 'AGE_BRACKET_OPTIONS':
					$this->processAgeBracketOptions();
					break;
				case 'PERSON_STATUS_OPTIONS':
					$this->processPersonStatusOptions();
					break;
				case '2FA_REQUIRED_PERMS':
					$this->process2FARequiredPermsField();
					break;
				default:
					if (isset($_REQUEST[$symbol])) {
						list($params, $value, $multi) = self::getParamsAndValue($symbol, $details);
						$val = process_widget($symbol, $params); //process_widget will handle multis
						$val = $this->prepareValueForSave($val, $details);
						Config_Manager::saveSetting($symbol, $val);
						$saved = TRUE;
					}
			}
		}
		if ($saved) {
			add_message("Configuration saved");
			redirect($_REQUEST['view']);
		}
	}

	private function _doConfigChecks()
	{
		if ((ifdef('2FA_REQUIRED_PERMS') > 0) && SMS_Sender::usesUserMobile() && strlen(ifdef('2FA_SENDER_ID')) == 0) {
			print_message("2-Factor authentication will not work until you set the 2FA_SENDER_ID setting", 'error');
		}
	}

	public function printView()
	{
		if (JETHRO_VERSION == 'DEV') {
			if (!empty($_REQUEST['dump_sql'])) {
				$installer = new Installer();
				$installer->initDB(TRUE);
				return;
			} else {
				?>
				<a class="btn" href="<?php echo build_url(Array('dump_sql' => 1)) ?>">Show init SQL</a>
				<?php
			}
		}
		$this->_doConfigChecks();
		?>
		<form method="post">
			<div class="form-horizontal">
			<?php
			foreach (Config_Manager::getSettings() as $symbol => $details) {
				if ($details['type'] == 'hidden') continue;
				$details['note'] = str_replace('<system_url>', BASE_URL, $details['note']);
				if ($details['heading']) {
					echo '<hr /><h4>'.ents($details['heading']).'</h4>';
				}
				?>
				<div class="control-group" id="<?php echo $symbol; ?>">
					<label class="control-label" for="<?php echo $symbol; ?>">
						<?php
						echo ucwords(str_replace('_', ' ', strtolower($symbol)));
						?>
					</label>
					<div class="controls">
						<?php
						if (defined($symbol.'_IN_CONFIG_FILE')) {
							if (Config_Manager::allowSettingInFile($symbol) && constant($symbol)) {
								// Don't show the value here - sensitive
								print_message('This setting has been set in the system config file', 'warning');
							} else {
								$this->printValue($symbol, $details);
								print_message('This setting has been set in the system config file. To make it editable here, remove it from the config file.', 'warning');
							}
						} else {
							$this->printWidget($symbol, $details);
							if ($details['note']) echo '<p class="smallprint">'.ents($details['note']).'</p>';
						}
						?>
					</div>
				</div>
				<?php
			}
			?>
				<div class="control-group">
					<div class="controls">
						<input type="submit" value="Save" class="btn" />
					</div>
				</div>
			</div>
		</form>
		<?php
	}

	private static function getParamsAndValue($symbol, $details)
	{
		$params = Array();
		$type = $details['type'];
		$multi = FALSE;
		if ((($x = strpos($type, '[')) !== FALSE)
			|| (($x = strpos($type, '{')) !== FALSE)
		) {
			$params['options'] = json_decode(substr($type, $x), 1);
			if (strpos($type, '[') !== FALSE) {
				$params['options'] = array_combine($params['options'], $params['options']);
			}
			$type = substr($type, 0, $x);
		}
		$params['type'] = $type;
		$value = constant($symbol);
		switch ($type) {
			case 'text_ml':
				$params['type'] = 'text';
				$params['height'] = 4;
				break;
			case 'multiselect':
				$params['allow_multiple'] = TRUE;
				$params['type'] = 'select';
				$params['height'] = 0; // expand to fit options
				$value = explode(',', $value);
				break;
			case 'multitext_cm':
				$value = explode(',', $value);
				$multi = TRUE;
				$params['type'] = 'text';
				break;
			case 'multitext_nl':
				$value = explode("\n", $value);
				$multi = TRUE;
				$params['type'] = 'text';
				break;
			case 'bool':
				$params['type'] = 'select';
				$params['options'] = Array('No', 'Yes');
				$value = (int)$value;
				break;
			case 'text':
				$params['width'] = 60;
				break;
			case 'int':
			case 'select':
			case 'email':
			case 'hidden':
				break;
			default:
				trigger_error("Unknown setting type '$type'");
				return;
		}
		return Array($params, $value, $multi);
	}

	private function prepareValueForSave($value, $details)
	{
		$type = $details['type'];
		$multi = FALSE;
		if ((($x = strpos($type, '[')) !== FALSE)
			|| (($x = strpos($type, '{')) !== FALSE)
		) {
			$type = substr($type, 0, $x);
		}
		switch ($type) {
			case 'multiselect':
				$value = implode(',', $value);
				break;
			case 'multitext_cm':
				$value = implode(',', array_remove_empties($value));
				break;
			case 'multitext_nl':
				$value = implode("\n", array_remove_empties($value));
				break;
			case 'bool':
				$value = (int)$value;
				break;
		}
		return $value;
	}

	private function printValue($symbol, $details)
	{
		list($params, $value, $multi) = self::getParamsAndValue($symbol, $details);
		if ($multi || is_array($value)) {
			echo '<ul>';
			foreach ($value as $v) {
				echo '<li>'.format_value($v, $params).'</li>';
			}
			echo '</ul>';
		} else {
			echo format_value($value, $params);
		}
	}

	private function printWidget($symbol, $details)
	{
		switch ($symbol) {
			case 'DEFAULT_PERMISSIONS':
				$sm = new Staff_Member();
				$sm->setValue('permissions', DEFAULT_PERMISSIONS);
				$sm->printFieldInterface('permissions');
				break;
			case 'AGE_BRACKET_OPTIONS':
				$this->printAgeBracketOptions();
				break;
			case 'PERSON_STATUS_OPTIONS':
				$this->printPersonStatusOptions();
				break;
			case 'GROUP_MEMBERSHIP_STATUS_OPTIONS':
				$this->printGroupMembershipStatusOptions();
				break;
			case '2FA_REQUIRED_PERMS':
				$this->print2FARequiredPermsField();
				break;
			default:
				list($params, $value, $multi) = self::getParamsAndValue($symbol, $details);
				if ($multi) {
					if (count($value) == 0) $value = Array('');
					?>
					<table class="expandable">
					<?php
					foreach ($value as $v) {
						?>
						<tr><td><?php print_widget($symbol.'[]', $params, $v); ?></td></tr>
						<?php
					}
					?>
					</table>
					<?php
				} else {
					print_widget($symbol, $params, $value);
				}
				break;
		}
	}

	private function print2FARequiredPermsField()
	{
		if (!SMS_Sender::canSend()) {
			SMS_Sender::setConfigPrefix ('2FA_SMS');
			if (!SMS_Sender::canSend()) {
				print_message("2 Factor auth is only available once a SMS gateway has been configured. Contact your System Administrator to set this up.", 'warning');
				return;
			}
		}
		echo '<div style="columns: 2">';
		$selected_perms = explode(',', constant('2FA_REQUIRED_PERMS'));
		$levels = $GLOBALS['user_system']->getPermissionLevels();
		print_hidden_field('2FA_REQUIRED_PERMS_SUBMITTED', 1);
		foreach ($levels as $num => $desc) {
			$checked = in_array($num, $selected_perms) ? 'checked="checked"' : '';
			?>
			<label class="checkbox">
				<input type="checkbox" name="2FA_REQUIRED_PERMS[]" value="<?php echo (int)$num; ?>" <?php echo $checked; ?>>
				<?php echo ents($desc); ?>
			</label>
			<?php
		}
		echo '</div>';
	}

	private function process2FARequiredPermsField()
	{
		if (!empty($_REQUEST['2FA_REQUIRED_PERMS_SUBMITTED'])) {
			$levels = $GLOBALS['user_system']->getPermissionLevels();
			$res = Array();
			foreach (array_get($_REQUEST, '2FA_REQUIRED_PERMS', Array()) as $perm) {
				if (isset($levels[$perm])) $res[] = $perm;
			}
			Config_Manager::saveSetting('2FA_REQUIRED_PERMS', implode(',', $res));
		}
	}

	private function printGroupMembershipStatusOptions()
	{
		?>
		<input type="hidden" name="group_membership_statuses_submitted" value="1" />
		<table class="table table-condensed expandable table-bordered table-auto-width">
			<thead>
				<tr>
					<th>ID</th>
					<th>Label</th>
					<th>Default?</th>
					<th>Re-order</th>
					<th>Delete?</th>
				</tr>
			</thead>
			<tbody>
		<?php
		$GLOBALS['system']->includeDBClass('person_group');
		list($options, $default) = Person_Group::getMembershipStatusOptionsAndDefault();
		$options[null] = '';
		$i = 0;
		foreach ($options as $id => $label) {
			?>
			<tr>
				<td>
					<?php
					if ($id) {
						echo $id;
						echo '<input type="hidden" name="membership_status_'.$i.'_id" value="'.$id.'" />';
					}
					echo '<input type="hidden" name="membership_status_ranking[]" value="'.$i.'" />';
					?>
				</td>
				<td><input type="text" name="membership_status_<?php echo $i; ?>_label" value="<?php echo ents($label); ?>" /></td>
				<td><input type="radio" name="membership_status_default_rank" value="<?php echo $i; ?>" <?php if ($id == $default) echo 'checked="checked"'; ?> /></td>
				<td>
					<img src="<?php echo BASE_URL; ?>/resources/img/arrow_up_thin_black.png" class="icon move-row-up" title="Move this role up" />
					<img src="<?php echo BASE_URL; ?>/resources/img/arrow_down_thin_black.png" class="icon move-row-down" title="Move this role down" />
				</td>
				<td>
					<?php
					if ($id && (count($options) > 2)) {
						?>
						<input type="checkbox" name="membership_status_delete[]" data-toggle="strikethrough" data-target="row" value="<?php echo $id; ?>" />
						<?php
					}
					?>
				</td>

			</tr>
			<?php
			$i++;
		}
		?>
		</table>
		<?php
	}

	private function processGroupMembershipStatusOptions()
	{
		$db = $GLOBALS['db'];
		if (!empty($_POST['group_membership_statuses_submitted'])) {
			$i = 0;
			$saved_default = false;
			$rankMap = $_REQUEST['membership_status_ranking'];
			foreach ($rankMap as $k => $v) {
				if ($v == '') $rankMap[$k] = max($rankMap)+1;
			}
			$ranks = array_flip($rankMap);

			while (isset($_POST['membership_status_'.$i.'_label'])) {
				$sql = null;
				$is_default = (int)($_POST['membership_status_default_rank'] == $i);
				if (empty($_POST['membership_status_'.$i.'_id'])) {
					if (!empty($_POST['membership_status_'.$i.'_label'])) {
						$label = $_POST['membership_status_'.$i.'_label'];
						$dupes = $db->queryRow('SELECT 1 FROM person_group_membership_status WHERE label = '.$db->quote($label));
						if ($dupes) {
							add_message("Did not save new group membership status option '".$label."' because there is already a group membership status option with that name", "warning");
						} else {
							$sql = 'INSERT INTO person_group_membership_status (label, `rank`, is_default)
									VALUES ('.$db->quote($label).', '.(int)$ranks[$i].','.$is_default.')';
						}
					}
				} else if (!in_array($_POST['membership_status_'.$i.'_id'], array_get($_POST, 'membership_status_delete', Array()))) {
					$label = $_POST['membership_status_'.$i.'_label'];
					$id = (int)$_POST['membership_status_'.$i.'_id'];
					$dupes = $db->queryRow('SELECT 1 FROM person_group_membership_status WHERE label = '.$db->quote($label).' AND id != '.$id);
					if ($dupes) {
						add_message("Did not update group membership status option '".$label."' because there is already another group membership status option with that name", "warning");
					} else {
						$sql = 'UPDATE person_group_membership_status
								SET label = '.$db->quote($label).',
								is_default = '.$is_default.',
								`rank` = '.(int)$ranks[$i].'
								WHERE id = '.$id;
					}
				}
				if ($sql) {
					$res = $db->query($sql);
					if ($is_default) $saved_default = true;
				}
				$i++;
			}
			if (!$saved_default) {
				$db->query('UPDATE person_group_membership_status SET is_default = 1 ORDER BY label LIMIT 1');
			}
			if (!empty($_POST['membership_status_delete'])) {
				$idSet = implode(',', array_map(Array($db, 'quote'), $_POST['membership_status_delete']));
				// Reset any records using this status to the default status
				$sql = 'UPDATE person_group_membership
						SET membership_status = (SELECT id FROM person_group_membership_status WHERE is_default = 1 AND id NOT IN ('.$idSet.'))
						WHERE membership_status IN ('.$idSet.')';
				$res = $db->query($sql);
				
				$sql = 'DELETE FROM person_group_membership_status
						WHERE id IN ('.$idSet.')';
				$res = $db->query($sql);
			}
		}
	}
	
	private function printAgeBracketOptions()
	{
		?>
		<input type="hidden" name="age_brackets_submitted" value="1" />
		<table class="table table-condensed expandable table-bordered table-auto-width">
			<thead>
				<tr>
					<th>ID</th>
					<th>Label</th>
					<th>Is Default?</th>
					<th>Is Adult? <i class="clickable icon-question-sign icon-white" data-toggle="visible" data-target="#tooltip-agebracket-adult"></i></th>
					<th>Re-order</th>
					<th>Delete? <i class="clickable icon-question-sign icon-white" data-toggle="visible" data-target="#tooltip-agebracket-delete"></i></th>
				</tr>
			</thead>
			<tbody>
		<?php
		$options = $GLOBALS['system']->getDBObjectData('age_bracket', Array(), 'OR', 'rank');
		$options[''] = Array('label' => '', 'is_default' => FALSE, 'is_adult' => FALSE);
		$i = 0;
		$ab = new Age_Bracket();
		foreach ($options as $id => $details) {
			$ab->populate($id, $details);
			$ab->acquireLock();
			?>
			<tr>
				<td>
					<?php
					if ($id) {
						echo $id;
						echo '<input type="hidden" name="age_bracket_'.$i.'_id" value="'.$id.'" />';
					}
					echo '<input type="hidden" name="age_bracket_ranking[]" value="'.$i.'" />';
					?>
				</td>
				<td><?php $ab->printFieldInterface('label', 'age_bracket_'.$i.'_'); ?></td>
				<td><input type="radio" name="age_bracket_default_rank" value="<?php echo $i; ?>" <?php if ($details['is_default']) echo 'checked="checked"'; ?> /></td>
				<td><?php $ab->printFieldInterface('is_adult', 'age_bracket_'.$i.'_'); ?></td>
				<td>
					<img src="<?php echo BASE_URL; ?>/resources/img/arrow_up_thin_black.png" class="icon move-row-up" title="Move this role up" />
					<img src="<?php echo BASE_URL; ?>/resources/img/arrow_down_thin_black.png" class="icon move-row-down" title="Move this role down" />
				</td>
				<td>
					<?php
					if ($id && (count($options) > 2)) {
						?>
						<input type="checkbox" name="age_bracket_delete[]" data-toggle="strikethrough" data-target="row" value="<?php echo $id; ?>" />
						<?php
					}
					?>
				</td>

			</tr>
			<?php
			$i++;
		}
		?>
		</table>
		<div class="help-block custom-field-tooltip" id="tooltip-agebracket-adult" style="display: none;">Persons with an age bracket marked as "adult" are used when, for example, sending an email to all the adults in a family.</div>
		<div class="help-block custom-field-tooltip" id="tooltip-agebracket-delete" style="display: none;">If you delete an age bracket, any persons with that age bracket will be set to the default age bracket</div>
		<?php
	}

	private function processAgeBracketOptions()
	{
		$db = $GLOBALS['db'];
		if (!empty($_POST['age_brackets_submitted'])) {
			$i = 0;
			$saved_default = false;
			$rankMap = $_REQUEST['age_bracket_ranking'];
			foreach ($rankMap as $k => $v) {
				if ($v == '') $rankMap[$k] = max($rankMap)+1;
			}
			$ranks = array_flip($rankMap);
			while (isset($_POST['age_bracket_'.$i.'_label'])) {
				$sql = null;
				$ab = new Age_Bracket();
				if (!empty($_POST['age_bracket_'.$i.'_id'])) {
					$ab->load((int)$_POST['age_bracket_'.$i.'_id']);
				}
				$ab->acquireLock();
				$ab->setValue('is_adult', 0); // The form field will set this back to true if appropriate.
				$ab->processForm('age_bracket_'.$i.'_');
				$ab->setValue('rank', $ranks[$i]);
				$is_default = (int)(array_get($_POST, 'age_bracket_default_rank', -1) == $i);
				if ($is_default) $saved_default = true;
				$ab->setValue('is_default', $is_default);
				if ($ab->id) {
					$ab->save();
				} else if ($ab->getValue('label')) {
					$dupes = $GLOBALS['system']->getDBObjectData('age_bracket', Array('label' => $ab->getValue('label')), 'AND', '', TRUE);
					if ($dupes) {
						add_message("Did not save new age bracket '".$ab->getValue('label')."' because there is already an age bracket with that name", "warning");
					} else {
						$ab->create();
					}
				}
				$ab->releaseLock();
				$i++;
			}
			if (!$saved_default) {
				$db->query('UPDATE age_bracket SET is_default = 1 ORDER BY `rank` LIMIT 1');
			}
			if (!empty($_POST['age_bracket_delete'])) {
				$idSet = implode(',', array_map(Array($db, 'quote'), $_POST['age_bracket_delete']));
				// Reset any persons using this age bracket to the default status
				$sql = 'UPDATE person
						SET age_bracketid = (SELECT id FROM age_bracket WHERE is_default = 1 AND id NOT IN ('.$idSet.'))
						WHERE age_bracketid IN ('.$idSet.')';
				$res = $db->query($sql);
				$sql = 'DELETE FROM age_bracket
						WHERE id IN ('.$idSet.')';
				$res = $db->query($sql);
			}
		}

	}

	private function printPersonStatusOptions()
	{
		?>
		<input type="hidden" name="person_status_submitted" value="1" />
		<table class="table table-condensed expandable table-bordered table-auto-width">
			<thead>
				<tr>
					<th>ID</th>
					<th>Label</th>
					<th>Default?</th>
					<th>Indicates<br />Archived Person? <i class="clickable icon-question-sign icon-white" data-toggle="visible" data-target="#tooltip-status-archived"></i></th>
					<th>Congregation<br />required? <i class="clickable icon-question-sign icon-white" data-toggle="visible" data-target="#tooltip-status-congregation"></i></th>
					<th>In use? <i class="clickable icon-question-sign icon-white" data-toggle="visible" data-target="#tooltip-status-use"></i></th>
					<th>Re-order</th>
					<th>Delete? <i class="clickable icon-question-sign icon-white" data-toggle="visible" data-target="#tooltip-status-delete"></i></th>
				</tr>
			</thead>
			<tbody>
		<?php
		$usage_counts = Person::getStatusStats();
		$options = $GLOBALS['system']->getDBObjectData('person_status', Array(), 'OR', 'rank');
		$options[''] = Array('label' => '', 'is_default' => FALSE, 'is_archived' => FALSE, 'require_congregation' => TRUE, 'active' => 1);
		$i = 0;
		$ab = new Person_Status();
		foreach ($options as $id => $details) {
			$ab->populate($id, $details);
			$ab->acquireLock();
			$class = (!$details['active'] ? 'class="archived"' : '');
			?>
			<tr <?php echo $class; ?>>
				<td>
					<?php
					if ($id) {
						echo $id;
						echo '<input type="hidden" name="pstatus_'.$i.'_id" value="'.$id.'" />';
					}
					echo '<input type="hidden" name="pstatus_ranking[]" value="'.$i.'" />';
					?>
				</td>
				<td><?php $ab->printFieldInterface('label', 'pstatus_'.$i.'_'); ?></td>
				<td><input type="radio" name="pstatus_default_rank" value="<?php echo $i; ?>" <?php if ($details['is_default']) echo 'checked="checked"'; ?> /></td>
				<td class="required-checkbox-col" data-error-message="Person Status Options: You must have at least one 'archived person' status"><?php $ab->printFieldInterface('is_archived', 'pstatus_'.$i.'_'); ?></td>
				<td><?php $ab->printFieldInterface('require_congregation', 'pstatus_'.$i.'_'); ?></td>
				<td><?php $ab->printFieldInterface('active', 'pstatus_'.$i.'_'); ?></td>
				<td>
					<img src="<?php echo BASE_URL; ?>/resources/img/arrow_up_thin_black.png" class="icon move-row-up" title="Move this role up" />
					<img src="<?php echo BASE_URL; ?>/resources/img/arrow_down_thin_black.png" class="icon move-row-down" title="Move this role down" />
				</td>
				<td>
					<?php
					if ($id && (count($options) > 2) && empty($usage_counts[$details['label']])) {
						?>
						<input type="checkbox" name="pstatus_delete[]" data-toggle="strikethrough" data-target="row" value="<?php echo $id; ?>" />
						<?php
					}
					?>
				</td>

			</tr>
			<?php
			$i++;
		}
		?>
		</table>

		<div class="help-block custom-field-tooltip" id="tooltip-status-archived" style="display: none;">Archived persons are omitted from most listings by default. You must have at least one archived-person status. When a family is archived, the family members will be assigned the first archived-person status.</div>
		<div class="help-block custom-field-tooltip" id="tooltip-status-congregation" style="display: none;">You can configure whether persons with a given status must be part of a congregation</div>
		<div class="help-block custom-field-tooltip" id="tooltip-status-use" style="display: none;">Disused statuses cannot be selected when adding or editing persons, but may still apply to existing person records.</div>
		<div class="help-block custom-field-tooltip" id="tooltip-status-delete" style="display: none;">You can only delete a status if it is not used by any person records.</div>

		<p class="help-inline">
			<?php
			echo _('');
			echo '<br />';
			echo _('');
			?>
		<p>
		<?php
	}

	private function processPersonStatusOptions()
	{
		$db = $GLOBALS['db'];
		if (!empty($_POST['person_status_submitted'])) {
			$i = 0;
			$saved_default = false;
			$got_an_archived = false;
			$rankMap = $_REQUEST['pstatus_ranking'];
			foreach ($rankMap as $k => $v) {
				if ($v == '') $rankMap[$k] = max($rankMap)+1;
			}
			$ranks = array_flip($rankMap);

			$to_delete = Array();
			if (!empty($_POST['pstatus_delete'])) {
				foreach ($_POST['pstatus_delete'] as $id) {
					if (!empty($id)) $to_delete[] = $id;
				}
			}

			while (isset($_POST['pstatus_'.$i.'_label'])) {
				$sql = null;
				$ab = new Person_Status();
				if (!empty($_POST['pstatus_'.$i.'_id'])) {
					$ab->load((int)$_POST['pstatus_'.$i.'_id']);
				}
				$ab->acquireLock();
				$ab->setValue('require_congregation', 0); // The form field will set this back to true if appropriate.
				$ab->setValue('is_archived', 0); // The form field will set this back to true if appropriate.
				$ab->setValue('is_default', 0); // The form field will set this back to true if appropriate.
				$ab->setValue('active', 0); // The form field will set this back to true if appropriate.
				$ab->processForm('pstatus_'.$i.'_');
				$ab->setValue('rank', $ranks[$i]);
				$is_default = (int)(array_get($_POST, 'pstatus_default_rank', -1) == $i);
				if ($is_default && !in_array($ab->id, $to_delete)) $saved_default = true;
				$ab->setValue('is_default', $is_default);
				if ($ab->id) {
					$ab->save();
				} else if ($ab->getValue('label')) {
					$dupes = $GLOBALS['system']->getDBObjectData('person_status', Array('label' => $ab->getValue('label')), 'AND', '', TRUE);
					if ($dupes) {
						add_message("Did not save new person_status '".$ab->getValue('label')."' because there is already a person status with that name", "warning");
					} else {
						$ab->create();
					}
				}
				$ab->releaseLock();
				if ($ab->getValue('is_archived') && !in_array($ab->id, $to_delete)) $got_an_archived = TRUE;
				$i++;
			}
			if (!$saved_default) {
				$db->query('UPDATE person_status SET is_default = 1 ORDER BY `rank` LIMIT 1');
			}
			foreach ($to_delete as $id) {
				// The interface should have prevented attempts to delete an in-use status.
				// So we'll just rely on the foriegn key to catch anything dodgy here.
				$s = new Person_status($id);
				if (!$got_an_archived && ($s->getValue('is_archived'))) {
					add_message("The person status '".$s->getValue('label')."' was not deleted, because you must have at least one status for archived persons", "error");
					continue;
				}
				$s->delete();
			}
		}

	}

}

