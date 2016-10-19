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
					check_db_result($res);
					if ($is_default) $saved_default = true;
				}
				$i++;
			}
			if (!empty($_POST['membership_status_delete'])) {
				$sql = 'DELETE FROM person_group_membership_status WHERE id IN ('.implode(',', array_map(Array($db, 'quote'), $_POST['membership_status_delete'])).')';
				$res = $db->query($sql);
				check_db_result($res);
			}
			if (!$saved_default) {
				$db->query('UPDATE person_group_membership_status SET is_default = 1 ORDER BY label LIMIT 1');
				check_db_result($res);
			}

			$db->query('UPDATE person_group_membership SET membership_status = (SELECT id FROM person_group_membership_status WHERE is_default) WHERE membership_status IS NULL');
			check_db_result($res);
		}
	}

	public function printView()
	{
		?>
		<p class="text alert alert-info">
			<?php 
			$text = _("This page shows the system-wide Jethro configuration settings.  Some settings can be edited on this page; others need to be changed by your system administrator in the Jethro configuration file.");
			if (ifdef('SYSADMIN_HREF')) {
				$text = str_replace(
							_('system administrator'), 
							'<a href="'.SYSADMIN_HREF.'">'._('system administrator').'</a>',
							$text
						);
			}
			echo $text;
			?>
		</p>
		<table class="table no-autofocus system-config">
			<tr>
				<td colspan="2"><h3>Overall system settings</h3></td>
			</tr>
			<tr>
				<th>System Name</th>
				<td><?php echo SYSTEM_NAME; ?></td>
			</tr>
			<tr>
				<th>Base URL</th>
				<td><?php echo BASE_URL; ?></td>
			</tr>
			<tr>
				<th>Require HTTPS</th>
				<td><?php echo REQUIRE_HTTPS ? 'Yes' : 'No'; ?></td>
			</tr>
			<tr>
				<td colspan="2"><h3>Jethro behaviour settings</h3></td>
			</tr>
			<tr>
				<th>Enabled Features</th>
				<td>
					<?php
					$enabled = explode(',', ENABLED_FEATURES);
					foreach (explode(',', 'NOTES,PHOTOS,DATES,ATTENDANCE,ROSTERS&SERVICES,SERVICEDETAILS,DOCUMENTS,SERVICEDOCUMENTS') as $feature) {
						echo '<i class="icon-'.(in_array($feature, $enabled) ? 'ok-sign' : 'ban-circle').'"></i>'.$feature.'<br />';
					}
					?>
				</td>
			</tr>
			<tr>
				<th>Require note when adding new family?</th>
				<td><?php echo REQUIRE_INITIAL_NOTE ? 'Yes' : 'No'; ?></td>
			</tr>
			<tr>
				<th>Attendance list order</th>
				<td><?php echo ATTENDANCE_LIST_ORDER; ?>
					<br /><small>When recording attendance, persons will be listed in this order</small>
				</td>
			</tr>
			<tr>
				<th>Chunk size for listings</th>
				<td>
					<?php echo CHUNK_SIZE; ?>
					<br /><small>When listing all persons or families, Jethro will paginate the results and aim for this number per page (up to a maximum of 26 pages).</small>
				
				</td>
			</tr>			
			<tr>
				<th>Lock length for editing objects</th>
				<td><?php echo LOCK_LENGTH; ?>
					<br /><small>When you open an object for editing, Jethro will prevent other users from editing the object for this long.  You will need to save your changes within this timeframe.</small>
				</td>
			</tr>

			<tr>
				<td colspan="2"><h3>Security settings</h3></td>
			</tr>
			<tr>
				<th>Default Permissions</th>
				<td>
				<?php
				$GLOBALS['system']->includeDBClass('staff_member');
				$sm = new Staff_Member();
				$sm->printFieldValue('permissions', DEFAULT_PERMISSIONS);
				?>
				</td>
			</tr>
			<tr>
				<th>Session Inactivity Timeout</th>
				<td>
					<?php echo (defined('SESSION_TIMEOUT_MINS') && (SESSION_TIMEOUT_MINS > 0)) ? SESSION_TIMEOUT_MINS : 90; ?>mins
					<br /><small>Users will be asked to log in again if they haven't done anything for this length of time.  This is important for security, especially on mobile devices.</small>
				</td>
			</tr>
			<tr>
				<th>Maximum Session Length</th>
				<td>
					<?php 
					$val= (defined('SESSION_MAXLENGTH_MINS') && (SESSION_MAXLENGTH_MINS > 0)) ? SESSION_MAXLENGTH_MINS : 8*60;
					if (($val % 60 == 0) && ($val > 60)) {
						echo ($val/60).' hours';
					} else {
						echo $val.' mins';
					}
					?>
					<br /><small>Active users will be asked to log in again after this length of time.  This is important for security, especially on mobile devices.</small>
				</td>
			</tr>			
			<tr>
				<td colspan="2"><h3>Jethro data structure settings</h3></td>
			</tr>
			<tr>
				<th>Person Status Options</th>
				<td><?php echo PERSON_STATUS_OPTIONS; ?> (Default <?php echo PERSON_STATUS_DEFAULT; ?>)</td>
			</tr>
			<tr>
				<th>Age Bracket Options</th>
				<td>
					<?php echo AGE_BRACKET_OPTIONS; ?>
					<br /><small>This list must always begin with 'adult'</small>
				</td>
			</tr>
			<tr>
				<th>Group Membership Status Options</th>
				<td>
					<form method="post">
					<input type="hidden" name="group_membership_statuses_submitted" value="1" />
					<table class="table-condensed expandable table-bordered table-auto-width">
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
								if ($id) {
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
					<input type="submit" value="Save" class="btn" />
					</form>
				</td>
			</tr>
			<tr>
				<td colspan="2"><h3>Rosters and Services</h3></td>
			</tr>
			<tr>
				<th>Roster Weeks Default</th>
				<td>
					<?php echo ROSTER_WEEKS_DEFAULT; ?>
					<br /><small>By default, rosters will display this number of weeks in the future.</small>
				</td>
			</tr>
			<tr>
				<th>Roster repeat date threshold</th>
				<td>
					<?php echo REPEAT_DATE_THRESHOLD; ?>
					<br /><small>If a roster has more than this number of columns, the date column will be repeated on the right hand side</small>
				</td>
			</tr>
			<tr>
				<th>Service Documents: Folders to populate</th>
				<td>
					<?php

					
					if (SERVICE_DOCS_TO_POPULATE_DIRS) {
						echo implode('<br />', explode('|', SERVICE_DOCS_TO_POPULATE_DIRS));
					}
					?>
				</td>
			</tr>
			<tr>
				<th>Service Documents: Folders to expand</th>
				<td>
					<?php
					if (SERVICE_DOCS_TO_EXPAND_DIRS) {
						echo implode('<br />', explode('|', SERVICE_DOCS_TO_POPULATE_DIRS));
					}
					?>
				</td>
			</tr>

			<tr>
				<td colspan="2"><h3>External tools</h3></td>
			</tr>
			<tr>
				<th>Bible reference URL</th>
				<td>
					<?php echo BIBLE_URL; ?>
					<br /><small>Bible references in rosters will be linked using this URL template</small>
				</td>
			</tr>
			<tr>
				<th>Maps URL</th>
				<td>
					<?php echo MAP_LOOKUP_URL; ?>
					<br /><small>The "map" link displayed next to a family's address uses this URL template</small>
				</td>
			</tr>
			<tr>
				<th>Email chunk size</th>
				<td>
					<?php echo EMAIL_CHUNK_SIZE; ?>
					<br /><small>Email servers can only handle a limited number of recipients per email.  When constructing email links to multiple persons, Jethro will divide the list into several links if there are more than this number of recipients.</small>
				</td>
			</tr>
			<tr>
				<th>SMS Gateway</th>
				<td>
					<?php echo SMS_HTTP_URL; ?><br />
					<?php echo (SMS_HTTP_POST_TEMPLATE && SMS_HTTP_RESPONSE_OK_REGEX) ? 'See details in config file' : '<b>Not fully configured.<b>'; ?>
                    <?php echo ifdef('SMS_HTTP_HEADER_TEMPLATE') ? '' : ' No additional headers configured.'; ?>
			</tr>
			<tr>
				<th>Max length for SMS messages</th>
				<td>
					<?php echo SMS_MAX_LENGTH; ?>
					<br /><small>160 characters is generally a one-part SMS. Longer messages will be sent in several parts and will cost more.</small>
				</td>
			</tr>
            <tr>
              <th>Default to saving sent SMS as note</th>
              <td>
                <?php echo ifdef('SMS_SAVE_TO_NOTE_BY_DEFAULT', 0); ?>
                <br />
              </td>
            </tr>
            <tr>
              <th>Default Subject for SMS saved as a note</th>
              <td>
                <?php echo ifdef('SMS_SAVE_TO_NOTE_SUBJECT', ''); ?>
                <br />
              </td>
            </tr>
			<tr>
				<th>Logging of SMS sending</th>
				<td>
					<?php echo SMS_SEND_LOGFILE ? 'Configured' : 'Not configured'; ?>
					<br /><small>This allows you to track how many SMSes each user is sending via Jethro.</small>
				</td>
			</tr>
			<tr>
				<td colspan="2"><h3>Locale settings</h3></td>
			</tr>

			<tr>
				<th>Timezone</th>
				<td><?php echo defined('TIMEZONE') ? TIMEZONE : '(Server default)'; ?></td>
			</tr>
			<tr>
				<th>Label for the Address 'suburb' field</th>
				<td><?php echo defined('ADDRESS_SUBURB_LABEL') ? ADDRESS_SUBURB_LABEL : 'Suburb'; ?></td>
			</tr>
			<tr>
				<th>Label for the address 'state' field</th>
				<td>
					<?php 
					if (!defined('ADDRESS_STATE_LABEL')) {
						echo 'State';
					} else if (ADDRESS_STATE_LABEL) {
						echo ADDRESS_STATE_LABEL;
					} else {
						echo '(State field disabled)';
					}
					echo '<br /><small>The state field can be hidden altogether by setting this to blank</small>';
					?>
				</td>
			</tr>
			<tr>
				<th>Options for the Address 'state' field</th>
				<td><?php echo ADDRESS_STATE_OPTIONS; ?>  (Default: <?php echo ADDRESS_STATE_DEFAULT; ?>)</td>
			</tr>
			<tr>
				<th>Label for the address 'postcode' field</th>
				<td><?php echo defined('ADDRESS_POSTCODE_LABEL') ? ADDRESS_POSTCODE_LABEL : 'Postcode'; ?></td>
			</tr>
			<tr>
				<th>Valid formats for the address 'postcode' field</th>
				<td><?php echo ADDRESS_POSTCODE_WIDTH.' characters matching the expression '.ADDRESS_POSTCODE_REGEX; ?></td>
			</tr>
			<tr>
				<th>Postcode lookup URL</th>
				<td>
					<?php echo POSTCODE_LOOKUP_URL; ?>
					<br /><small>When editing an address, the "look up <?php echo defined('ADDRESS_POSTCODE_LABEL') ? ADDRESS_POSTCODE_LABEL : 'postcode'; ?>" link uses this URL</small>
				</td>
			</tr>
			<tr>
				<th>Envelope Size</th>
				<td><?php echo ENVELOPE_WIDTH_MM.'mm x '.ENVELOPE_HEIGHT_MM.'mm'; ?></td>
			</tr>


			<tr>
				<th>Formats for the home phone field</th>
				<td>
					<?php echo nl2br(HOME_TEL_FORMATS); ?>
					<br /><small>When a phone number is displayed, it is laid out using these formats. When a phone number is entered, Jethro makes sure it has the right number of digits to match one of these formats.</small>
				</td>
			</tr>
			<tr>
				<th>Formats for the work phone field</th>
				<td><?php echo nl2br(HOME_TEL_FORMATS); ?></td>
			</tr>
			<tr>
				<th>Formats for the mobile phone field</th>
				<td><?php echo nl2br(MOBILE_TEL_FORMATS); ?></td>
			</tr>

			<tr>
				<td colspan="2"><h3>Appearance Settings</h3></td>
			</tr>
			<tr>
				<th>Custom Colours</th>
				<td><?php
					$customLessVars = defined('CUSTOM_LESS_VARS') ? constant('CUSTOM_LESS_VARS') : NULL;
					$customCSSFile = 'jethro-'.JETHRO_VERSION.'-custom.css';
					if (file_exists(JETHRO_ROOT.'/'.$customCSSFile)) {
						?>
						Jethro is using the custom CSS file <?php echo $customCSSFile; ?><br />
						<?php
						if ($customLessVars) {
							?>
							The following <a href="http://lesscss.org/">LESS</a> variables are set in the conf file:
							<?php bam($customLessVars); ?>
							To adjust these colours, first delete <?php echo $customCSSFile; ?> from your jethro directory.  Then edit your conf.php file, and when happy with the changes, come back to this page to re-save the custom CSS file.
							<?php
						}
					} else if ($customLessVars) {
						?>
						The following <a href="http://lesscss.org/">LESS</a> variables are set in the conf file:
						<?php bam($customLessVars); ?>
						<b>These changes have not been saved to a custom CSS file,
							so Jethro is building the CSS on every page load.</b>
						For production environments you should
						<span class="clickable" onclick="TBLib.downloadText($(document.head).find('style[id^=less]').get(0).innerHTML, '<?php echo $customCSSFile; ?>')">download the compiled CSS</span>
						and save it in your main Jethro folder.
						<?php
					}
					?>
				</td>
			</tr>

		</table>
		<?php
	}
}
