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
		return _('Contact List');
	}


	function printView()
	{
		if (!empty($_REQUEST['go'])) {
			if (!empty($_REQUEST['groupid'])) {
				?>
				<a class="pull-right" href="<?php echo build_url(Array('call' => 'contact_list', 'format' => 'html', 'view' => NULL)); ?>">Download HTML file</a><br />
				<a class="pull-right" href="<?php echo build_url(Array('call' => 'contact_list', 'format' => 'docx', 'view' => NULL)); ?>">Download DOCX file</a>
				<?php
				$this->printResults();
				return;
			} else {
				print_message(_("You must choose an opt-in group"), 'error');
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
				<th><?php echo _('Opt-in group');?></th>
				<td><?php Person_Group::printChooser('groupid', 0); ?></td>
			</tr>
			<tr>
				<th><?php echo _('Congregation');?></th>
				<td><?php echo _('Only include opted-in persons from');?><br />
				<?php $dummy_person->printFieldInterface('congregationid'); ?></td>
			</tr>
			<tr>
				<th><?php echo _('Age brackets');?></th>
				<td><?php echo _('Only show contact details for persons who are');?><br />
				<?php $dummy_person->printFieldInterface('age_bracket'); ?>
				</td>
			</tr>
			<tr>
				<th><?php echo _('Other family members');?></th>
				<td><?php echo _('For other members of the families of persons who opted in, show');?><br />
				<label class="radio">
					<input type="radio" name="all_member_details" value="-1" checked="checked" id="all_member_details_0" />
					<?php echo _('nothing - do not mention them at all');?>
				</label>
				<label class="radio">
					<input type="radio" name="all_member_details" value="0" checked="checked" id="all_member_details_0" />
					<?php echo _('only their names');?>
				</label>
				<label class="radio">
					<input type="radio" name="all_member_details" value="1" id="all_member_details_1" />
					<?php echo _('their contact details, same as for opted-in persons');?>
				</label>
			</tr>
			<tr>
					<th><?php echo _('Addresses');?></th>
					<td>
						<label class="checkbox">
							<input type="checkbox" name="include_address" />
							<?php echo _('Include home addresses in results');?>
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
		?>
		<table class="contact-list">
		<?php
		foreach ($this->getData() as $family) {
			//bam($family);
			?>
			<tr>
			<?php
			if (!empty($_REQUEST['include_photos'])) {
				if (($family['have_photo']) || count($family_members) == 1) {
					if ($dataURLs) {
						$src = Photo_Handler::getDataURL('family', $family['famliyid']);
					} else {
						$src = '?call=photo&familyid='.$family['famliyid'];
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
					<h2 style="margin: 5px 0px 0px 0px"><?php echo $family['family_name']; ?></h2></td></tr>
			<?php
			if ($family['home_tel']) {
				echo '<tr><td colspan="4"><h3 style="border: 0px; margin: 0px; padding: 0px">';
				echo ents($family['home_tel']);
				echo '</h3></td></tr>';
			}
			if (!empty($_REQUEST['include_address']) && $family['address_street']) {
				echo '<tr><td colspan="4">'.nl2br(ents($family['address_street'])).'<br />';
				echo ents($family['address_suburb'].' '.$family['address_state'].' '.$family['address_postcode']);
				echo '</td></tr>';
			}
			foreach ($family['adults'] as $adult) {
				?>
				<tr style="height: 1px">
					<td><?php echo ents($adult['name']); ?></td>
					<td><?php echo ents($adult['congname']); ?></td>
					<td><?php echo ents($adult['mobile_tel']); ?></td>
					<td><?php echo ents($adult['email']); ?></td>
				</tr>
				<?php
			}
			if ($family['child_names']) {
				?>
				<tr style="height: 1px">
					<td colspan="4"><?php echo ents(implode(', ', $family['child_names'])); ?></td
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

	private function getData()
	{
		$dummy_person = new Person();
		$dummy_family = new Family();

		$db = $GLOBALS['db'];
		$groupid = (int)$_REQUEST['groupid'];
		$all_member_details = array_get($_REQUEST, 'all_member_details', 0);

		if (empty($groupid)) return;

		$sql = '
		select family.id as familyid, family.family_name, family.home_tel,
			person.id, person.first_name, person.last_name, person.mobile_tel, person.email, person.age_bracket,
			congregation.long_name as congname,
			address_street, address_suburb, address_state, address_postcode,
			IF (fp.familyid IS NULL, 0, 1) as have_photo,
			IF (signup.groupid IS NULL, 0, 1) as signed_up
		from family
		join person on family.id = person.familyid
		left join congregation on person.congregationid = congregation.id
		left join family_photo fp ON fp.familyid = family.id
		left join person_group_membership signup ON signup.personid = person.id AND signup.groupid = '.(int)$groupid.'
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
			?><p><i><?php echo _('No families to show');?></i></p><?php
			return;
		}

		$GLOBALS['system']->includeDBClass('family');
		$GLOBALS['system']->includeDBClass('person');

		$families = Array();

		foreach ($res as $familyid => $family_members) {
			$family = Array(
						'familyid' => $familyid,
						'adults' => Array(),
						'children' => Array(),
				);
			$adults_use_full = FALSE;
			$children_use_full = FALSE;
			foreach ($family_members as $member) {
				$member['name'] = $member['first_name'];
				if (!$member['signed_up']) {
					if ($all_member_details == -1) continue;
					if ($all_member_details == 0) {
						$member['email'] = $member['mobile_tel'] = '';
					}
				}
				$member['mobile_tel'] = $dummy_person->getFormattedValue('mobile_tel', $member['mobile_tel']);
				if (empty($_REQUEST['age_bracket']) || in_array($member['age_bracket'], $_REQUEST['age_bracket'])) {
					$family['adults'][] = $member;
					if ($member['last_name'] != $member['family_name']) {
						$adults_use_full = true;
					}
				} else {
					$family['children'][] = $member;
					if ($member['last_name'] != $member['family_name']) {
						$children_use_full = true;
					}
				}
			}

			if ($adults_use_full) {
				foreach ($family['adults'] as &$adult) {
					$adult['name'] .= ' '.$adult['last_name'];
				}
			}
			if ($children_use_full) {
				foreach ($family['children'] as &$child) {
					$child['name'] .= ' '.$child['last_name'];
				}
			}

			$family['child_names'] = Array();
			foreach ($family['children'] as $child) {
				$family['child_names'][] = $child['name'];
			}

			$first_member = reset($family_members);
			foreach (Array('have_photo', 'family_name', 'home_tel', 'address_street', 'address_suburb', 'address_state', 'address_postcode') as $ffield) {
				$family[$ffield] = $first_member[$ffield];
			}
			$family['home_tel'] = $dummy_family->getFormattedValue('home_tel', $family['home_tel']);

			$families[] = $family;
		}
		return $families;
	}


	public function printDOCX()
	{
		// NB THIS FILE HAS BEEN CHANGED!
		require_once 'include/phpword/src/PhpWord/Autoloader.php';
		\PhpOffice\PhpWord\Autoloader::register();
		\PhpOffice\PhpWord\Settings::setTempDir(sys_get_temp_dir());
		require_once 'view_9_documents.class.php';
		$phpWord =  new \PhpOffice\PhpWord\PhpWord();
		$phpWord->addParagraphStyle('FAMILY HEADER', array());
		$phpWord->addFontStyle('FAMILY NAME', array('bold' => true, 'size' => 15));
		$phpWord->addFontStyle('HOME PHONE', array());
		$phpWord->addFontStyle('ADDRESS', array());
		$phpWord->addFontStyle('PERSON NAME', array('bold' => true));
		$phpWord->addTableStyle('FAMILY LIST', array('width' => '100%', 'borderSize' => 0, 'cellMargin' => 80,'borderColor' => 'CCCCCC'));

		/*$intro = $phpWord->addSection();
		$intro->addTitle(SYSTEM_NAME.' Contact List', 1);
		$intro->addText('Intro text goes here');*/

		$section = $phpWord->addSection(array('breakType' => 'continuous'));

		/*$outro = $phpWord->addSection(array('breakType' => 'continuous'));
		$outro->addText('Concluding text goes here');*/

		

		$table = $section->addTable('FAMILY LIST');

		$wideCellProps = array('gridSpan' => 4, 'valign' => 'top');
		$narrowCellProps = array('valign' => 'top');

		foreach ($this->getData() as $family) {
			$table->addRow();
			$table->addCell(NULL, $wideCellProps)
						->addText($family['family_name'], 'FAMILY NAME', 'FAMILY HEADER');

			if ($family['address_street']) {
				$table->addRow();
				$cell = $table->addCell(NULL, $wideCellProps);
				$cell->addText($family['address_street'], 'ADDRESS');
				$cell->addText($family['address_suburb'].' '.$family['address_state'].' '.$family['address_postcode'], 'ADDRESS');
			}

			if ($family['home_tel']) {
				$table->addRow();
				$table->addCell(NULL, $wideCellProps)
							->addText($family['home_tel'], 'HOME PHONE');
			}

			foreach ($family['adults'] as $member) {
				$table->addRow();
				$table->addCell('30%', $narrowCellProps)->addText($member['name'], 'PERSON NAME');
				$table->addCell('25%', $narrowCellProps)->addText($member['congname']);
				$table->addCell('20%', $narrowCellProps)->addText($member['mobile_tel']);
				$table->addCell('20%', $narrowCellProps)->addText($member['email']);
			}
		}

		$objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
		$tempname = tempnam(sys_get_temp_dir(), 'contactlist');
		$objWriter->save($tempname);
		
		readfile($tempname);
		unlink($tempname);
		
		/*$templateFilename = View_Documents::getRootPath().'/Templates/contact_list_template.docx';
		if (file_exists($templateFilename)) {
			require_once 'include/odf_tools.class.php';
			$guts = ODF_Tools::getDOCXBodyContent($tempname);	
			$outname = tempnam(sys_get_temp_dir(), 'contactlist');
			copy($templateFilename, $outname);
			ODF_Tools::appendToDOCXBody($outname, $guts);
			readfile($outname);
			unlink($outname);
		} else {
			readfile($tempname);
		}
		 */
		 
	}
}
