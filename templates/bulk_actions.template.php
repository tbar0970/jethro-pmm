<?php
$in_group = (array_get($_REQUEST, 'view') == 'groups') && (!empty($_REQUEST['groupid']) || !empty($_REQUEST['person_groupid']));
$groupid = array_get($_REQUEST, 'groupid', array_get($_REQUEST, 'person_groupid')); 
?>

<div class="form-horizontal bulk-actions">
	With selected people: 
		<select id="bulk-action-chooser">
			<option>-- Choose Action --</option>
				<?php
				if ($GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
					if ($in_group) {
						?>
						<option value="remove-from-group">Remove from this group</option>
						<option value="move-to-group">Move to a different group</option>
						<option value="add-to-group">Add to another group</option>
						<?php
					} else {
						?>
						<option value="add-to-group">Add to a group</option>
						<?php
					}
					?>
					<option value="update-field">Update field(s)</option>
					<?php
				}
				if ($GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
					?>
					<option value="add-note">Add note</option>
					<?php
				}
				?>
					<option value="email">Send email</option>
				<?php
				$enable_sms = $GLOBALS['user_system']->havePerm(PERM_SENDSMS) && defined('SMS_HTTP_URL') && constant('SMS_HTTP_URL') && defined('SMS_HTTP_POST_TEMPLATE') && constant('SMS_HTTP_POST_TEMPLATE');
				if ($enable_sms) {
					?>
					<option value="smshttp">Send SMS</option>
					<?php
				}
				?>
					<option value="envelopes">Print envelopes</option>
					<option value="csv">Export as CSV</option>
					<option value="vcf">Export as vCard</option>
				<?php
				if (version_compare(PHP_VERSION, '5.2', '>=')) {
					?>
					<option value="mail-merge">Mail merge a document</option>
					<?php
				}
				require_once 'db_objects/action_plan.class.php';
				$plan_chooser = Action_Plan::getMultiChooser('planid', Array());
				if ($plan_chooser) {
					?>
					<option value="execute-plan">Execute an action plan</option>
					<?php
				}
				
				if (function_exists('custom_bulk_action_options')) {
					custom_bulk_action_options();
				}
				?>
				
	</select>
	<?php
	if ($GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
		?>
		<span class="bulk-action" id="remove-from-group">
			<input type="submit" class="btn " value="Go" data-set-form-action="<?php echo BASE_URL; ?>?view=_edit_group&action=remove_members&groupid=<?php echo $groupid; ?>" />
		</span>
		
		
		<div class="bulk-action well" id="update-field">
			<table>
			<?php
			$dummy = new Person();
			foreach (Array('congregationid', 'status', 'age_bracket') as $field) {
				$dummy->fields[$field]['allow_empty'] = TRUE;
				$dummy->fields[$field]['empty_text'] = '(No change)';
				$dummy->setValue($field, NULL);
				echo '<tr><td>Set '.$dummy->getFieldLabel($field).' to: </td><td>';
				$dummy->printFieldInterface($field);
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
					echo 'Move selected persons to ';
					echo '<input type="hidden" name="membership_status" value="_PRESERVE_" />';
				} else {
					echo 'Add selected persons as ';
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
					<b>an existing group:</b>
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
					<input type="submit" class="btn " value="Go" data-set-form-action="<?php echo BASE_URL; ?>?view=_edit_group&action=add_members" />
				</div>
				</p>

				<p>
				<label name="<?php echo $verb; ?>_group_source" class="radio">
					<input type="radio" name="<?php echo $verb; ?>_group_source" value="new"
							data-toggle="enable" data-target="#<?php echo $verb; ?>_new_group *" />
					<b>a new group:</b>
				</label>
				<table class="indent-left" id="<?php echo $verb; ?>_new_group">
					<tr>
						<td>New group name: </td>
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
							<input type="submit" class="btn " value="Go" data-set-form-action="<?php echo BASE_URL; ?>?view=groups__add" />
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
			<input type="hidden" name="new_note_submitted" value="1" />
			<?php 
			$GLOBALS['system']->includeDBClass('person_note');
			$note = new Person_Note();
			$note->printForm();
			?>
			<div class="control-group">
				<div class="controls">
					<input type="submit" class="btn " value="Go" data-set-form-action="<?php echo BASE_URL; ?>?view=_add_note_to_person" />
				</div>
			</div>
		</div>
		<?php
	}
	if (version_compare(PHP_VERSION, '5.2', '>=')) {
		?>
		<div class="bulk-action well" id="mail-merge">
			<div class="control-group">
				<label class="control-label">Source Document</label>
				<div class="controls">
					<input class="compulsory" type="file" name="source_document" />
					<p class="help-inline">(ODT or DOCX format)</p> 
				</div>
			</div>
			<div class="control-group">
				<label class="control-label">Merge for</label>
				<div class="controls">
						<label class="radio">
							<input class="compulsory" type="radio" name="merge_type" value="person" id="merge_type_person" checked="checked" />
							each of the selected persons
							<span class="smallprint">
								(Sample file: 
								<a href="<?php echo BASE_URL; ?>/resources/sample_mail_merge.odt">ODT</a>, 
								<a href="<?php echo BASE_URL; ?>/resources/sample_mail_merge.docx">DOCX</a>)
							</span>
						</label>
						<label class="radio">
							<input type="radio" name="merge_type" value="family" id="merge_type_family" />
							each of the families that the selected persons belong to
							<span class="smallprint">
								(Sample file: 
								<a href="<?php echo BASE_URL; ?>/resources/sample_mail_merge_family.odt">ODT</a>,
								<a href="<?php echo BASE_URL; ?>/resources/sample_mail_merge_family.docx">DOCX</a>)
							</span>
						</label>
				</div>
			</div>
			<div class="control-group">
				<div class="controls">
					<input type="submit" class="btn " value="Go" data-set-form-action="<?php echo BASE_URL; ?>?call=odf_merge" />
				</div>
			</div>		

			
		</div>
 		<?php
	}
	?>
		<div class="bulk-action well" id="email">
			<p>Send an email to</p>
			<label class="radio"><input class="compulsory" type="radio" name="email_type" value="person" id="email_type_person" checked="checked" />the selected persons</label>
			<label class="radio"><input type="radio" name="email_type" value="family" id="email_type_family" />the adults in the selected persons&#8217; families</label></p>
			<label class="checkbox"><input type="checkbox" name="method" value="public" id="method-public" />Allow recipients to see each other&#8217;s email addresses</label>
			<input type="submit" class="btn " value="Go" data-set-form-target="hidden" data-set-form-action="<?php echo BASE_URL; ?>?call=email" />
		</div>
	<?php
	if ($enable_sms) {
		?>
		<div class="bulk-action well" id="smshttp">
			<div class="control-group">
				<label class="control-label">To:</label>
				<div class="controls">
					<label class="radio">
						<input class="compulsory" type="radio" name="sms_type" value="person" id="sms_type_person" checked="checked" />
						the selected persons
					</label>
					<label class="radio">
						<input type="radio" name="sms_type" value="family" id="sms_type_family" />
						the adults in the selected persons' families
					</label>
				</div>
			</div>
			<div class="control-group">
				<label class="control-label">Message: </label>
				<div class="controls">
					<textarea name="message" class="span4" rows="5" cols="30" maxlength="<?php echo SMS_MAX_LENGTH; ?>"></textarea>
					<br />
					<input type="submit" class="btn " value="Send" data-set-form-action="<?php echo BASE_URL; ?>?view=_send_sms_http" />
				</div>
			</div>
		</div>
		<?php
	}
	?>
		<div class="bulk-action well" id="csv">
			<p>Get a CSV file of:</p>
			<label class="radio"><input class="compulsory" type="radio" name="merge_type" value="person" id="merge_type_person" checked="checked" />the selected persons</label>
			<label class="radio"><input type="radio" name="merge_type" value="family" id="merge_type_family" />the families the selected persons belong to</label></p>
			<input type="submit" class="btn " value="Go" data-set-form-action="<?php echo BASE_URL; ?>?call=csv" />
		</div>

		<div class="bulk-action well" id="envelopes">
			<p>Print envelopes addressed to </p>
			<label class="radio">
				<input class="compulsory" type="radio" name="addressee" value="person" id="addressee_person" checked="checked" />
				the selected persons themselves, grouped by family (eg &#8220;John, Joanne &amp; James Smith&#8221;)
			</label>
			<label class="radio">
				<input type="radio" name="addressee" value="family" id="addressee_family" />
				the families the selected persons belong to (eg &#8220;Jones Family&#8221;)
			</label>
			<label class="radio">
				<input type="radio" name="addressee" value="adults" id="addressee_adults" />
				adult members of the selected persons&#8217; families (eg &#8220;Bert and Marjorie Citizen&#8221;)
			</label>
			<input type="submit" class="btn " value="Go" data-set-form-target="envelope" data-set-form-action="<?php echo BASE_URL; ?>?call=envelopes" />
		</div>
	<?php
	if ($plan_chooser) {
		?>
		<div class="bulk-action well" id="execute-plan">
		<?php echo $plan_chooser; ?>
		<p>Reference date for plans: <?php print_widget('plan_reference_date', Array('type' => 'date'), NULL); ?>
		&nbsp;
		<input type="submit" value="Go" data-set-form-action="<?php echo BASE_URL; ?>?view=_execute_plans" /></p>
		</div>
		<?php
	}
	?>

	<div class="bulk-action well" id="vcf">
		<input type="submit" value="Go" class="btn" data-set-form-action="<?php echo BASE_URL; ?>?call=vcf" />
	</div>
</div>

<?php
if (function_exists('custom_bulk_action_bodies')) {
	custom_bulk_action_bodies();
}
