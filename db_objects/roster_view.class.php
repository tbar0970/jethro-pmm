<?php
include_once 'include/db_object.class.php';
include_once 'include/bible_ref.class.php';
include_once 'db_objects/service.class.php';
class roster_view extends db_object
{
	var $_members = Array();
	var $_members_to_set = Array();

	var $_load_permission_level = NULL;
	var $_save_permission_level = PERM_MANAGEROSTERS;

	/**
	* Get all the congregations related to this view
	*/
	function getCongregations()
	{
		$res = Array();
		foreach ($this->_members as $member) {
			if ($member['congregationid']) $res[] = $member['congregationid'];
		}
		$res = array_unique($res);
		if (empty($res)) {
			// this view must be only non-congregational roles, so we will use all congregations
			$congs = $GLOBALS['system']->getDBObjectData('congregation', Array('!meeting_time' => ''));
			$res = array_keys($congs);
		}
		return $res;
	}


	function load($id)
	{
		$res = parent::load($id);
		
		$sql = '(
					SELECT rvrm.order_num as order_num, rr.id as role_id, rr.title as role_title, NULL as service_field, rr.congregationid as congregationid, rrc.name as congregation_name, rr.volunteer_group as volunteer_group
					FROM
					roster_view_role_membership rvrm
						JOIN (
							roster_role rr
							LEFT JOIN congregation rrc ON rr.congregationid = rrc.id
						) ON rvrm.roster_role_id = rr.id
					WHERE rvrm.roster_view_id = '.(int)$this->id.'
				)
				UNION
				(
					SELECT rvdi.order_num as order_num, NULL as role_id, NULL as role_title, rvdi.service_field as service_field, rvdi.congregationid as congregationid, dic.name as congregation_name, NULL as volunteer_group
					FROM
					roster_view_service_field rvdi
						JOIN congregation dic ON rvdi.congregationid = dic.id
					WHERE rvdi.roster_view_id = '.(int)$this->id.'
				)

				ORDER BY order_num';
		$this->_members = $GLOBALS['db']->queryAll($sql, null, null, true);
		check_db_result($this->_members);
	}

	function getMembers()
	{
		return $this->_members;
	}

	function getRoleIds()
	{
		$res = Array();
		foreach ($this->_members as $member) {
			if ($member['role_id']) $res[] = $member['role_id'];
		}
		return $res;
	}

	function _getFields()
	{
		
		$fields = Array(
			'name'			=> Array(
									'type'		=> 'text',
									'width'		=> 80,
									'maxlength'	=> 128,
									'initial_cap'	=> TRUE,
									'allow_empty'	=> FALSE,
								   ),
			'is_public'		=> Array(
									'type'		=> 'select',
									'options'	=> Array(0 => 'No', 1 => 'Yes'),
									'default'	=> 0,
									'note' => 'Public roster views are available to non-logged-in users via the <a href="'.BASE_URL.'/public/">public site</a>',
								),
		);
		return $fields;
	}

	function printForm($prefix='', $fields=NULL)
	{
		$this->fields['members'] = Array(); // fake field for interface purposes
		parent::printForm($prefix, $fields);
		unset($this->fields['members']);
	}

	function printFieldInterface($name, $prefix)
	{
		switch ($name) {
			case 'members':
				$all_roles = $GLOBALS['system']->getDbObjectData('roster_role', Array(), 'OR', 'roster_role.congregationid, roster_role.title');
				?>
				<script>
					$(window).load(function() {
						$('select.roster-view-member-type-chooser').change(function() {
							var chooser = this;
							$(this).siblings('select').each(function() {
								var wanted = this.className == 'view-members-'+$(chooser).val();
								this.style.display = wanted ? '' : 'none';
								this.disabled = wanted ? false : true;
							});

						}).change();
				});
				</script>
				<table class="no-padding middle expandable">
				<?php
				$members_to_print = $this->_members + Array(-1 => Array(
					'role_id' => 0,
					'role_title' => '',
					'service_field' => NULL,
					'congregationid' => NULL,
				));
				foreach ($members_to_print as $order_num => $member_details) {

					$role_selected_html = is_null($member_details['role_id']) ? '' : 'selected="selected"';
					$field_selected_html = is_null($member_details['service_field']) ? '' : 'selected="selected"';

					$role_enabled_html = is_null($member_details['role_id']) ? 'disabled="disabled" style="display: none"' : '';
					$field_enabled_html = is_null($member_details['service_field']) ? 'disabled="disabled" style="display: none"' : '';

					?>
					<tr>
						<td>
							<img src="<?php echo BASE_URL; ?>/resources/img/expand_up_down_green_small.png" class="icon insert-row-below" style="position: relative; top: 2ex" title="Create a blank entry here" />
						</td>
						<td class="nowrap">
							<select class="roster-view-member-type-chooser">
								<option <?php echo $role_selected_html; ?> value="role">Roster Role</option>
								<option <?php echo $field_selected_html; ?> value="service_field">Service Field</option>
							</select>

							<select name="members[]" class="view-members-role" <?php echo $role_enabled_html; ?>>
							<option value=""></option>
							<?php
							$last_cong = '';
							foreach ($all_roles as $id => $details) {
								if ($details['congregation_name'] != $last_cong) {
									if ($last_cong) echo '</optgroup>';
									?>
									<optgroup label="<?php echo htmlentities($details['congregation_name']); ?>">
									<?php
									$last_cong = $details['congregation_name'];
								}
								?>
								<option value="role-<?php echo $id; ?>"<?php if ($id == $member_details['role_id']) echo 'selected="selected" '; ?>><?php echo htmlentities($details['congregation_name'].' '.$details['title']); ?></option>
								<?php
							}
							?>
							</optgroup>
							</select>

							<select name="members[]" class="view-members-service_field" <?php echo $field_enabled_html; ?>>
							<option value=""></option>
							<?php
							foreach ($GLOBALS['system']->getDBObjectData('congregation', Array('!meeting_time' => ''), 'AND', 'meeting_time') as $congid => $cong_details) {
								?>
								<optgroup label="<?php echo htmlentities($cong_details['name']); ?>">
								<?php
								foreach (Service::getDisplayFields() as $k => $v) {
									$selected = (($member_details['congregationid'] == $congid) && ($member_details['service_field'] == $k)) ? 'selected="selected"' : '';
									?>
									<option value="service-<?php echo $congid.'-'.$k; ?>" <?php echo $selected; ?>><?php echo htmlentities($cong_details['name'].' '.$v); ?></option>
									<?php
								}
								?>
								</optgroup>
								<?php
							}
							?>
							</select>
						</td>
						<td class="nowrap">

							<img src="<?php echo BASE_URL; ?>/resources/img/arrow_up_thin_black.png" class="icon move-row-up" title="Move this role up" />
							<img src="<?php echo BASE_URL; ?>/resources/img/arrow_down_thin_black.png" class="icon move-row-down" title="Move this role down" />
							<img src="<?php echo BASE_URL; ?>/resources/img/cross_red.png" class="icon delete-row" title="Delete this role from the list" />
						</td>
					</tr>
					<?php
				}
				?>
				</table>
				<br />
				<?php
				break;
			default:
				return parent::printFieldInterface($name, $prefix);
		}
	}

	function processForm($prefix='', $fields=NULL)
	{
		$this->fields['members'] = Array();
		parent::processForm($prefix, $fields);
		unset($this->fields['members']);
	}

	function processFieldInterface($name, $prefix)
	{
		switch ($name) {
			case 'members':
				$this->_members = Array();
				$this->_members_to_set = $_POST[$prefix.$name];
				break;
			default:
				return parent::processFieldInterface($name, $prefix);
		}
	}

	function save()
	{
		$res = parent::save();
		$this->_saveMemberRoles();
		return $res;
	}

	function create()
	{
		$res = parent::create();
		$this->_saveMemberRoles();
		return $res;
	}

	function _saveMemberRoles()
	{
		if (!empty($this->_members_to_set)) {
			$sql = 'DELETE FROM roster_view_role_membership WHERE roster_view_id = '.(int)$this->id;
			$q = $GLOBALS['db']->query($sql);
			check_db_result($q);
			$sql = 'DELETE FROM roster_view_service_field WHERE roster_view_id = '.(int)$this->id;
			$q = $GLOBALS['db']->query($sql);
			check_db_result($q);

			$role_inserts = Array();
			$field_inserts = Array();
	
			foreach ($this->_members_to_set as $order => $detail) {
				if (empty($detail)) continue;
				$bits = explode('-', $detail);
				if ($bits[0] == 'role') {
					$role_inserts[$bits[1]] = '('.(int)$this->id.', '.(int)$bits[1].', '.(int)$order.')';
				} else {
					$field_inserts[$bits[1].'-'.$bits[2]] = '('.(int)$this->id.', '.(int)$bits[1].', '.$GLOBALS['db']->quote($bits[2]).', '.(int)$order.')';
				}
			}

			if (!empty($role_inserts)) {
				$sql = 'INSERT INTO roster_view_role_membership (roster_view_id, roster_role_id, order_num) VALUES ';
				$sql .= implode(', ', $role_inserts);
				$q = $GLOBALS['db']->query($sql);
				check_db_result($q);
			}

			if (!empty($field_inserts)) {
				$sql = 'INSERT INTO roster_view_service_field (roster_view_id, congregationid, service_field, order_num) VALUES ';
				$sql .= implode(', ', $field_inserts);
				$q = $GLOBALS['db']->query($sql);
				check_db_result($q);
			}

			$this->_members_to_set = Array();
			$this->load($this->id);
		}
	}

	function toString()
	{
		return $this->getValue('name');
	}


	function getInstancesQueryComps($params, $logic, $order)
	{
		$res = parent::getInstancesQueryComps($params, $logic, $order);
		$res['select'][] = 'members';
		$res['from'] = '(SELECT roster_view_id, GROUP_CONCAT(member_name ORDER BY order_num SEPARATOR ", ") as members
							FROM
							(
								(
									SELECT rvrm.roster_view_id as roster_view_id, CONCAT(COALESCE(rrc.name, ""), " ", rr.title) as member_name, rvrm.order_num as order_num
									FROM
									roster_view_role_membership rvrm
										JOIN (
											roster_role rr
											LEFT JOIN congregation rrc ON rr.congregationid = rrc.id
										) ON rvrm.roster_role_id = rr.id
								)
								UNION
								(
									SELECT rvdi.roster_view_id as roster_view_id, CONCAT(dic.name, " ", rvdi.service_field) as member_name, rvdi.order_num as order_num
									FROM
									roster_view_service_field rvdi
										JOIN congregation dic ON rvdi.congregationid = dic.id
								)
								ORDER BY order_num
							) res
							GROUP BY roster_view_id
						) m
						RIGHT OUTER JOIN roster_view on m.roster_view_id = roster_view.id';
		return $res;
	}


	function getAssignments($start_date, $end_date)
	{
		$roleids = $this->getRoleIds();
		if (empty($roleids)) return Array();
		if (is_null($start_date)) $start_date = date('Y-m-d');
		if (is_null($end_date)) $end_date = date('Y-m-d', strtotime('+1 year'));
		$sql = 'SELECT roster_role_id, assignment_date, rra.personid,
				CONCAT(assignee.first_name, " ", assignee.last_name) as assignee,
				assignee.email as email,
				CONCAT(assigner.first_name, " ", assigner.last_name) as assigner, 
				rra.assignedon
				FROM roster_role_assignment rra
				JOIN person assignee ON rra.personid = assignee.id
				LEFT JOIN person assigner ON rra.assigner = assigner.id
				WHERE roster_role_id IN ('.implode(', ', array_map(Array($GLOBALS['db'], 'quote'), $roleids)).')
				AND assignment_date BETWEEN '.$GLOBALS['db']->quote($start_date).' AND '.$GLOBALS['db']->quote($end_date);
		$rows = $GLOBALS['db']->queryAll($sql);
		check_db_result($rows);
		$res = Array();
		foreach ($rows as $row) {
			$res[$row['assignment_date']][$row['roster_role_id']][$row['personid']] = Array(
				'name' => $row['assignee'],
				'email' => $row['email'],
				'assigner' => $row['assigner'],
				'assignedon' => $row['assignedon']
			);
		}
		return $res;
	}

	function getAssignees($start_date, $end_date)
	{
		$roleids = $this->getRoleIds();
		if (empty($roleids)) return Array();
		if (is_null($start_date)) $start_date = date('Y-m-d');
		if (is_null($end_date)) $end_date = date('Y-m-d', strtotime('+1 year'));
		$sql = 'SELECT person.*
				FROM roster_role_assignment rr JOIN person ON rr.personid = person.id
				WHERE roster_role_id IN ('.implode(', ', array_map(Array($GLOBALS['db'], 'quote'), $roleids)).')
				AND assignment_date BETWEEN '.$GLOBALS['db']->quote($start_date).' AND '.$GLOBALS['db']->quote($end_date);
		$rows = $GLOBALS['db']->queryAll($sql);
		check_db_result($rows);
		return $rows;
	}


	function printView($start_date=NULL, $end_date=NULL, $editing=FALSE, $public=FALSE)
	{
		if (empty($this->_members)) return;
		if (!$editing && !$public) {
			$my_email = $GLOBALS['user_system']->getCurrentUser('email');
		}
		$GLOBALS['system']->includeDBClass('service');
		$dummy_service = new Service();

		if (is_null($start_date)) $start_date = date('Y-m-d');
		$service_params = Array('congregationid' => $this->getCongregations(), '>date' => date('Y-m-d', strtotime($start_date.' -1 day')));
		if (!is_null($end_date)) $service_params['<date'] = date('Y-m-d', strtotime($end_date.' +1 day'));
		$services = $GLOBALS['system']->getDBObjectData('service', $service_params, 'AND', 'date');

		$to_print = Array();
		foreach ($services as $id => $service_details) {
			$to_print[$service_details['date']]['service'][$service_details['congregationid']] = $service_details;
			$to_print[$service_details['date']]['assignments'] = Array();
		}
		foreach ($this->getAssignments($start_date, $end_date) as $date => $date_assignments) {
			$to_print[$date]['assignments'] = $date_assignments;
		}
		ksort($to_print);
		$role_objects = Array();
		$this_sunday = date('Y-m-d', strtotime('Sunday'));

		if (empty($to_print)) {
			?>
			<div class="alert alert-error">There are no services during the date range specified.  Please try a different date range, or create some services using the 'Edit service program' page.</div>
			<?php
			return;
		}

		if ($editing) {
			$show_lock_fail_msg = false;
			$show_group_denied_msg = false;
			foreach ($this->_members as $id => &$details) {
				if (!empty($details['role_id'])) {
					$role = $GLOBALS['system']->getDBObject('roster_role', $details['role_id']);
					
					if (!($role->canAcquireLock('assignments') && $role->acquireLock('assignments'))) {
						$details['readonly'] = true;
						$show_lock_fail_msg = true;
					}
					if (!$role->canEditAssignments()) {
						$details['readonly'] = true;
						$show_group_denied_msg = true;
					}
				}
			}
			if ($show_lock_fail_msg) {
				print_message("Some of the roles in this roster are currently being edited by another user.  To edit assignments for these roles, wait until the other user finishes then try again.", 'failure');
			}
			if ($show_group_denied_msg) {
				print_message("There are some roles in this roster which you are not able to edit because they refer to a volunteer group you do not have access to.");
			}
			?>
			<form method="post" class="warn-unsaved bubble-option-props">
			<script>
				$(document).ready(function() {

					setTimeout('showLockExpiryWarning()', <?php echo (strtotime('+'.LOCK_LENGTH, 0)-60)*1000; ?>);
					setTimeout('showLockExpiredWarning()', <?php echo (strtotime('+'.LOCK_LENGTH, 0))*1000; ?>);

					$('table.roster select').keypress(function() { handleRosterChange(this); }).change(function() { handleRosterChange(this); });
					$('table.roster input.person-search-single, table.roster input.person-search-multiple').each(function() {
						this.onchange = function() { handleRosterChange(this); };
					});
					$('table.roster > tbody > tr').each(function() { updateClashesForRow($(this)); });
				});
				function handleRosterChange(inputField)
				{
					var row = null;
					if ($(inputField).hasClass('person-search-single') || $(inputField).hasClass('person-search-multiple')) {
						row = $(inputField).parents('tr:first');
					} else if (inputField.tagName == 'SELECT' || inputField.type == 'hidden') {
						var expandableParent = $(inputField).parents('table.expandable');
						if (expandableParent.length) {
							var row = $(inputField).parents('table:first').parents('tr:first');
						} else {
							var row = $(inputField).parents('tr:first');
						}
					}
					if (row) {
						updateClashesForRow(row);
					}
				}

				function updateClashesForRow(row)
				{
					var uses = new Object();
					// Deal with the single person choosers and select boxes first
					var sameRowInputs = row.find('input.person-search-single, select');
					sameRowInputs.removeClass('clash');
					sameRowInputs.each(function() {
						var thisElt = this;
						var thisVal = 0;
						if (this.className == 'person-search-single') {
							var hiddenInput = document.getElementsByName(this.id.substr(0, this.id.length-6))[0];
							thisVal = hiddenInput.value;
						} else if (this.tagName == 'SELECT') {
							thisVal = this.value;
						}
						if (thisVal != 0) {
							if (!uses[thisVal]) {
								uses[thisVal] = new Array();
							}
							uses[thisVal].push(thisElt);
						}
					});
					// Now add the multi person choosers
					row.find('ul.multi-person-finder li').removeClass('clash').each(function() {
						var thisVal = $(this).find('input')[0].value;
						if (thisVal != 0) {
							if (!uses[thisVal]) {
								uses[thisVal] = new Array();
							}
							uses[thisVal].push(this);
						}
					});
					for (i in uses) {
						if (uses[i].length > 1) {
							for (j in uses[i]) {
								if (typeof uses[i][j] == 'function') continue;
								$(uses[i][j]).addClass('clash');
							}
						}
					}
				}
			</script>
			<?php
		}
		?>
		<table class="table roster" border="1" cellspacing="0" cellpadding="1">

			<?php $this->_printTableHeader($editing, $public); ?>

			<tbody>
			<?php
			foreach ($to_print as $date => $ddetail) {
				if ($public && empty($ddetail['assignments'])) continue;
				$class_clause = ($date == $this_sunday) ? 'class="tblib-hover"' : '';
				?>
				<tr <?php echo $class_clause; ?>>
					<td class="nowrap">
						<?php 
						echo '<strong>'.str_replace(' ', '&nbsp;', date('j M y', strtotime($date))).'</strong>'; 
						if (!$editing && !$public) {
							$emails = Array();
							foreach ($ddetail['assignments'] as $roleid => $assignees) {
								foreach ($assignees as $pid => $pdetails) {
									if (!empty($pdetails['email']) && $pdetails['email'] != $my_email) {
										$emails[] = $pdetails['email'];
									}
								}
							}
							$emails = array_unique($emails);
							if (!empty($emails)) {
								?>
								<p class="smallprint no-print">
									<a href="mailto:<?php echo $my_email; ?>?bcc=<?php echo implode(',', $emails); ?>&subject=<?php echo (date('jS F', strtotime($date))); ?>">Email All</a>
									<?php
									if (defined('SMS_HTTP_URL') && constant('SMS_HTTP_URL') && $GLOBALS['user_system']->havePerm(PERM_SENDSMS)) {
										?>
										| <span class="clickable" onclick="$(this).parent().next('form').toggle(); $(this).parents('tr:first').addClass('tblib-hover')">SMS All</span>
										<?php
									}
									?>
								</p>
								<?php
								if (defined('SMS_HTTP_URL') && constant('SMS_HTTP_URL') && $GLOBALS['user_system']->havePerm(PERM_SENDSMS)) {
									$url = build_url(Array(
										'view' => '_send_sms_http',
										'roster_view' => $this->id,
										'start_date' => $date,
										'end_date' => $date
									));
									?>
									<form method="post" action="<?php echo $url; ?>" style="position: absolute; display: none">
										<div class="standard" style="border-width: 2px; border-radius: 8px">
										<h3>Send SMS</h3>
										<textarea name="message" rows="5" cols="30" maxlength="<?php echo SMS_MAX_LENGTH; ?>"></textarea>
										<br />
										<input type="submit" value="Send" />
										<input type="button" onclick="$(this).parents('form').toggle(); $(this).parents('tr:first').removeClass('tblib-hover')" value="Cancel" />
										</div>
									</form>
									<?php
								}
							}
						}
						?>
					</td>
				<?php
				$last_congid = NULL;
				foreach ($this->_members as $id => $mdetail) {
					$td_class = '';
					if ($mdetail['congregationid'] != $last_congid) {
						$td_class = 'thick-left-border';
						$last_congid = $mdetail['congregationid'];
					}
					?>
					<td class="<?php echo $td_class; ?>">
					<?php
					if ($mdetail['role_id']) {
						if ($editing && empty($mdetail['readonly'])) {
							$currentval = Array();
							foreach (array_get($ddetail['assignments'], $mdetail['role_id'], Array()) as $pid => $pdetails) {
								$currentval[$pid] = $pdetails['name'];
							}
							if (empty($role_objects[$mdetail['role_id']])) {
								$role_objects[$mdetail['role_id']] =& $GLOBALS['system']->getDBObject('roster_role', $mdetail['role_id']);
							}
							if (empty($role_objects[$mdetail['role_id']])) {
								// must've been a problem
								continue;
							}
							$role_objects[$mdetail['role_id']]->printChooser($date, $currentval);
						} else {
							$names = Array();
							foreach (array_get($ddetail['assignments'], $mdetail['role_id'], Array()) as $personid => $vs) {
								if (!$public) {
									$n = '<a href="'.BASE_URL.'?view=persons&personid='.$personid.'" title="Assigned by '.htmlentities($vs['assigner']).' on '.format_datetime($vs['assignedon']).'">'.nbsp(htmlentities($vs['name'])).'</a>';
									if (empty($vs['email'])) $n .= '&nbsp;<img src="'.BASE_URL.'resources/img/no_email.png" style="display:inline" title="No Email Address" />';
									$names[] = $n;
								} else {
									$names[] = nbsp($vs['name']);
								}
							}
							echo implode("<br />", $names);
						}
					} else {
						if (!empty($ddetail['service'][$mdetail['congregationid']])) {
							if ($public) unset($ddetail['service'][$mdetail['congregationid']]['notes']); // no notes in public view
							$dummy_service->populate(0, $ddetail['service'][$mdetail['congregationid']]);
							$dummy_service->printFieldvalue($mdetail['service_field']);
						}
					}
					?>
					</td>
					<?php
				}
				if (!$public && (count($this->_members) > REPEAT_DATE_THRESHOLD)) {
					?>
					<td class="nowrap thick-left-border">
						<strong><?php echo str_replace(' ', '&nbsp;', date('j M y', strtotime($date))); ?></strong>
					</td>
					<?php
				}
				?>
				</tr>
				<?php
			}
		?>
		</tbody>

		<?php
		if (!$public && (count($to_print) > 6)) $this->_printTableFooter($editing, $public); 
		?>

		</table>

		<?php
		if ($editing) {
			?>
			<input type="submit" class="btn" value="Save" accesskey="s" />
			</form>
			<?php
		}
	}

	function _printTableHeader($editing, $public)
	{
		?>
		<thead>
			<tr>
				<th rowspan="2">Date</th>
				<?php 
				$this->_printCongHeaders();
				if (!$public && (count($this->_members) > REPEAT_DATE_THRESHOLD)) {
					?>
					<th rowspan="2" class="thick-left-border">Date</th>
					<?php
				}
				?>
			</tr>
			<tr>
				<?php $this->_printRoleHeaders($editing, $public); ?>
			</tr>
		</thead>
		<?php
	}

	function _printTableFooter($editing, $public)
	{
		?>
		<tfoot>
			<tr>
				<th rowspan="2">Date</th>
				<?php $this->_printRoleHeaders($editing, $public);
				if (!$public && (count($this->_members) > REPEAT_DATE_THRESHOLD)) {
					?>
					<th rowspan="2">Date</th>
					<?php
				}
				?>
			</tr>
			<tr>
				<?php $this->_printCongHeaders(); ?>
			</tr>
		</tfoot>
		<?php
	}

	function _printCongHeaders()
	{
		// print congregation headings
		$current_cong_name = FALSE;
		$colspan = 1;
		foreach ($this->_members as $id => $details) {
			if ($details['congregation_name'] !== $current_cong_name) {
				if ($current_cong_name !== FALSE) {
					echo $colspan.'">'.($current_cong_name ? $current_cong_name : '&nbsp;').'</th>';
				}
				$colspan = 1;
				echo '<th class="thick-left-border" colspan="';
				$current_cong_name = $details['congregation_name'];
			} else {
				$colspan++;
			}
		}
		echo $colspan.'">'.$current_cong_name.'</th>';
	}

	function _printRoleHeaders($editing, $public)
	{
		// print role/field headings
		$last_congid = NULL;
		foreach ($this->_members as $id => $details) {
			$th_class = '';
			if ($details['congregationid'] != $last_congid) {
				$th_class = 'thick-left-border';
				$last_congid = $details['congregationid'];
			}
			?>
			<th class="<?php echo $th_class; ?>">
				<?php
				if ($details['role_id']) {
					if ($editing) {
						if (!empty($details['volunteer_group'])) {
							echo '<a title="Click to edit volunteer group" href="'.BASE_URL.'?view=groups&groupid='.(int)$details['volunteer_group'].'">'.htmlentities($details['role_title']).'</a>';
						} else {
							echo htmlentities($details['role_title']);
						}
						if (!empty($details['readonly'])) echo '<br /><b>[LOCKED]</b>';
					} else {
						echo '<a class="med-popup" title="Click for role description" href="'.BASE_URL.'/public/?view=display_role_description&role='.$details['role_id'].'">'.htmlentities($details['role_title']).'</a>';
					}
				} else {
					$dummy_service = new Service();
					echo htmlentities($dummy_service->getFieldLabel($details['service_field'], true));
				}
				?>
			</th>
			<?php
		}
	}

	function populate($id, $values)
	{
		$this->_members = Array();
		$this->_members_to_set = Array();
		return parent::populate($id, $values);
	}


	function processAllocations($start_date, $end_date)
	{
		if (!isset($_POST['assignees'])) return;

		// Exclude roles that we couldn't get the lock for
		$roles = $this->getRoleIds();
		foreach ($roles as $i => $roleid) {
			$role = $GLOBALS['system']->getDBObject('roster_role', $roleid);
			if (!$role->haveLock('assignments')) {
				unset($roles[$i]);
			}
		}

		if (empty($roles)) {
			trigger_error('Couldn\'t process allocations - do not have the lock on any role');
			return;
		}

		$to_add = Array();
		$to_delete = $this->getAssignments($start_date, $end_date);
		foreach ($roles as $roleid) {
			if (!empty($_POST['assignees'][$roleid])) {
				foreach ($_POST['assignees'][$roleid] as $date => $assignee) {
					if (!is_array($assignee)) $assignee = Array($assignee);
					foreach ($assignee as $new_personid) {
						if (empty($new_personid)) continue;
						if (isset($to_delete[$date][$roleid][$new_personid])) {
							// unchanged allocation - leave it as is
							unset($to_delete[$date][$roleid][$new_personid]);
						} else {
							// new allocation
							$to_add[] = '('.(int)$roleid.', '.$GLOBALS['db']->quote($date).', '.(int)$new_personid.', '.(int)$GLOBALS['user_system']->getCurrentUser('id').')';
						}
					}
				}
			}
		}
		$del_clauses = Array();
		foreach ($to_delete as $date => $date_allocs) {
			foreach ($date_allocs as $roleid => $role_allocs) {
				if (in_array($roleid, $roles)) { // don't delete any allocations for read-only roles!!
					foreach ($role_allocs as $personid => $person_details) {
						$del_clauses[] = '(roster_role_id = '.(int)$roleid.' AND assignment_date = '.$GLOBALS['db']->quote($date).' AND personid = '.(int)$personid.')';
					}
				}
			}
	
		}
		$GLOBALS['system']->doTransaction('BEGIN');
		if (!empty($del_clauses)) {
			$sql = 'DELETE FROM roster_role_assignment WHERE ('.implode(' OR ', $del_clauses).')';
			$res = $GLOBALS['db']->query($sql);
			check_db_result($res);
		}
		if (!empty($to_add)) {
			$to_add = array_unique($to_add);
			$sql = 'INSERT INTO roster_role_assignment (roster_role_id, assignment_date, personid, assigner) VALUES '.implode(",\n", $to_add);
			$res = $GLOBALS['db']->query($sql);
			check_db_result($res);
		}
		foreach ($roles as $i => $roleid) {
			$role = $GLOBALS['system']->getDBObject('roster_role', $roleid);
			$role->releaseLock('assignments');
		}
		$GLOBALS['system']->doTransaction('COMMIT');
		add_message("Role allocations saved");
		redirect('rosters__display_roster_assignments');
	}
}
?>
