<?php
include_once 'include/db_object.class.php';
include_once 'include/bible_ref.class.php';
include_once 'db_objects/service.class.php';
class roster_view extends db_object
{
	private $_members = Array();
	private $_members_to_set = Array();

	protected $_load_permission_level = NULL;
	protected $_save_permission_level = PERM_MANAGEROSTERS;

	const hiddenPersonLabel = '(Hidden)';

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

		// Enforce visibility
		switch ($this->getValue('visibility')) {
			case '':
				if (!$GLOBALS['user_system']->getCurrentUser('id') && (PHP_SAPI != 'cli')) {
					header($_SERVER["SERVER_PROTOCOL"]." 401 Not Authorised");
					print_message("Roster view #{$this->id} is only available to logged in operators", 'error');
					exit;
				}
				break;
			case 'members':
				// Make sure either a user or a member is logged in
				 if (!$GLOBALS['user_system']->getCurrentPerson('id') && (PHP_SAPI != 'cli')) {
					header($_SERVER["SERVER_PROTOCOL"]." 401 Not Authorised");
					print_message("Roster view #{$this->id} is only available to logged in members", 'error');
					exit;
				}
				break;
		}

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

	protected static function _getFields()
	{

		$fields = Array(
			'name'			=> Array(
									'type'		=> 'text',
									'width'		=> 80,
									'maxlength'	=> 128,
									'initial_cap'	=> TRUE,
									'allow_empty'	=> FALSE,
								   ),
			'visibility'		=> Array(
									'type'		=> 'select',
									'options'	=> Array('' => 'Private', 'members' => 'Show in members area', 'public' => 'Show in public area'),
									'default'	=> 0,
									'note' => 'Whether this roster view is visible in the <a href="'.BASE_URL.'/public/">public area</a> and/or to church members via the <a href="'.BASE_URL.'members/">members area</a>',
								),
			'show_on_run_sheet' => Array(
									'type'	=> 'select',
									'options' => Array(0 => 'No', 1 => 'Yes, including empty roles', 2 => 'Yes, without empty roles'),
									'default' => 0,
									'note' => 'Whether to display this view\'s allocations at the top of applicable service run sheets',
									),
		);
		return $fields;
	}

	function printForm($prefix='', $fields=NULL)
	{
		$this->fields['members'] = Array(); // fake field for interface purposes
		if ($this->id) {
			$url = BASE_URL.'public/?view=display_roster&roster_view='.$this->id;
			if (defined('PUBLIC_ROSTER_SECRET') && strlen(PUBLIC_ROSTER_SECRET)) {
				$url .= '&secret='.PUBLIC_ROSTER_SECRET;
			}
		}
		parent::printForm($prefix, $fields);
		unset($this->fields['members']);
	}

	function printFieldInterface($name, $prefix='')
	{
		switch ($name) {
			case 'members':
				$all_roles = $GLOBALS['system']->getDbObjectData('roster_role', Array('active' => 1), 'OR', 'roster_role.congregationid, roster_role.title');
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
							<div class="insert-row-below" title="Create a blank entry here" />
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
									<optgroup label="<?php echo ents($details['congregation_name']); ?>">
									<?php
									$last_cong = $details['congregation_name'];
								}
								?>
								<option value="role-<?php echo $id; ?>"<?php if ($id == $member_details['role_id']) echo 'selected="selected" '; ?>><?php echo ents($details['congregation_name'].' '.$details['title']); ?></option>
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
								<optgroup label="<?php echo ents($cong_details['name']); ?>">
								<?php
								foreach (Service::getDisplayFields() as $k => $v) {
									$selected = (($member_details['congregationid'] == $congid) && ($member_details['service_field'] == $k)) ? 'selected="selected"' : '';
									?>
									<option value="service-<?php echo $congid.'-'.$k; ?>" <?php echo $selected; ?>><?php echo ents($cong_details['name'].' '.$v); ?></option>
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

	function processFieldInterface($name, $prefix='')
	{
		switch ($name) {
			case 'members':
				$this->_members = Array();
				$this->_members_to_set = array_get($_POST, $prefix.$name, Array());
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
			$sql = 'DELETE FROM roster_view_service_field WHERE roster_view_id = '.(int)$this->id;
			$q = $GLOBALS['db']->query($sql);

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
			}

			if (!empty($field_inserts)) {
				$sql = 'INSERT INTO roster_view_service_field (roster_view_id, congregationid, service_field, order_num) VALUES ';
				$sql .= implode(', ', $field_inserts);
				$q = $GLOBALS['db']->query($sql);
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
									SELECT rvdi.roster_view_id as roster_view_id, CONCAT(dic.name, " ", COALESCE(scc.category_name, rvdi.service_field)) as member_name, rvdi.order_num as order_num
									FROM
									roster_view_service_field rvdi
										JOIN congregation dic ON rvdi.congregationid = dic.id
										LEFT JOIN service_component_category scc ON ((rvdi.service_field LIKE "comps_%") AND (scc.id = SUBSTRING(rvdi.service_field, 7)))
								)
								ORDER BY order_num
							) res
							GROUP BY roster_view_id
						) m
						RIGHT OUTER JOIN roster_view on m.roster_view_id = roster_view.id';
		return $res;
	}


	private function getAssignments($start_date, $end_date)
	{
		$roleids = $this->getRoleIds();
		if (empty($roleids)) return Array();
		if (empty($start_date)) $start_date = date('Y-m-d');
		if (empty($end_date)) $end_date = date('Y-m-d', strtotime('+1 year'));

		// Normally any assignments involving a person the current user cannot see
		// will be shown as "Hidden".  BUT if this roster is public, we might as well
		// show all names all the time.  But even if it's public we need to know
		// which assignments involve 'hidden' persons so we treat them as read-only.
		$visiblePersonTable = ($this->getValue('visibility') == 'public') ? '_person' : 'person';

		$sql = 'SELECT roster_role_id, assignment_date, `rank`, rra.personid,
				IFNULL(CONCAT(publicassignee.first_name, " ", publicassignee.last_name), "'.self::hiddenPersonLabel.'") as assignee,
				IF(privateassignee.id IS NULL, 1, 0) as assigneehidden,
				privateassignee.email as email,
				privateassignee.mobile_tel as mobile,
				CONCAT(assigner.first_name, " ", assigner.last_name) as assigner,
				rra.assignedon,
				pa.id as absenceid,
				pa.comment as absence_comment
				FROM roster_role_assignment rra
				LEFT JOIN person privateassignee ON rra.personid = privateassignee.id
				LEFT JOIN '.$visiblePersonTable.' publicassignee ON rra.personid = publicassignee.id
				LEFT JOIN person assigner ON rra.assigner = assigner.id
				LEFT JOIN planned_absence pa 
					ON ((pa.personid = rra.personid)
						AND (rra.assignment_date BETWEEN pa.start_date and pa.end_date))
				WHERE roster_role_id IN ('.implode(', ', array_map(Array($GLOBALS['db'], 'quote'), $roleids)).')
				AND assignment_date BETWEEN '.$GLOBALS['db']->quote($start_date).' AND '.$GLOBALS['db']->quote($end_date).'
				ORDER BY assignment_date, roster_role_id, `rank`, privateassignee.last_name, privateassignee.first_name';
		$rows = $GLOBALS['db']->queryAll($sql);
		$res = Array();
		foreach ($rows as $row) {
			$res[$row['assignment_date']][$row['roster_role_id']][$row['rank']] = Array(
				'personid' => $row['personid'],
				'name' => $row['assignee'],
				'email' => $row['email'],
				'mobile' => $row['mobile'],
				'assigner' => $row['assigner'],
				'assignedon' => $row['assignedon'],
				'assigneehidden' => $row['assigneehidden'],
				'absence_comment' => $row['absence_comment'],
				'absenceid' => $row['absenceid'],
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
		$sql = 'SELECT person.id, person.first_name, person.last_name, person.email, person.mobile_tel
				FROM roster_role_assignment rr JOIN person ON rr.personid = person.id
				WHERE roster_role_id IN ('.implode(', ', array_map(Array($GLOBALS['db'], 'quote'), $roleids)).')
				AND assignment_date BETWEEN '.$GLOBALS['db']->quote($start_date).' AND '.$GLOBALS['db']->quote($end_date);
		$rows = $GLOBALS['db']->queryAll($sql, null, null, TRUE);
		return $rows;
	}

	public function printCSV($start_date=NULL, $end_date=NULL, $return=FALSE)
	{
		$GLOBALS['system']->includeDBClass('service');
		$dummy_service = new Service();

		if (empty($start_date)) $start_date = date('Y-m-d');
		$service_params = Array('congregationid' => $this->getCongregations(), '>date' => date('Y-m-d', strtotime($start_date.' -1 day')));
		if (!empty($end_date)) $service_params['<date'] = date('Y-m-d', strtotime($end_date.' +1 day'));
		$services = $GLOBALS['system']->getDBObjectData('service', $service_params, 'AND', 'date');

		$to_print = Array();
		foreach ($services as $id => $service_details) {
			$to_print[$service_details['date']]['service'][$service_details['congregationid']] = $service_details;
			$to_print[$service_details['date']]['service'][$service_details['congregationid']]['id'] = $id;
			$to_print[$service_details['date']]['assignments'] = Array();
		}
		foreach ($this->getAssignments($start_date, $end_date) as $date => $date_assignments) {
			$to_print[$date]['assignments'] = $date_assignments;
		}
		ksort($to_print);
		$role_objects = Array();

		$csvData = Array();

		// Headers
		$row = Array('');
		$lastCong = '';
		foreach ($this->_members as $id => $details) {
			if ($details['congregation_name'] != $lastCong) {
				$row[] = $details['congregation_name'];
				$lastCong = $details['congregation_name'];
			} else {
				$row[] = '';
			}
		}
		$csvData[] = $row;

		$row = Array('Date');
		$dummy_service = new Service();
		foreach ($this->_members as $id => $details) {
			if ($details['role_id']) {
				$row[] = $details['role_title'];
			} else {
				$row[] = $dummy_service->getFieldLabel($details['service_field'], true);
			}
		}
		$csvData[] = $row;

		foreach ($to_print as $date => $ddetail) {
			$row = Array(format_date($date));
			foreach ($this->_members as $id => $mdetail) {
				if (empty($mdetail)) continue;

				if (!empty($mdetail['role_id'])) {
					$names = Array();
					foreach (array_get($ddetail['assignments'], $mdetail['role_id'], Array()) as $rank => $vs) {
						$names[] = $vs['name'];
					}
					$row[] = implode("\n", $names);;
				} else {
					if (!empty($ddetail['service'][$mdetail['congregationid']])) {
						$dummy_service->populate($ddetail['service'][$mdetail['congregationid']]['id'], $ddetail['service'][$mdetail['congregationid']]);
						$row[] = $dummy_service->getFormattedValue($mdetail['service_field']);
					} else {
						$row[] = '';
					}
				}
			}
			$csvData[] = $row;
		}
		if ($return) return $csvData;
		print_csv($csvData);
	}

	function printSingleViewFlexi($service, $includeServiceFields=FALSE)
	{
		$showBlanks = ($this->getValue('show_on_run_sheet') == 1);
		$asns = $this->getAssignments($service->getValue('date'), $service->getValue('date'));
		$asns = empty($asns) ? Array() : reset($asns);
		$ourMembers = Array();
		foreach ($this->_members as $member) {
			if (empty($member['role_id']) && !$includeServiceFields) continue;
			if (!$showBlanks && !empty($member['role_id']) && empty($asns[$member['role_id']])) continue;
			$ourMembers[] = $member;
		}
		?>
		<div class="column">
		<?php
		$numCols = 3;
		$totalRows = ceil(count($ourMembers)/$numCols);
		$i = 0;
		foreach ($ourMembers as $member) {
			?>
			<div class="clearfix">
				<label>
					<?php $this->_printOutputLabel($member, $service); ?>
				</label>
				<div>
					<?php $this->_printOutputValue($member, $service, array_get($asns, $member['role_id'], Array())); ?>
				</div>
			</div>
			<?php
			$i++;
			if (($i % $totalRows == 0) && ($i < count($ourMembers))) {
				?>
		</div>
		<div class="column">
				<?php
			}
		}
		?>
		</div>
		<?php

		return $asns;
	}

	function printSingleViewTable($service, $columns=2, $includeServiceFields=FALSE)
	{
		$showBlanks = ($this->getValue('show_on_run_sheet') == 1);
		$asns = $this->getAssignments($service->getValue('date'), $service->getValue('date'));
		$asns = empty($asns) ? Array() : reset($asns);

		$ourMembers = Array();
		foreach ($this->_members as $member) {
			if (empty($member['role_id']) && !$includeServiceFields) continue;
			if (!$showBlanks && !empty($member['role_id']) && empty($asns[$member['role_id']])) continue;
			$ourMembers[] = $member;
		}

		$totalRows = ceil(count($ourMembers)/$columns);
		?>
		<table cellpadding="5">
			<?php
			for ($rowNum = 0; $rowNum < $totalRows; $rowNum++) {
				?>
				<tr>
				<?php
				$i = 0;
				foreach ($ourMembers as $member) {
					if (($i % $totalRows) == $rowNum) {
						?>
						<th><?php $this->_printOutputLabel($member, $service); ?></th>
						<td>
							<?php $this->_printOutputValue($member, $service, array_get($asns, $member['role_id'], Array()), 0); ?>
						</td>
						<?php
					}
					$i++;
				}
				?>
				</tr>
				<?php
			}
			?>
		</table>
		<?php
	}

	private function _printOutputLabel($member, $service)
	{
		if ($member['role_id']) {
			if (ifdef('PUBLIC_AREA_ENABLED', 1)) {
				echo '<a class="med-popup" href="'.BASE_URL.'/public/?view=display_role_description&role='.(int)$member['role_id'].'">';
			} else {
				echo '<a class="med-popup" href="'.BASE_URL.'?view=rosters__define_roster_roles&roster_roleid='.(int)$member['role_id'].'">';
			}
			echo ents($member['role_title']);
			echo '</a>';
		} else if ($member['service_field']) {
			echo ents($service->getFieldLabel($member['service_field'], TRUE));
		}
	}

	private function _printOutputValue($member, $service, $asn, $withLinks=TRUE)
	{
		// This is only used in run sheets
		if ($member['role_id']) {
			if (empty($asn)) echo '--';
			foreach ($asn as $rank => $asn) {
				if ($withLinks) echo '<span class="nowrap"><a href="?view=persons&personid='.$asn['personid'].'" class="med-popup">';
				echo ents($asn['name']);
				if ($withLinks) {
					echo '</a>';
					if ($asn['absenceid']) {
						echo ' <span class="label label-important" title="Planned absence: '.ents($asn['absence_comment']).'">!</i></span>';
					}
					if (('' === $asn['email'])) echo ' <img class="visible-desktop" src="'.BASE_URL.'resources/img/no_email.png" title="No Email Address" />';
					if (('' === $asn['mobile']) && SMS_Sender::canSend()) {
						echo ' <img class="visible-desktop" src="'.BASE_URL.'resources/img/no_phone.png" title="No Mobile" />';
					}
					echo '</span>';


				} else {
					echo '&nbsp;';
				}
				echo '<br />';
			}
		} else {
			$service->printFieldValue($member['service_field']);
		}
	}


	/**
	 *
	 * @param string $start_date
	 * @param string $end_date
	 * @param boolean $editing Whether to show the editable version
	 * @param boolean $public Whether we are showing on the public site
	 * @param boolean $printable Whether to show the printable version
	 * @return int	Count of the number of rows(dates) displayed.
	 */
	function printView($start_date=NULL, $end_date=NULL, $editing=FALSE, $public=FALSE, $printable=FALSE)
	{
		if (empty($this->_members)) return;
		$GLOBALS['system']->includeDBClass('service');
		$dummy_service = new Service();

		if (is_null($start_date)) $start_date = date('Y-m-d');
		$service_params = Array('congregationid' => $this->getCongregations(), '>date' => date('Y-m-d', strtotime($start_date.' -1 day')));
		if (!is_null($end_date)) $service_params['<date'] = date('Y-m-d', strtotime($end_date.' +1 day'));
		$services = $GLOBALS['system']->getDBObjectData('service', $service_params, 'AND', 'date');

		if (!$editing && !$public) {
			$my_email = $GLOBALS['user_system']->getCurrentUser('email');
		}
		if ($editing) {
			$absences = $GLOBALS['system']->getDBObjectData('planned_absence',
																Array(
																	'>=end_date' => $start_date,
																	'<=start_date' => $end_date,
																),
																'AND');
		}

		$to_print = Array();
		foreach ($services as $id => $service_details) {
			$service_details['id'] = $id;
			$to_print[$service_details['date']]['service'][$service_details['congregationid']] = $service_details;
			$to_print[$service_details['date']]['assignments'] = Array();
		}
		$haveHidden = FALSE;
		foreach ($this->getAssignments($start_date, $end_date) as $date => $date_assignments) {
			$to_print[$date]['assignments'] = $date_assignments;
			foreach ($date_assignments as $rid => $asns) {
				foreach ($asns as $rank => $dets) {
					if ($dets['assigneehidden']) $haveHidden = TRUE;
					break(2);
				}
			}
		}
		ksort($to_print);
		$role_objects = Array();
		$this_sunday = date('Y-m-d', strtotime('Sunday'));
		if (empty($to_print)) {
			if ($public) {
				?>
				<div class="alert alert-error">This roster is empty for the current date range.</div>
				<?php
			} else {
				?>
				<div class="alert alert-error">There are no services during the date range specified.  Please try a different date range, or create some services using the 'Edit service program' page.</div>
				<?php
			}
			return;
		}
		if (!$public && $haveHidden) {
			if ($editing) {
				print_message("Some allocations can't be edited because they involve persons you do not have permission to view", 'warning');
			} else {
				print_message("This roster includes some persons that you do not have permission to view", 'warning');
			}
		}

		if ($editing) {
			$show_lock_fail_msg = false;
			$show_group_denied_msg = false;
			$lockholders = Array(); // Array mapping personid of lock holders to info about them and their lock, for later display. Typically there will be N identical locks for N roles edited. We only store the first, assuming it is representative.
			foreach ($this->_members as $id => &$details) {
				if (!empty($details['role_id'])) {
					$role = $GLOBALS['system']->getDBObject('roster_role', $details['role_id']);
					if (!($role->canAcquireLock('assignments') && $role->acquireLock('assignments'))) {
						$details['readonly'] = true;
						$show_lock_fail_msg = true;
						$lockHolder = $role->getLockHolder('assignments');
						$lockholders[$lockHolder['userid']] ??= $lockHolder;
					}
					if (!$role->canEditAssignments()) {
						$details['readonly'] = true;
						$show_group_denied_msg = true;
					}
				}
			}
			if ($show_lock_fail_msg) {
				$this->printLockHoldMessage($lockholders);
			}
			if ($show_group_denied_msg) {
				print_message("There are some roles in this roster which you are not able to edit because they refer to a volunteer group you do not have access to.");
			}
			?>
			<form id="roster" method="post" class="warn-unsaved bubble-option-props" data-lock-length="<?php echo db_object::getLockLength() ?>">
			<?php
		}
		if (!$public) {
			require_once 'include/sms_sender.class.php';
			SMS_Sender::printModal();
			?>
			<div id="choose-assignee-modal" class="modal hide fade" role="dialog" aria-hidden="true">
				<div class="modal-header">
					<h4>Choose assignee</h4>
				</div>
				<div class="modal-body">
					<?php Person::printSingleFinder('personid', NULL); ?>
					<label class="checkbox">
						<input type="checkbox" name="add-to-group" value="1" />
						Add this person to the volunteer group for this role
					</label>
				</div>
				<div class="modal-footer">
					<button class="btn" data-dismiss="modal" id="choose-assignee-save">Save</button>
					<button class="btn" data-dismiss="modal"id="choose-assignee-cancel">Cancel</button>
				</div>
			</div>
			<?php
		}

		// HTML attrs for border/cellspacing etc are for the printable version
		?>
		<table class="table roster table-hover" border="1" cellspacing="0" cellpadding="1">

			<?php $this->_printTableHeader($editing, $public); ?>

			<tbody>
			<?php

			foreach ($to_print as $date => $ddetail) {
				if ($editing) {
					$date_absences = Array();
					foreach ($absences as $ab) {
						if (($ab['start_date'] <= $date) && ($ab['end_date'] >= $date)) {
							$date_absences[] = $ab['personid'];
						}
					}
				}
				$class_clause = ($date == $this_sunday) ? 'class="roster-next"' : '';
				?>

				<tr <?php echo $class_clause; ?>>
					<th class="roster-date nowrap">
						<?php
						echo '<strong>'.str_replace(' ', '&nbsp;', date('j M y', strtotime($date))).'</strong>';
						if (!$editing && !$public) {
							$emails = Array();
							$mobiles = Array();
							$personids = Array();
							foreach ($ddetail['assignments'] as $roleid => $assignees) {
								foreach ($assignees as $rank => $pdetails) {
									$personids[] = $pdetails['personid'];
									if (!empty($pdetails['email']) && $pdetails['email'] != $my_email) {
										$emails[] = $pdetails['email'];
									}
									if (!empty($pdetails['mobile'])) {
										$mobiles[] = $pdetails['mobile'];
									}
								}
							}
							if (!empty($emails) || (!empty($mobiles) && SMS_Sender::canSend())) {
								echo '<br />';
							}
							if (!empty($emails)) {
								?>
								<div class="smallprint no-print soft">
									<a class="soft" href="<?php echo get_email_href($my_email, NULL, $emails, date('jS F', strtotime($date))); ?>" <?php echo email_link_extras(); ?>>Email&nbsp;All</a>
				                </div>
								<?php
							}
							if (!empty($mobiles) && SMS_Sender::canSend()) {
								?>
								<div class="smallprint no-print">
								  <a class="soft" href="#send-sms-modal" data-personid="<?php echo implode(',', array_unique($personids)); ?>" data-toggle="sms-modal" data-name="People Rostered on <?php echo format_date($date);?>" onclick="$(this).parents('tr:first').addClass('tblib-hover')">SMS&nbsp;All</a>
								</div>
								<?php
							}
						}
						?>
					</th>
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
						$haveHidden = FALSE;
						foreach (array_get($ddetail['assignments'], $mdetail['role_id'], Array()) as $pdetails) {
							if ($pdetails['assigneehidden']) $haveHidden = TRUE;
						}
						if ($editing && empty($mdetail['readonly']) && !$haveHidden) {
							$currentval = Array();
							foreach (array_get($ddetail['assignments'], $mdetail['role_id'], Array()) as $rank => $pdetails) {
								$currentval[] = $pdetails['personid'];
							}
							if (empty($role_objects[$mdetail['role_id']])) {
								$role_objects[$mdetail['role_id']] = $GLOBALS['system']->getDBObject('roster_role', $mdetail['role_id']);
							}
							if (empty($role_objects[$mdetail['role_id']])) {
								// must've been a problem
								continue;
							}
							$role_objects[$mdetail['role_id']]->printChooser($date, $currentval, $date_absences);
						} else {
							$names = Array();
							foreach (array_get($ddetail['assignments'], $mdetail['role_id'], Array()) as $rank => $vs) {
								$personid = $vs['personid'];
								if (!$public && !$vs['assigneehidden']) {
									$href = '?view=persons&personid='.$personid;
									$n = '<span class="nowrap">';
									$n .= '<a data-personid="'.$personid . '" href="'.$href.'" title="Assigned by '.ents($vs['assigner']).' on '.format_datetime($vs['assignedon']).'">'.ents($vs['name']).'</a>';
									if (strlen(strval($vs['absenceid']))) {
										$n .= ' <a href="'.$href.'#rosters" class="label label-important" title="Planned absence: '.ents($vs['absence_comment']).'">!</i></a>';
									}
									if (('' === $vs['email'])) $n .= ' <img class="visible-desktop" src="'.BASE_URL.'resources/img/no_email.png" title="No Email Address" />';
									if (('' === $vs['mobile']) && SMS_Sender::canSend()) {
										$n .= ' <img class="visible-desktop" src="'.BASE_URL.'resources/img/no_phone.png" title="No Mobile" />';
					                }
									$n .= '</span>';
									$names[] = $n;
								} else {
									$names[] = '<span data-personid="'.$personid.'">'.nbsp(ents($vs['name'])).'</span>';;
								}
							}
							echo implode("<br />", $names);
						}
					} else {
						echo '<div class="service-field-'.$mdetail['service_field'].'">';
						if (!empty($ddetail['service'][$mdetail['congregationid']])) {
							if ($public && (!defined('SHOW_SERVICE_NOTES_PUBLICLY') || !SHOW_SERVICE_NOTES_PUBLICLY)) {
								// no notes in public view
								unset($ddetail['service'][$mdetail['congregationid']]['notes']);
							}
							$dummy_service->populate($ddetail['service'][$mdetail['congregationid']]['id'], $ddetail['service'][$mdetail['congregationid']]);
							$dummy_service->printFieldvalue($mdetail['service_field'], NULL, $printable);
						}
						echo '</div>';
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

		<?php
		if ($editing) {
			?>
			<input type="submit" class="btn" value="Save" accesskey="s" />
			</form>
			<?php
		}

		return count(array_keys($to_print));
	}

	function _printTableHeader($editing, $public)
	{
		?>
		<thead>
			<tr>
				<th rowspan="2" class="roster-date">Date</th>
				<?php
				$this->_printCongHeaders();
				?>
			</tr>
			<tr>
				<?php $this->_printRoleHeaders($editing, $public); ?>
			</tr>
		</thead>
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
		$dummy_service = new Service();
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
					if ($public) {
						echo '<a class="med-popup" href="'.BASE_URL.'/public/?view=display_role_description&role='.(int)$details['role_id'].'">';
					} else {
						echo '<a class="med-popup" href="'.BASE_URL.'?view=rosters__define_roster_roles&roster_roleid='.(int)$details['role_id'].'">';
					}
					echo ents($details['role_title']);
					echo '</a>';
				} else {
					if (!$public) echo '<a href="?view=services__list_all">';
					echo ents($dummy_service->getFieldLabel($details['service_field'], true));
					if (!$public) echo '</a>';
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
		if (!isset($_POST['assignees'])) return FALSE;

		// Exclude roles that we couldn't get the lock for
		$roles = $this->getRoleIds();
		foreach ($roles as $i => $roleid) {
			$role = $GLOBALS['system']->getDBObject('roster_role', $roleid);
			if (!$role->haveLock('assignments')) {
				unset($roles[$i]);
			}
			if (!$role->canEditAssignments()) {
				unset($roles[$i]);
			}
		}

		if (empty($roles)) {
			trigger_error('Couldn\'t process allocations - do not have the lock on any role');
			return FALSE;
		}

		$to_add = Array();
		$to_delete = $this->getAssignments($start_date, $end_date);
		foreach ($roles as $roleid) {
			if (!empty($_POST['assignees'][$roleid])) {
				foreach ($_POST['assignees'][$roleid] as $date => $assignee) {
					if (!is_array($assignee)) $assignee = Array($assignee);
					foreach ($assignee as $rank => $new_personid) {
						$new_personid = (int)$new_personid;
						if (empty($new_personid)) continue;
						if (isset($to_delete[$date][$roleid][$rank]) && $to_delete[$date][$roleid][$rank]['personid'] == $new_personid) {
							// unchanged allocation - leave it as is
							unset($to_delete[$date][$roleid][$rank]);
						} else {
							// new allocation
							$to_add[] = '('.(int)$roleid.', '.$GLOBALS['db']->quote($date).', '.(int)$new_personid.', '.(int)$rank.', '.(int)$GLOBALS['user_system']->getCurrentUser('id').')';
						}
					}
				}
			}
		}
		$del_clauses = Array();
		foreach ($to_delete as $date => $date_allocs) {
			foreach ($date_allocs as $roleid => $role_allocs) {
				if (in_array($roleid, $roles)) { // don't delete any allocations for read-only roles!!
					foreach ($role_allocs as $rank => $person_details) {
						if ($person_details['assigneehidden']) continue;
						$del_clauses[] = '(roster_role_id = '.(int)$roleid.' AND assignment_date = '.$GLOBALS['db']->quote($date).' AND `rank` = '.(int)$rank.')';
					}
				}
			}

		}
		$GLOBALS['system']->doTransaction('BEGIN');
		if (!empty($del_clauses)) {
			$sql = 'DELETE FROM roster_role_assignment WHERE ('.implode(' OR ', $del_clauses).')';
			$res = $GLOBALS['db']->query($sql);
		}
		if (!empty($to_add)) {
			$to_add = array_unique($to_add);
			$sql = 'REPLACE INTO roster_role_assignment (roster_role_id, assignment_date, personid, `rank`, assigner)
					VALUES '.implode(",\n", $to_add);
			$res = $GLOBALS['db']->query($sql);
		}

		// Tidy up ranks - for example if somebody already had rank 4 but they're
		// now the only assignee, update their rank to 0.
		// Just to be safe, we'll just do this for the roles in this roster view.
		$clean_role_ids = Array();
		foreach ($roles as $roleid) $clean_role_ids[] = (int)$roleid; // paranoia pays.
		$SQL = 'UPDATE roster_role_assignment rra
				INNER JOIN ( SELECT *,
								(row_number() OVER (PARTITION BY assignment_date, roster_role_id
													ORDER BY `rank` ASC) - 1) AS correctrank
							   FROM roster_role_assignment
							) a
							ON rra.assignment_date = a.assignment_date
								AND rra.roster_role_id = a.roster_role_id
								AND rra.personid = a.personid
				SET rra.`rank` = a.correctrank
				WHERE rra.`rank` <> a.correctrank
				AND rra.roster_role_id IN ('.implode(',', $clean_role_ids).')';
		$res = $GLOBALS['db']->query($SQL);

		$this->releaseLocks();
		unset($roleid);

		if (!empty($_POST['new_volunteers'])) {
			foreach ($_POST['new_volunteers'] as $roleID => $personIDs) {
				$role = $GLOBALS['system']->getDBObject('roster_role', $roleID);
				if (!$role) {
					trigger_error("Could not find role #$roleID to add new volunteer");
					continue;
				}
				$group = $GLOBALS['system']->getDBObject('person_group', $role->getValue('volunteer_group'));
				if (!$group) {
					trigger_error("Could not find volunteer group for role #$roleID");
					continue;
				}
				foreach ($personIDs as $personID) {
					if ($personID) $group->addMember($personID);
				}
			}
		}

		$GLOBALS['system']->doTransaction('COMMIT');
		return TRUE;
	}

	/**
	 * Get all the roster views that should be shown on the run sheet for the specified congregation
	 * @param int $congregationid
	 * @return array
	 */
	static function getForRunSheet($congregationid)
	{
		$res = Array();
		$SQL = '
		SELECT id
		FROM roster_view
		WHERE show_on_run_sheet > 0
		AND id IN (
			SELECT DISTINCT roster_view_id
			FROM roster_view_service_field sf
			WHERE sf.congregationid = '.(int)$congregationid.'
			UNION
			SELECT DISTINCT roster_view_id
			FROM roster_view_role_membership rm
			JOIN roster_role rr ON rr.id = rm.roster_role_id
			WHERE rr.congregationid = '.(int)$congregationid.'
		)';
		$ids = $GLOBALS['db']->queryCol($SQL);
		foreach ($ids as $id) {
			$res[$id] = $GLOBALS['system']->getDBObject('roster_view', $id);
		}
		return $res;
	}

	public function printAnalysis($start, $end)
	{
		if (!$this->getRoleIDs()) return;
		$db = JethroDB::get();
		$SQL = '
				SELECT personid, first_name, last_name,
					GROUP_CONCAT(DISTINCT role_title SEPARATOR ", ") as role_titles, count(asnid) as assignment_count, COUNT(distinct role_id) as role_count, COUNT(distinct assignment_date) as date_count
				FROM (
					SELECT p.id as personid, p.first_name, p.last_name, rr.id as role_id, rr.title as role_title, rra.assignment_date, concat(rra.assignment_date, "-", rr.id) as asnid
					FROM person p
					JOIN roster_role_assignment rra on p.id = rra.personid
					JOIN roster_role rr ON rra.roster_role_id = rr.id
					WHERE rra.assignment_date BETWEEN '.$db->quote($start).' AND '.$db->quote($end).'
					AND rr.id IN ('.implode(',', $this->getRoleIDs()).')
				) x
				GROUP BY personid, first_name, last_name
				HAVING date_count > 1
				ORDER BY assignment_count DESC
				';
		$res = $db->queryAll($SQL);
		if (empty($res)) {
			echo '<i>('._('None').')</i>';
			return;
		}

		?>
		<table class="table roster-analysis table-bordered table-condensed table-auto-width table-compact">
			<thead>
				<tr>
					<th>Name</th>
					<th>Load</th>
					<th>Roles</th>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ($res as $row) {
				$asn = $row['assignment_count'] > 1 ? 'assignments' : 'assignment';
				$dt = $row['date_count'] > 1 ? 'dates' : 'date';
				?>
				<tr>
					<td><a title="Click to highlight assignments" data-personid="<?php echo (int)$row['personid']; ?>" href="#"><?php echo ents($row['first_name'].' '.$row['last_name']); ?></a></td>
					<td><?php echo $row['assignment_count'].' '.$asn.' on '.$row['date_count'].' '.$dt; ?></td>
					<td><?php echo ents($row['role_titles']); ?></td>
				</tr>
				<?php
			}
			?>
			</tbody>
		</table>
		<?php

	}

	public function releaseLocks()
	{
		$roles = $this->getRoleIds();
		foreach ($roles as $i => $roleid) {
			$role = $GLOBALS['system']->getDBObject('roster_role', $roleid);
			$role->releaseLock('assignments');
		}
	}

	/**
	 * Print an error explaining which other user has the roster edit lock, and for how long.
	 * @param array{userid: int, array{userid: int, expires: string, first_name: ?string, last_name: ?string}} $lockholders Array mapping lock holder userids to info about them and their lock.
	 *
	 * @return void
	 * @throws DateMalformedStringException
	 */
	public function printLockHoldMessage(array $lockholders): void
	{
		$lockHoldersStr =
			implode(' and ', array_map(
				fn($lockowner, $userid) => $lockowner['first_name'] !== null ? "{$lockowner['first_name']} {$lockowner['last_name']} ({$userid})" : "user $userid",
				$lockholders,
				array_keys($lockholders)
			));
        // If more than one person holds locks of differing expiries, the longest expiry is most relevant.
		$maxLockExpiry = array_reduce($lockholders, function ($carry, $lockholder) {
			return $lockholder['expires'] > $carry ? $lockholder['expires'] : $carry;
		}, PHP_INT_MIN);
		$lockExpiresStr = (new DateTime($maxLockExpiry))->format('H:i');
		print_message("Some of the roles in this roster are currently being edited by $lockHoldersStr.  To edit assignments for these roles, wait until the other user finishes or their lock expires (at $lockExpiresStr), then try again.", 'failure');
	}
}
