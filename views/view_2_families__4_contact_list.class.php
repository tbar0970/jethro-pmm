<?php
class View_Families__Contact_List extends View
{

	static function getMenuPermissionLevel()
	{
		return PERM_RUNREPORT;
	}

	function processView()
	{
		$GLOBALS['system']->includeDBClass('family');
		$GLOBALS['system']->includeDBClass('person');
		$GLOBALS['system']->includeDBClass('person_group');
	}
	
	function getTitle()
	{
		return 'Contact List';
	}


	function printView()
	{
		if (!empty($_REQUEST['go'])) {
			if (!empty($_REQUEST['groupid'])) {
				?>
				<a class="pull-right" href="<?php echo build_url(Array('call' => 'contact_list', 'view' => NULL)); ?>">Download HTML file</a>
				<?php
				$this->printResults();
				return;
			} else {
				print_message("You must choose an opt-in group", 'error');
			}
		}
		$this->printForm();
	}

	function printForm()
	{
		$dummy_person = new Person();
		$dummy_person->fields['congregationid']['allow_multiple'] = true;
		$dummy_person->fields['age_bracket']['allow_multiple'] = true;
		?>
		<form method="get">
		<input type="hidden" name="view" value="<?php echo ents($_REQUEST['view']); ?>" />
		<table>
			<tr>
				<th>Opt-in group</th>
				<td><?php Person_Group::printChooser('groupid', 0); ?></td>
			</tr>
			<tr>
				<th>Congregation</th>
				<td>Only include opted-in persons from<br />
				<?php $dummy_person->printFieldInterface('congregationid'); ?></td>
			</tr>
			<tr>
				<th>Age brackets</th>
				<td>Only show contact details for persons who are<br />
				<?php $dummy_person->printFieldInterface('age_bracket'); ?>
				</td>
			</tr>
			<tr>
				<th>Other family members</th>
				<td>For other members of the families of persons who opted in, show<br />
				<label class="radio">
					<input type="radio" name="all_member_details" value="0" checked="checked" id="all_member_details_0" />
					only their names
				</label>
				<label class="radio">
					<input type="radio" name="all_member_details" value="1" id="all_member_details_1" />
					their contact details, same as for opted-in persons
				</label>
			</tr>
			<tr>
					<th>Addresses</th>
					<td>
						<label class="checkbox">
							<input type="checkbox" name="include_address" />
							Include home addresses in results
						</label>
					</td>
			</tr>
			<tr>
					<th>Photos</th>
					<td>
						<label class="checkbox">
							<input type="checkbox" name="include_photos" />
							Include family photos
						</label>
					</td>
			</tr>
			<tr>
				<th></th>
				<td><input type="submit" name="go" value="Go" /></td>
			</tr>
		</table>
		</form>
		<?php
	}

	function printResults($dataURLs=FALSE)
	{
		$db = $GLOBALS['db'];
		$groupid = (int)$_REQUEST['groupid'];
		$all_member_details = array_get($_REQUEST, 'all_member_details', 0);

		if (empty($groupid)) return;
		
		$sql = '
		select family.id as familyid, family.family_name, family.home_tel, 
			person.*, congregation.long_name as congname,
			address_street, address_suburb, address_state, address_postcode,
			IF (fp.familyid IS NULL, 0, 1) as have_photo
		from family 
		join person on family.id = person.familyid
		left join congregation on person.congregationid = congregation.id
		left join family_photo fp ON fp.familyid = family.id
		where person.status <> "archived"
		and family.id in 
		(select familyid 
		from person join person_group_membership pgm on person.id = pgm.personid
		where pgm.groupid = '.(int)$groupid;

		if (!empty($_REQUEST['congregationid'])) {
			$sql .= '
				AND person.congregationid in ('.implode(',', array_map(Array($db, 'quote'), $_REQUEST['congregationid'])).')';
		}
		$sql .= ')
		order by family_name asc, age_bracket asc, gender desc
		';
		$res = $db->queryAll($sql, null, null, true, true, true);
		check_db_result($res);

		if (empty($res)) {
			?><p><i>No families to show</i></p><?php
			return;
		}

		$sql = '
		select personid
		from person_group_membership pgm
		where pgm.groupid = '.(int)$groupid;
		$signups = $db->queryCol($sql);
		check_db_result($signups);

		$GLOBALS['system']->includeDBClass('family');
		$GLOBALS['system']->includeDBClass('person');

		$dummy_family = new Family();
		$dummy_person = new Person();
		?>

		<table class="contact-list">
		<?php
		foreach ($res as $familyid => $family_members) {
			$adults = Array();
			$children = Array();
			$adults_use_full = false;
			$children_use_full = false;
			foreach ($family_members as $member) {
				if (empty($_REQUEST['age_bracket']) || in_array($member['age_bracket'], $_REQUEST['age_bracket'])) {
					$adults[] = $member;
					if ($member['last_name'] != $member['family_name']) {
						$adults_use_full = true;
					}
				} else {
					$children[] = $member;
					if ($member['last_name'] != $member['family_name']) {
						$children_use_full = true;
					}
				}
			}
			$first_member = reset($family_members);
			?>
			<tr>
			<?php
			if (!empty($_REQUEST['include_photos'])) {
				if ($first_member['have_photo']) {
					if ($dataURLs) {
						$src = Photo_Handler::getDataURL('family', $familyid);
					} else {
						$src = '?call=photo&familyid='.$familyid;
					}
				} else {
					$src = BASE_URL.'resources/img/unknown_family.gif';
				}
				?>
				<td rowspan="<?php echo 2 + count($adults) + (empty($children) ? 0 : 1); ?>" style="padding: 5px">
					<img src="<?php echo $src; ?>" />
				</td>
				<?php
			}
			?>
				<td colspan="4" style="height: 1px">
					<h2 style="margin: 5px 0px 0px 0px"><?php echo $first_member['family_name']; ?></h2></td></tr>
			<?php
			if ($first_member['home_tel']) {
				$dummy_family->setValue('home_tel', $first_member['home_tel']);
				echo '<tr><td colspan="4"><h3 style="border: 0px; margin: 0px; padding: 0px">';
				echo ents($dummy_family->getFormattedValue('home_tel'));
				echo '</h3></td></tr>';
			}
			if (!empty($_REQUEST['include_address']) && $first_member['address_street']) {
				echo '<tr><td colspan="4">'.nl2br(ents($first_member['address_street'])).'<br />';
				echo ents($first_member['address_suburb'].' '.$first_member['address_state'].' '.$first_member['address_postcode']);
				echo '</td></tr>';
			}
			$fn = 'getFormattedValue';
			foreach ($adults as $adult) {
				$dummy_person->populate($adult['id'], $adult);
				?>
				<tr style="height: 1px">
					<td><?php echo ents($adults_use_full ? $adult['first_name'].' '.$adult['last_name'] : $adult['first_name']); ?></td>
					<td><?php echo ents($adult['congname']); ?></td>
					<td><?php if ($all_member_details || in_array($adult['id'], $signups)) echo ents($dummy_person->getFormattedValue('mobile_tel')); ?></td>
					<td><?php if ($all_member_details || in_array($adult['id'], $signups)) echo ents($dummy_person->$fn('email')); ?></td>
				</tr>
				<?php
			}
			$child_names = Array();
			foreach ($children as $child) {
				$child_names[] = $children_use_full ? $child['first_name'].' '.$child['last_name'] : $child['first_name'];
			}
			if ($child_names) {
				?>
				<tr style="height: 1px">
					<td colspan="4"><?php echo ents(implode(', ', $child_names)); ?></td
				</tr>
				<?php
			}
			?>
			<?php
			if (!empty($_REQUEST['include_photos'])) {
				?>
				<tr>
					<td colspan="4">&nbsp;</td>
				</tr>
				<?php
			}
		}
		?>
		</table>
		<?php
	}
}
?>
