<?php
class View_Families__Contact_List extends View
{

	// NOTE: DOCX Contact lists have to be A4.  We can only add table cells with fixed widths.

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
				<div class="pull-right">
				<a class="clickable back"><i class="icon-wrench"></i>Adjust configuration</a><br />
				<i class="icon-download"></i> Download as
				<a href="<?php echo build_url(Array('call' => 'contact_list', 'format' => 'html', 'view' => NULL)); ?>">HTML</a> |
				<a href="<?php echo build_url(Array('call' => 'contact_list', 'format' => 'docx', 'view' => NULL)); ?>">DOCX</a>
				</div>
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
		$dummy_person->fields['age_bracketid']['allow_multiple'] = true;
		$dummy_person->fields['age_bracketid']['allow_empty'] = true;
		?>
		<form method="get">
		<input type="hidden" name="view" value="<?php echo ents($_REQUEST['view']); ?>" />
		<table>
			<tr>
				<th><?php echo _('Opt-in group');?></th>
				<td>
					<?php echo _('Only show families that have a member in the group'); ?> <br />
					<?php Person_Group::printChooser('groupid', 0); ?></td>
			</tr>
			<tr>
				<th><?php echo _('Congregation');?></th>
				<td><?php echo _('Only include opted-in persons from');?><br />
				<?php $dummy_person->printFieldInterface('congregationid'); ?></td>
			</tr>
			<tr>
				<th><?php echo _('Age brackets');?></th>
				<td><?php echo _('Only show contact details for persons who are');?><br />
				<?php $dummy_person->printFieldInterface('age_bracketid'); ?>
				</td>
			</tr>
			<tr>
				<th><?php echo _('Other family members');?></th>
				<td><?php echo _('When a family has other members not in the opt-in group above:');?><br />
				<label class="radio">
					<input type="radio" name="all_member_details" value="-1" checked="checked" id="all_member_details_0" />
					<?php echo _('Do not show them at all');?>
				</label>
				<label class="radio">
					<input type="radio" name="all_member_details" value="0" checked="checked" id="all_member_details_0" />
					<?php echo _('Show their names but no contact details');?>
				</label>
				<label class="radio">
					<input type="radio" name="all_member_details" value="1" id="all_member_details_1" />
					<?php echo _('Show their contact details just like the opted-in persons');?>
				</label>
			</tr>
			<tr>
					<th><?php echo _('Details to show');?></th>
					<td>
						<label class="checkbox">
							<?php
							print_widget('include_address', Array('type' => 'checkbox'), array_get($_REQUEST, 'include_address', TRUE));
							echo _('Home address');
							?>
						</label>
						<label class="checkbox">
							<?php
							print_widget('include_home_tel', Array('type' => 'checkbox'), array_get($_REQUEST, 'include_home_tel', TRUE));
							echo _('Home phone');
							?>
						</label>
						<label class="checkbox">
							<?php
							print_widget('include_congregation', Array('type' => 'checkbox'), array_get($_REQUEST, 'include_congregation', TRUE));
							echo _('Congregation');
							?>
						</label>
					<?php
					if ($GLOBALS['system']->featureEnabled('PHOTOS')) {
						?>
						<label class="checkbox">
							<?php
							print_widget('include_photos', Array('type' => 'checkbox'), array_get($_REQUEST, 'include_photos', TRUE));
							echo _('Family photos');
							?>
						</label>
						<?php
					}
					?>
					</td>
			</tr>
			<tr>
				<th></th>
				<td>
					<input class="btn" type="submit" name="go" value="Show results" />
				</td>
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
			?>
			<tr>
			<?php
			if (!empty($_REQUEST['include_photos'])) {
				$rowSpan = count($family['optins']) + 2;
				if (!empty($_REQUEST['include_home_tel']) && $family['home_tel']) $rowSpan++;
				if (!empty($_REQUEST['include_address']) && $family['address_street']) $rowSpan++;
				if (count($family['all']) > count($family['optins'])) $rowSpan++;
				if (($family['have_photo']) || count($family['optins']) == 1) {
					if ($dataURLs) {
						$src = Photo_Handler::getDataURL('family', $family['familyid']);
					} else {
						$src = '?call=photo&familyid='.$family['familyid'];
					}
				} else {
					$src = BASE_URL.'resources/img/unknown_family.gif';
				}
				?>
				<td rowspan="<?php echo $rowSpan; ?>" style="padding: 5px">
					<img style="width: 200px" src="<?php echo $src; ?>" />
				</td>
				<?php
			}
			?>
				<td colspan="4" style="height: 1px">
					<h2 style="margin: 5px 0px 0px 0px"><?php echo $family['family_name']; ?></h2>
				</td>
			</tr>
			<?php
			if (count($family['all']) > count($family['optins'])) {
				?>
				<tr style="height: 1px">
					<td colspan="4"><i><?php echo ents($family['all_names']); ?></td>
				</tr>
				<?php
			}
			if (!empty($_REQUEST['include_home_tel']) && $family['home_tel']) {
				echo '<tr style="height: 1px"><td colspan="4">';
				echo ents($family['home_tel']);
				echo '</td></tr>';
			}
			if (!empty($_REQUEST['include_address']) && $family['address_street']) {
				echo '<tr style="height: 1px"><td colspan="4">'.str_replace("\n", ', ', ents($family['address_street'])).', ';
				echo ents($family['address_suburb'].' '.$family['address_state'].' '.$family['address_postcode']);
				echo '</td></tr>';
			}
			foreach ($family['optins'] as $adult) {
				?>
				<tr style="height: 1px">
					<td style="padding-right: 1ex"><?php echo ents($adult['name']); ?></td>
					<td style="padding-right: 1ex">
						<?php
						if (!empty($_REQUEST['include_congregation'])) echo ents($adult['congname']);
						?>
					</td>
					<td style="padding-right: 1ex"><?php echo ents($adult['mobile_tel']); ?></td>
					<td><?php echo ents($adult['email']); ?></td>
				</tr>
				<?php
			}
			if (!empty($_REQUEST['include_photos'])) {
				// to take up extra vertical space
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
			person.id, person.first_name, person.last_name, person.mobile_tel, person.email, person.age_bracketid,
			congregation.long_name as congname,
			address_street, address_suburb, address_state, address_postcode,
			IF (fp.familyid IS NULL, 0, 1) as have_photo,
			IF (signup.groupid IS NULL, 0, 1) as signed_up,
			IF (pp.personid IS NULL, 0, 1) as have_person_photo
		from family
		join person on family.id = person.familyid
		join age_bracket ab ON ab.id = person.age_bracketid
		left join congregation on person.congregationid = congregation.id
		left join family_photo fp ON fp.familyid = family.id
		left join person_group_membership signup ON signup.personid = person.id AND signup.groupid = '.(int)$groupid.'
		left join person_photo pp ON pp.personid = person.id
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
		order by family_name asc, familyid, ab.rank asc, IF(ab.is_adult, gender, "") desc, first_name asc
		';
		$res = $db->queryAll($sql, null, null, true, true, true);

		if (empty($res)) {
			?><p><i><?php echo _('No families to show');?></i></p><?php
			return Array();
		}

		$GLOBALS['system']->includeDBClass('family');
		$GLOBALS['system']->includeDBClass('person');

		$families = Array();

		foreach ($res as $familyid => $family_members) {
			$family = Array(
						'familyid' => $familyid,
						'optins' => Array(),
						'all' => Array(),
				);
			$adults_use_full = FALSE;
			$all_use_full = FALSE;
			foreach ($family_members as $member) {
				$member['name'] = $member['first_name'];
				// show full details if
				// - (they are signed up, or all-member-details is 1)
				// - AND (their age bracket is correct) OR all age brackets are in
				if (
					($member['signed_up'] || $all_member_details == 1)
					&& (empty($_REQUEST['age_bracketid']) || in_array($member['age_bracketid'], $_REQUEST['age_bracketid']))
				) {
					$member['mobile_tel'] = $dummy_person->getFormattedValue('mobile_tel', $member['mobile_tel']);
					$family['optins'][] = $member;
					if ($member['last_name'] != $member['family_name']) {
						$adults_use_full = true;
					}
				}

				if ($member['signed_up'] || $all_member_details != -1) {
					$family['all'][] = $member;
				}
				if ($member['last_name'] != $member['family_name']) {
					$all_use_full = true;
				}
			}

			if ($adults_use_full) {
				foreach ($family['optins'] as &$adult) {
					$adult['name'] .= ' '.$adult['last_name'];
				}
				unset($adult);
			}
			if ($all_use_full) {
				foreach ($family['all'] as &$member) {
					$member['name'] .= ' '.$member['last_name'];
				}
				unset($member);
			}

			$family['all_names'] = Array();
			foreach ($family['all'] as $member) {
				$family['all_names'][] = $member['name'];
			}
			$last = '';
			if (count($family['all_names']) > 1) $last = array_pop($family['all_names']);
			$family['all_names'] = implode(', ', $family['all_names']);
			if ($last) $family['all_names'] .= ' & '.$last;

			$first_member = reset($family_members);
			foreach (Array('have_photo', 'have_person_photo', 'family_name', 'home_tel', 'address_street', 'address_suburb', 'address_state', 'address_postcode') as $ffield) {
				$family[$ffield] = $first_member[$ffield];
			}
			$family['home_tel'] = $dummy_family->getFormattedValue('home_tel', $family['home_tel']);

			$families[] = $family;
		}
		return $families;
	}


	public function printDOCX()
	{
		require_once 'include/odf_tools.class.php';
		require_once 'vendor/autoload.php';
		\PhpOffice\PhpWord\Settings::setTempDir(sys_get_temp_dir());
		\PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(TRUE);

		require_once 'view_9_documents.class.php';
		$width = 14173; // 25cm in twips
		$phpWord =  new \PhpOffice\PhpWord\PhpWord();
		$phpWord->addParagraphStyle('FAMILY-HEADER', array());
		$phpWord->addParagraphStyle('FAMILY-SUB-HEADER', array());
		$phpWord->addFontStyle('FAMILY-NAME', array('bold' => true, 'size' => 15));
		$phpWord->addFontStyle('FAMILY-MEMBERS', array('italic' => true));
		$phpWord->addFontStyle('HOME-PHONE', array());
		$phpWord->addFontStyle('ADDRESS', array());
		$phpWord->addFontStyle('PERSON-NAME', array('bold' => true));
		$phpWord->addTableStyle('FAMILY-LIST', array('width' => $width, 'borderSize' => 0, 'cellMargin' => 40,'borderColor' => 'CCCCCC'));
		$section = $phpWord->addSection(array('breakType' => 'continuous'));

		/////////////////////////////////////////////////////////////////////////////////
		// STEP ONE - BUILD A TEMPORARY DOCX FILE WITH THE TABLE OF CONTACT DATA:

		$table = $section->addTable('FAMILY-LIST');
		$gridspan = 3;
		if (!empty($_REQUEST['include_congregation'])) $gridspan++;

		$extraWideCellProps = $wideCellProps = array('gridSpan' => $gridspan, 'valign' => 'top');
		if (!empty($_REQUEST['include_photos'])) {
			$wideCellProps['gridSpan']--;
		}
		$narrowCellProps = array('valign' => 'top', 'vMerge' => 'restart');
		$mergeProps = array('vMerge' => 'continue');

		// Show single-person photos at 65% the width of family photos
		$imageWidthPoints = 172;
		$imageWidthTwips = $imageWidthPoints*20;
		$familyImageStyle = Array('width' => $imageWidthPoints);
		$singleImageStyle = Array('width' => $imageWidthPoints*0.65);

		$cleanup = Array();
		foreach ($this->getData() as $family) {
			$table->addRow();

			$table->addCell(NULL, $extraWideCellProps)
						->addText($family['family_name'], 'FAMILY-NAME', 'FAMILY-HEADER');

			$table->addRow();
			$rowOpen = TRUE;
			if (!empty($_REQUEST['include_photos'])) {
				// Add photo cell but stay on the same row.
				$cell = $table->addCell($imageWidthTwips, $narrowCellProps);
				$imageStyle = (count($family['all']) == 1) ? $singleImageStyle : $familyImageStyle;
				if ($family['have_photo'] || (count($family['all']) == 1 && $family['have_person_photo'])  ) {
					$tempfile = str_replace('.tmp', '', tempnam(sys_get_temp_dir(), 'contactlistphoto')).'.jpg';
					$cleanup[] = $tempfile;
					file_put_contents($tempfile, Photo_Handler::getPhotoData('family', $family['familyid']));
					try {
						$cell->addImage($tempfile, $imageStyle);
					} catch (Exception $e) {
						if (!filesize($tempfile)) {
							error_log(__METHOD__.': Got zero bytes of photo data for family #'.$family['familyid']);
						} else {
							error_log(__METHOD__.' exception adding image to DOCX: '.$e->getMessage());
						}
					}
				} else {
					// Previously we included the placeholder images. But it seems better not to.
					//$cell->addImage(JETHRO_ROOT.'/resources/img/unknown.jpg', $imageStyle);
				}
			}

			if (count($family['all']) > count($family['optins'])) {
				$table->addCell(NULL, $wideCellProps)
							->addText($family['all_names'], 'FAMILY-MEMBERS', 'FAMILY-SUB-HEADER');
				$rowOpen = FALSE;
			}

			if (!empty($_REQUEST['include_address']) && $family['address_street']) {
				if (!$rowOpen) {
					$table->addRow();
					if (!empty($_REQUEST['include_photos'])) {
						$table->addCell(NULL, $mergeProps);
					}
				}

				$cell = $table->addCell(NULL, $wideCellProps);
				// save vertical space by putting address on one line
				$text = str_replace("\n", ', ', $family['address_street']).', ';
				$text .= $family['address_suburb'].' '.$family['address_state'].' '.$family['address_postcode'];
				$cell->addText($text, 'ADDRESS');
				$rowOpen = FALSE;
			}

			if (!empty($_REQUEST['include_home_tel']) && $family['home_tel']) {
				if (!$rowOpen) {
					$table->addRow();
					if (!empty($_REQUEST['include_photos'])) {
						$table->addCell(NULL, $mergeProps);
					}
				}
				$table->addCell(NULL, $wideCellProps)
							->addText($family['home_tel'], 'HOME PHONE');
				$rowOpen = FALSE;
			}

			foreach ($family['optins'] as $member) {
				if (!$rowOpen) {
					$table->addRow();
					if (!empty($_REQUEST['include_photos'])) {
						$table->addCell(NULL, $mergeProps);
					}
				}
				$table->addCell($width*0.25, $narrowCellProps)->addText($member['name'], 'PERSON-NAME');
				if (!empty($_REQUEST['include_congregation'])) {
					$table->addCell($width*0.25, $narrowCellProps)->addText($member['congname']);
				}
				$contactCell = $table->addCell($width*0.25, $narrowCellProps);
				if (strlen($member['mobile_tel'])) $contactCell->addText($member['mobile_tel']);
				if (strlen($member['email'])) {
					if (!empty($_REQUEST['include_photos'])) {
						// If there's a photo, put mobile and email in the same cell on different lines
						$contactCell->addText($member['email']);
					} else {
						$table->addCell($width*0.2, $narrowCellProps)->addText($member['email']);
					}
				}



				$rowOpen = FALSE;
			}
		}

		$objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
		$tempname = tempnam(sys_get_temp_dir(), 'contactlist');
		$objWriter->save($tempname);

		foreach ($cleanup as $file) {
			unlink($file);
		}

		//readfile($tempname); exit; // TEMP

		/////////////////////////////////////////////////////////////////////////////////
		// STEP 2 - MERGE THE CONTACT LIST DATA FROM THE TEMPORARY FILE INTO A TEMPLATE
		// EITHER THE BUILT-IN ONE OR A CUSTOM ONE

		// In the original file:
		//  - in word/media folder
		//		rename xyz.jpg to jethroimage-xyz.jpg
		//  - in word/_rels/document.xml.rels
		//     change ID="xyz" to ID="jethroimage-xyz"
		//     change Target="media/xyz.jpg" to Target="media/jethroimage-xyz.jpg"
		//  - in word/document.xml
		//		change r:id="xyz" to r:id="jethroimage-xyz"
		//  Then copy the document content into the new word/document.xml
		//  Then add the images from word/media to the new zip
		//  Then add the relationships from word/_rels/document.xml.rels to new zip's rels file.
		//


		$templateFilename = Documents_Manager::getRootPath().'/Templates/contact_list_template.docx';
		if (!file_exists($templateFilename)) {
			// no custom template found - use the built-in template
			$templateFilename = JETHRO_ROOT.'/resources/contact_list_template.docx';
		}
		if (file_exists($templateFilename)) {
			require_once 'include/odf_tools.class.php';
			$outname = tempnam(sys_get_temp_dir(), 'contactlist').'.docx';
			copy($templateFilename, $outname);
			ODF_Tools::insertFileIntoFile($tempname, $outname, '%CONTACT_LIST%');
			$replacements = Array('SYSTEM_NAME' => SYSTEM_NAME, 'MONTH' => date('F Y'));
			ODF_Tools::replaceKeywords($outname, $replacements);
			readfile($outname);
			unlink($outname);
		} else {
			// Couldn't find any template (!) - dump the temporary raw file.
			readfile($tempname);
		}
		unlink($tempname);

	}
}

