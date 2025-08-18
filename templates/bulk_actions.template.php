<?php
$in_group = (array_get($_REQUEST, 'view') == 'groups') && (!empty($_REQUEST['groupid']) || !empty($_REQUEST['person_groupid']));
$groupid = array_get($_REQUEST, 'groupid', array_get($_REQUEST, 'person_groupid'));
?>

<div class="form-horizontal bulk-actions">
	<?php echo _('With selected persons:')?>
		<select id="bulk-action-chooser" class="no-autofocus">
			<option><?php echo _('-- Choose Action --')?></option>
				<?php
				if ($GLOBALS['user_system']->havePerm(PERM_EDITGROUP)) {
					?>
					<option value="update-field"><?php echo _('Set field(s)')?></option>
					<?php
					if ($in_group) {
						?>
						<option value="remove-from-group"><?php echo _('Remove from this group')?></option>
						<option value="move-to-group"><?php echo _('Move to a different group')?></option>
						<option value="add-to-group"><?php echo _('Add to another group')?></option>
						<?php
					} else {
						?>
						<option value="add-to-group"><?php echo _('Add to a group')?></option>
						<?php
					}
				}
				if ($GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
					?>
					<option value="add-note"><?php echo _('Add note')?></option>
					<?php
				}
				?>
					<option value="email"><?php echo _('Send email')?></option>
				<?php
				if (SMS_Sender::canSend()) {
					?>
					<option value="smshttp"><?php echo _('Send SMS')?></option>
					<?php
				}
				?>
					<option value="export"><?php echo _('Export...')?></option>
				<?php
				if (version_compare(PHP_VERSION, '5.2', '>=') && !SizeDetector::isNarrow()) {
					echo '<option value="document-merge">'._('Mail merge a document')."</option>\n";
				}
				?>
				<option value="envelopes"><?php echo _('Print envelopes')?></option>
				<?php
				require_once 'db_objects/action_plan.class.php';
				$plan_chooser = Action_Plan::getMultiChooser('planid', Array());
				if ($plan_chooser) {
					?>
					<option value="execute-plan"><?php echo _('Execute an action plan')?></option>
					<?php
				}

				if (function_exists('custom_bulk_action_options')) {
					custom_bulk_action_options();
				}
				?>
	</select>
	<?php
	if ($GLOBALS['user_system']->havePerm(PERM_EDITGROUP)) {
		?>
		<span class="bulk-action" id="remove-from-group">
			<input type="submit" class="btn " value="Go" data-set-form-action="<?php echo BASE_URL; ?>?view=_edit_group&action=remove_members&groupid=<?php echo $groupid; ?>" />
		</span>


		<div class="bulk-action well" id="update-field">
			<table class="valign-middle">
			<?php
			$dummy = new Person();
			foreach (Array('status', 'age_bracketid') as $field) {
				$dummy->fields[$field]['allow_empty'] = TRUE;
				$dummy->fields[$field]['empty_text'] = '(No change)';
				$dummy->setValue($field, NULL);
				echo '<tr><td>Set '.$dummy->getFieldLabel($field).' to: </td><td>';
				$dummy->printFieldInterface($field);
				echo '</td></tr>';
			}
			//Handle congregation separately because we need to have both 'no change' and 'none' options.
			$params = Array(
				'type' => 'select',
				'allow_empty' => TRUE,
				'empty_text' => '(No change)',
				'options' => Array(0 => '(None)'),
			);
			foreach ($GLOBALS['system']->getDBObjectdata('congregation', Array()) as $cid => $cong) {
				$params['options'][$cid] = $cong['name'];
			}
			echo '<tr><td>Set '.$dummy->getFieldLabel('congregationid').' to: </td><td>';
			print_widget('congregationid', $params, NULL);
			echo '</td></tr>';


			$dummy->fields['congregationid']['options'][0] = '(None)';
			$customFields = $GLOBALS['system']->getDBObjectData('custom_field', Array(), 'OR', 'rank');
			$dummy = new Custom_Field();
			$addParams = Array(
							'type' => 'select',
							'options' => Array('Replacing existing values ', 'Adding to existing values')
						);
			foreach ($customFields as $fieldid => $fieldDetails) {
				$dummy->populate($fieldid, $fieldDetails);
				echo '<tr><td>Set '.ents($dummy->getValue('name')).' to: </td><td>';
				$dummy->printWidget('');
				if ($dummy->getValue('allow_multiple')) {
					echo '</td><td>';
					print_widget('custom_'.$fieldid.'_add', $addParams, 0);
				}
				echo '</td></tr>';
			}

			?>
			</table>
			<input type="submit" class="btn" onclick="return confirm('Are you sure you want to bulk-update these persons?')" value="Go" data-set-form-action="<?php echo BASE_URL; ?>?view=_persons_bulk_update&backto=<?php echo urlencode(http_build_query($_GET)); ?>" />
		</div>

		<?php
		$verbs = Array('add');
		if ($in_group) $verbs[] = 'move';
		foreach ($verbs as $verb) {
			?>
			<div class="bulk-action well" id="<?php echo $verb; ?>-to-group">
				<p>
				<?php
				if ($verb == 'move') {
					echo _('Move selected persons to ');
					echo '<input type="hidden" name="membership_status" value="_PRESERVE_" />';
				} else {
					echo _('Add selected persons as ');
					$GLOBALS['system']->includeDBClass('person_group');
					Person_Group::printMembershipStatusChooser('membership_status', NULL);
					echo 'of';
				}
				?>
				</p>

				<p>
				<label class="radio">
					<input type="radio" name="<?php echo $verb; ?>_group_source" value="existing" checked="checked"
							data-toggle="enable" data-target="#<?php echo $verb; ?>_existing_group *" />
					<b><?php echo _('an existing group:')?></b>
				</label>
				<div class="indent-left" id="<?php echo $verb; ?>_existing_group">
					<?php
					$GLOBALS['system']->includeDBClass('person_group');
					Person_Group::printChooser('groupid', NULL);
					if ($verb == 'move') {
						?>
						<input type="hidden" name="remove_from_groupid" value="<?php echo $groupid ?>" />
						<?php
					}
					?>
					<input type="submit" class="btn " value="Go" data-require-fields="#<?php echo $verb; ?>_existing_group [name=groupid]" data-set-form-action="<?php echo BASE_URL; ?>?view=_edit_group&action=add_members" />
				</div>
				</p>

				<p>
				<label class="radio">
					<input type="radio" name="<?php echo $verb; ?>_group_source" value="new"
							data-toggle="enable" data-target="#<?php echo $verb; ?>_new_group *" />
					<b><?php echo _('a new group:')?></b>
				</label>
				<table class="indent-left" id="<?php echo $verb; ?>_new_group">
					<tr>
						<td><?php echo _('New group name: ')?></td>
						<td>
							<?php
							$GLOBALS['system']->includeDBClass('person_group');
							$g = new Person_Group();
							$g->printFieldInterface('name');
							?>
						</td>
					</tr>
					<tr>
						<td>Group category:</td>
						<td><?php $g->printFieldInterface('categoryid'); ?></td>
					</tr>
					<tr>
						<td></td>
						<td>
						<?php
						if ($verb == 'move') {
							?>
							<input type="hidden" name="remove_from_groupid" value="<?php echo $groupid ?>" />
							<?php
						}
						?>
							<input type="hidden" name="new_person_group_submitted" value="1" />
							<input type="submit" class="btn " value="Go" data-require-fields="<?php echo $verb; ?>_new_group [name=name]" data-set-form-action="<?php echo BASE_URL; ?>?view=groups__add" />
						</td>
					</tr>
				</table>
				</p>
			</div>
			<?php
		}
	}
	if ($GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
		?>
		<div class="bulk-action well" id="add-note">
			<?php
			$GLOBALS['system']->includeDBClass('person_note');
			$note = new Person_Note();
			$note->printForm();
			?>
			<div class="control-group">
				<div class="controls">
					<input type="submit" name="new_note_submitted" class="btn " value="Go" data-set-form-action="<?php echo BASE_URL; ?>?view=_add_note_to_person" />
				</div>
			</div>
		</div>
		<?php
	}
	?>
		<div class="bulk-action well" id="email">
			<p><?php echo _('Send an email to')?></p>
			<label class="radio"><input class="compulsory" type="radio" name="email_type" value="person" id="email_type_person" checked="checked" /><?php echo _('the selected persons')?></label>
			<label class="radio"><input type="radio" name="email_type" value="family" id="email_type_family" /><?php echo _('the adults in the selected persons&#8217; families')?></label></p>
			<label class="checkbox"><input type="checkbox" name="method" value="public" id="method-public" /><?php echo _('Allow recipients to see each other&#8217;s email addresses')?></label>
			<input type="submit" class="btn " value="Go" data-set-form-target="hidden" data-set-form-action="<?php echo BASE_URL; ?>?call=email" />
		</div>
	<?php
	if (SMS_Sender::canSend()) {
		?>
		<div class="bulk-action well" id="smshttp">
			<div class="control-group">
				<label class="control-label"><?php echo _('To:')?></label>
				<div class="controls">
					<label class="radio">
						<input class="compulsory" type="radio" name="sms_type" value="person" id="sms_type_person" checked="checked" />
						<?php echo _('the selected persons')?>
					</label>
					<label class="radio">
						<input type="radio" name="sms_type" value="family" id="sms_type_family" />
						<?php echo _('the adults in the selected persons families')?>
					</label>
				</div>
			</div>
			<div class="control-group">
				<label class="control-label">Message: </label>
				<div class="controls">
					<?php
					SMS_Sender::printTextbox();
					?>
				</div>
			</div>
		<?php
		if ($GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
			?>
			<div class="control-group">
				<label class="control-label">After sending:</label>
				<div class="controls">
				  <label class="checkbox">
					<input type="checkbox" name="saveasnote" <?php if (ifdef('SMS_SAVE_TO_NOTE_BY_DEFAULT')) { echo 'checked="checked"'; } ?>  />
					save as note
				  </label>
				</div>
			</div>
			<?php
		}
		?>
			<div class="control-group">
				<div class="controls">
					<input type="button" class="btn bulk-sms-submit" value="Send" />
				</div>
			</div>
			<div class="control-group" id="bulk-sms-results"></div>
		</div>
		<?php
	}
	?>
		<div class="bulk-action well" id="export">
			Download Person data:
			<input type="submit" class="btn " value="CSV" data-set-form-action="<?php echo BASE_URL; ?>?call=csv&merge_type=person" />
			<input type="submit" class="btn" value="vCard" data-set-form-action="<?php echo BASE_URL; ?>?call=vcf" />
			&nbsp;&nbsp;Download Family data:
			<input type="submit" class="btn " value="CSV" data-set-form-action="<?php echo BASE_URL; ?>?call=csv&merge_type=family" />
		</div>
    <?php
	if (version_compare(PHP_VERSION, '5.2', '>=') && !SizeDetector::isNarrow()) {
		include_once 'calls/call_document_merge.class.php';
		$templates = @glob(Call_Document_Merge::getSavedTemplatesDir().'/*.*');
		?>
		<div class="bulk-action well" id="document-merge">
			<div class="control-group">
				<label class="control-label"><?php echo _('Template Document')?></label>
				<div class="controls">
				<?php
				if (!empty($templates)) {
					$tOptions = Array('' => '', '__NEW__' => 'Upload a new file...');
					foreach ($templates as $t) $tOptions[basename($t)] = basename($t);
					print_widget('source_doc_select', Array('type' => 'select', 'options' => $tOptions, 'class' => 'merge-template'), '');
					?>
					<div id="merge-template-upload" class="indent-left" style="display:none">
						<input type="file" name="source_document" />
						<label class="checkbox"><input type="checkbox" name="save_template" value="1" />Save template for next time</label>
					</div>
					<p class="help-inline">
						<a target="roster-merge-help" class="med-newwin" href="<?php echo BASE_URL; ?>index.php?call=document_merge_help"><i class="icon-help"></i>Help and examples</a>
						&nbsp;
						<button type="submit" class="btn btn-mini muted" name="preview_keywords" onclick="$('input[name=source_document]').removeClass('compulsory')" data-set-form-target="_blank" data-set-form-action="<?php echo BASE_URL; ?>index.php?call=document_merge">Preview all tags</button>
					</p>
					<?php
				} else {
					?>
					<input class="compulsory" type="file" name="source_document" />
					<p class="help-inline">
						<a target="roster-merge-help" class="med-newwin" href="<?php echo BASE_URL; ?>index.php?call=document_merge_help"><i class="icon-help"></i>Help and examples</a>
						&nbsp;
						<button type="submit" class="btn btn-mini muted" name="preview_keywords" onclick="$('input[name=source_document]').removeClass('compulsory')" data-set-form-target="_blank" data-set-form-action="<?php echo BASE_URL; ?>index.php?call=document_merge">Preview all tags</button>
					</p>
					<label class="checkbox"><input type="checkbox" name="save_template" value="1" />Save template for next time</label>
					<?php
				}
				?>
				</div>
			</div>
		<?php
		if (ifdef('MAIL_MERGE_INCLUDE_LEGACY', FALSE)) {
			// Just in case somebody still needs legacy-mode merge, we can re-activate it in the conf.php
			?>
			<div class="control-group">
				<label class="control-label"><?php echo _('Template type')?></label>
				<div class="controls">
					<label class="radio inline">
						<input type="radio" name="template_format" value="tbs" checked="checked" />
						Default
					</label>
					<label class="radio inline" style="color:#888">
						<input type="radio" name="template_format" value="legacy" />
						Legacy <small><i>(Jethro ≤ v2.27)</i></small>
					</label>
				</div>
			</div>
			<?php
		}
		?>
			<div class="control-group">
				<label class="control-label"><?php echo _('Merge for')?></label>
				<div class="controls">
						<label class="radio">
						<?php
							if (isset($merge_type_person_attendance)) {
								echo '<input class="compulsory" type="radio" name="merge_type" value="person_data" id="merge_type_person_attendance" checked="checked" />';
							} else {
								echo '<input class="compulsory" type="radio" name="merge_type" value="person" id="merge_type_person" checked="checked" />';
							}
							echo _('each selected person');
							?>

						</label>
						<label class="radio">
							<input type="radio" name="merge_type" value="family" id="merge_type_family" />
							<?php echo _('each family that contains a selected person')?>
							<span class="smallprint">
						</label>
				</div>
			</div>
			<?php if (!empty($_REQUEST['queryid'])) print_hidden_field('queryid', $_REQUEST['queryid']); ?>
			<div class="control-group">
				<div class="controls">
					<input type="submit" class="btn " value="Go" data-set-form-action="<?php echo BASE_URL; ?>index.php?call=document_merge" />
				</div>
			</div>


		</div>
 		<?php
	}
	?>
		<div class="bulk-action well" id="envelopes">
			<p><?php echo _('Print envelopes addressed to ')?></p>
			<label class="radio">
				<input class="compulsory" type="radio" name="addressee" value="person" id="addressee_person" checked="checked" />
				<?php echo _('the selected persons themselves, grouped by family (eg &#8220;John, Joanne &amp; James Smith&#8221;)')?>
			</label>
			<label class="radio">
				<input type="radio" name="addressee" value="family" id="addressee_family" />
				<?php echo _('the families the selected persons belong to (eg &#8220;Jones Family&#8221;)')?>
			</label>
			<label class="radio">
				<input type="radio" name="addressee" value="adults" id="addressee_adults" />
				<?php echo _('adult members of the selected persons&#8217; families (eg &#8220;Bert and Marjorie Citizen&#8221;)')?>
			</label>
			<input type="submit" class="btn " value="Go" data-set-form-target="envelope" data-set-form-action="<?php echo BASE_URL; ?>?call=envelopes" />
		</div>
	<?php
	if ($plan_chooser) {
		?>
		<div class="bulk-action well" id="execute-plan">
		<?php echo $plan_chooser; ?>
		<p><?php echo _('Reference date for plans:')?> <?php print_widget('plan_reference_date', Array('type' => 'date'), NULL); ?>
		&nbsp;
		<input type="submit" value="Go" data-set-form-action="<?php echo BASE_URL; ?>?view=_execute_plans" /></p>
		</div>
		<?php
	}

	if (function_exists('custom_bulk_action_bodies')) {
		custom_bulk_action_bodies();
	}
	?>

</div>


