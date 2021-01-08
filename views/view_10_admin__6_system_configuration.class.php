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
		?>
		<form method="post">
			<div class="form-horizontal">
			<?php
			foreach (Config_Manager::getSettings() as $symbol => $details) {
				$details['note'] = str_replace('<system_url>', BASE_URL, $details['note']);
				if ($details['heading']) {
					echo '<hr /><h4>'.ents($details['heading']).'</h4>';
				}
				?>
				<div class="control-group">
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
								echo '<i>'._("This setting has been set in the conf.php file").'</i>';
							} else {
								$this->printValue($symbol, $details);
								if ($details['note']) echo '<p class="smallprint">'.ents($details['note']).'</p>';
								echo '<p class="smallprint">'._('To edit this setting here, first remove it from your conf.php file').'</p>';;
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
			case 'GROUP_MEMBERSHIP_STATUS_OPTIONS':
				$this->printGroupMembershipStatusOptions();
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
						$sql = 'INSERT INTO person_group_membership_status (label, rank, is_default)
								VALUES ('.$db->quote($_POST['membership_status_'.$i.'_label']).', '.(int)$ranks[$i].','.$is_default.')';
					}
				} else if (!in_array($_POST['membership_status_'.$i.'_id'], array_get($_POST, 'membership_status_delete', Array()))) {
					$sql = 'UPDATE person_group_membership_status
							SET label = '.$db->quote($_POST['membership_status_'.$i.'_label']).',
							is_default = '.$is_default.',
							rank = '.(int)$ranks[$i].'
							WHERE id = '.(int)$_POST['membership_status_'.$i.'_id'];
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
					<th>Is Adult?</th>
					<th>Is Default?</th>
					<th>Re-order</th>
					<th>Delete?</th>
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
				<td><?php $ab->printFieldInterface('is_adult', 'age_bracket_'.$i.'_'); ?></td>
				<td><input type="radio" name="age_bracket_default_rank" value="<?php echo $i; ?>" <?php if ($details['is_default']) echo 'checked="checked"'; ?> /></td>
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
		<p class="help-inline">
			<?php
			echo _('Age brackets with the "is adult" box ticked are treated as adults when, for example, sending an email to the adults in a family.');
			echo '<br />';
			echo _('If you delete an age bracket, persons using that age bracket will be set to the default age bracket.');
			?>
		<p>
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
				$ab->processForm('age_bracket_'.$i.'_');
				$ab->setValue('rank', $ranks[$i]);
				$is_default = (int)(array_get($_POST, 'age_bracket_default_rank', -1) == $i);
				if ($is_default) $saved_default = true;
				$ab->setValue('is_default', $is_default);
				if ($ab->id) {
					$ab->save();
				} else if ($ab->getValue('label')) {
					$ab->create();
				}
				$ab->releaseLock();
				$i++;
			}
			if (!$saved_default) {
				$db->query('UPDATE age_bracket SET is_default = 1 ORDER BY rank LIMIT 1');
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

}

