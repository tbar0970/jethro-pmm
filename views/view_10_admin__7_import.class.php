<?php
class View_Admin__Import extends View
{
	var $_stage = 'begin';
	
	static function getMenuPermissionLevel()
	{
		return PERM_SYSADMIN;
	}

	function getTitle()
	{
		return 'Import';
	}

	function _isEmptyRow($row)
	{
		foreach ($row as $x) {
			if (!empty($x)) return FALSE;
		}
		return TRUE;
	}

	function _isNewFamily($row, $current_family)
	{
		foreach (Array('family_name', 'address_street', 'address_suburb', 'address_state', 'home_tel') as $field) {
			if (!empty($row[$field]) && !empty($current_family[$field])) {
				$newVal = strtolower($row[$field]);
				if ($field == 'home_tel') $newVal = preg_replace('/[^0-9]/', '', $newVal);
				if ($newVal != strtolower($current_family[$field])) {
					return TRUE;
				}
			}
		}
		return FALSE;
	}

	function _captureErrors()
	{
		$this->_captured_errors = Array();
		set_error_handler(Array($this, '_handleError'));
	}

	function _handleError($errno, $errstr, $errfile, $errline)
	{
		if (in_array($errno, array(E_USER_NOTICE, E_USER_WARNING, E_NOTICE, E_WARNING))) {
			$this->_captured_errors[] = $errstr;
		} else {
			$GLOBALS['system']->_handleError($errno, $errstr, $errfile, $errline);
		}
	}

	function _getErrors()
	{
		$res = $this->_captured_errors;
		$this->_captured_errors = Array();
		restore_error_handler();
		return $res;
	}

	function processView()
	{
		$GLOBALS['system']->includeDBClass('family');
		$GLOBALS['system']->includeDBClass('person');
		$GLOBALS['system']->includeDBClass('person_group');
		$GLOBALS['system']->includeDBClass('congregation');
		$GLOBALS['system']->includeDBClass('person_note');

		if (!empty($_REQUEST['done'])) {
			$this->_stage = 'done';
		} else if (!empty($_POST['confirm_import'])) {
			ini_set('memory_limit', '256M');
			ini_set('max_execution_time', 60*10);
			ini_set('zlib.output_compression', 'Off');

			// read from session and create
			$GLOBALS['system']->doTransaction('BEGIN');
			$group = $GLOBALS['system']->getDBObject('person_group', $_SESSION['import']['groupid']);
			$this->_captureErrors();
			$done = 0;
			?>
			<h1 style="position: absolute; text-align: center; top: 40%; color: #ccc; width: 100%">Importing...</h1>
			<div style="border: 1px solid; width: 50%; height: 30px; top: 50%; left: 25%; position: absolute"><div id="progress" style="background: blue; height: 30px; width: 2%; overflow: visible; line-height: 30px; text-align: center; color: white" /></div>
			<p style="text-align: center; color: #888">If this indicator stops making progress, your import may still be running in the background.<br />You should <a href="<?php echo build_url(Array('view' => 'persons__list_all')); ?>">check your system for the imported persons</a> before running the import again.</p>
			<?php
			foreach ($_SESSION['import']['families'] as $familydata) {
				$members = $familydata['members'];
				unset($familydata['members']);
				$family = new Family();
				$family->populate(0, $familydata);
				if ($family->create()) {
					foreach ($members as $persondata) {
						$notetext = null;
						if (!empty($persondata['note'])) {
							$notetext = $persondata['note'];
							unset($persondata['note']);
						}
						$person = new Person();
						$person->populate(0, $persondata);
						$person->setValue('familyid', $family->id);
						if ($person->create()) {
							$group->addMember($person->id);
							if ($notetext) {
								$note = new Person_Note();
								$note->setValue('subject', 'Import note');
								$note->setvalue('details', $notetext);
								$note->setValue('personid', $person->id);
								$note->create();
								unset($note);
							}
							unset($person);
						}
					}
					$done++;
					if ($done % 20 == 0) {
						?><script>var d = document.getElementById('progress'); d.innerHTML = 'Importing family <?php echo $done.' of '.$_SESSION['import']['total_families']; ?>'; d.style.width = '<?php echo (int)(($done/$_SESSION['import']['total_families'])*100); ?>%'</script><?php
						echo str_repeat('    ', 1024*4);
					}
					flush();
					unset($family);
				}
			}
			if ($errors = $this->_getErrors()) {
				$msg = _('Errors during import - import aborted').'. <ul><li>'.implode('</li></li>', $errors).'</li></ul>';
				add_message($msg, 'failure', true);
				$GLOBALS['system']->doTransaction('ROLLBACK');
			} else {
				add_message(_('Import complete'), 'success');
				$GLOBALS['system']->doTransaction('COMMIT');
			}
			?><script>document.location = '<?php echo build_url(Array('view' => 'groups', 'groupid' => $group->id)); ?>&done=1';</script>
			<?php
			exit;
		
		} else if (!empty($_FILES['import'])) {
			if (empty($_REQUEST['groupid'])) {
				add_message(_("You must choose a group first"), 'error');
				$this->stage = 'begin';
				return;
			}
			if (empty($_FILES['import']) || empty($_FILES['import']['tmp_name'])) {
				add_message(_("You must upload a file"), 'error');
				return;
			}
			$this->_dummy_family = new Family();
			$this->_dummy_person = new Person();

			// read the csv and save to session
			ini_set("auto_detect_line_endings", "1");
			$fp = fopen($_FILES['import']['tmp_name'], 'r');
			if (!$fp) {
				add_message(_("There was a problem reading your CSV file.  Please try again."), 'error');
				$this->stage = 'begin';
				return;
			}
			
			$map = fgetcsv($fp, 0, ",", '"');
			foreach ($map as $k => $v) {
				$map[$k] = strtolower(str_replace(' ', '_', $v));
			}
			$_SESSION['import']['groupid'] = (int)$_POST['groupid'];
			$_SESSION['import']['families'] = Array();
			$_SESSION['import']['total_families'] = 0;
			$_SESSION['import']['total_persons'] = 0;
			$_SESSION['import']['total_notes'] = 0;
			$row_errors = Array();
			$family = NULL;
			$i = 1;
			while ($rawrow = fgetcsv($fp, 0, ",", '"')) {

				$row = Array();
				foreach ($map as $index => $fieldname) {
					$row[$fieldname] = array_get($rawrow, $index);
				}

				if ($this->_isEmptyRow($row)) {
					// Blank row = start a new family for the next row
					unset($family);
					continue;
				}

				if (!isset($family) || $this->_isNewFamily($row, $family)) {
					// Add family
					$this->_dummy_family->values = Array();
					$this->_dummy_family->setValue('status', 'current');
					$this->_captureErrors();
					$familyrow = $row;
					unset($familyrow['status']);
					$this->_dummy_family->fromCsvRow($familyrow);
					if ($errors = $this->_getErrors()) {
						$row_errors[$i] = $errors;
					} else {
						$_SESSION['import']['families'][] = $this->_dummy_family->values;
						$family =& $_SESSION['import']['families'][count($_SESSION['import']['families'])-1];
						$_SESSION['import']['total_families']++;
					}
				} else {
					// see if there's anything to update
					// eg if the second family member has a home tel
					foreach ($family as $fi => $fv) {
						if (($family[$fi] === '') && ($row[$fi] !== '')) {
							$family[$fi] = $row[$fi];
						}
					}
				}

				$this->_captureErrors();

				// Add a person and note
				$this->_dummy_person->values = Array();
				$this->_dummy_person->setValue('familyid', '-1');
				if (!empty($row['congregation'])) {
					$row['congregationid'] = Congregation::findByName($row['congregation']);
				}
				$this->_dummy_person->fromCsvRow($row);
				if ($errors = $this->_getErrors()) {
					$row_errors[$i] = array_merge(array_get($row_errors, $i, array()), $errors);
				} else {
					$member = $this->_dummy_person->values + Array('congregation' => $this->_dummy_person->getFormattedValue('congregationid'));
					foreach ($this->_dummy_person->getCustomValues() as $fieldID => $val) {
						$member['CUSTOM_'.$fieldID] = $val;
						$_SESSION['import']['custom_field_ids'][$fieldID] = true;
					}
					if (!empty($row['note'])) {
						$member['note'] = $row['note'];
						$_SESSION['import']['total_notes']++;
					}
					$family['members'][] = $member;
					$_SESSION['import']['total_persons']++;
				}
				$i++;
			}
			if (!empty($row_errors)) {
				$msg = _('Your import file is not valid.  Please correct the following errors and try again:').'<ul>';
				foreach ($row_errors as $line => $errors) {
					$msg .= '<li>Row '.($line+1).': '.implode('; ', $errors).'</li>';
				}
				$msg .= '</ul>';
				add_message($msg, 'failure', true);
				$this->_stage = 'begin';
			} else {
				$this->_stage = 'confirm';
			}

		}
	}

	function printView()
	{
		switch ($this->_stage) {
			case 'begin':
				if (!($GLOBALS['system']->getDBObjectData('person_group'))) {
					print_message(_('You must create a group to import into before you can import persons.  See Groups > Add.'));
					return;
				}
				$text = _('This page allows you to import persons, families and notes from a CSV file.  The CSV must be in a format like this sample file.  (The order of columns is flexible but the first row must be a header with these column names).
					You may also include extra columns labelled with the exact name of custom fields.
					Jethro will create a new person record for each row in the CSV file.  Jethro will create a new family record whenever a row contains different family details to the previous row (although changing to or from a blank value does not count as a change).  A new family is also triggered if you include an entirely blank row.
					All the imported persons will be added to the group you choose below.');
				$s = _('sample file');
				$text = str_replace($s, '<a href="'.BASE_URL.'/resources/sample_import.csv">'.$s.'</a>', $text);
				$text = '<p class="text">'.str_replace("\n", '</p><p class="text">', $text);
				echo $text;
				?>
				<form method="post" enctype="multipart/form-data">
				<table>
					<tr>
						<td>Import File:</td>
						<td><input type="file" name="import" /></td>
					</tr>
					<tr>
						<td>Group:</td>
						<td><?php Person_Group::printChooser('groupid', 0); ?></td>
					</tr>
					<tr>
						<td>&nbsp;</td>
						<td class="compulsory"><input type="submit" class="btn" value="Go" /></td>
					</tr>
				</table>
				</form>
				<p>(<?php echo _('You will be asked to confirm at the next step'); ?>)</p>
				<?php
				break;
				
			case 'confirm':
				$groupname = $GLOBALS['system']->getDBObject('person_group', $_SESSION['import']['groupid'])->toString();
				$GLOBALS['system']->includeDBClass('family');
				$this->_dummy_family = new Family();
				?>
				<p class="alert alert-info text">
				<?php echo _('Please check the following is correct then click "confirm" at the bottom'); ?><br />
				<b>
						<?php printf(
								_('%s families,
									%s persons and
									%s notes
									will be created.'),
								$_SESSION['import']['total_families'],
								$_SESSION['import']['total_persons'],
								$_SESSION['import']['total_notes']
								);
						?>
				</b><br />
				<?php ents(printf(_('The new persons will be added to the %s group.'), $groupname));	?>
				</p>

				<?php
				foreach ($_SESSION['import']['families'] as $family) {
					foreach ($family['members'] as $k => &$v) {

						unset($v['note']); // don't show notes in the preview

						// format custom field names and values
						foreach (array_get($_SESSION['import'], 'custom_field_ids', Array()) as $fieldID => $x) {
							$field = $GLOBALS['system']->getDBObject('custom_field', $fieldID);
							$v[$field->getValue('name')] = $field->formatValue(array_get($v, 'CUSTOM_'.$fieldID));
							unset($v['CUSTOM_'.$fieldID]);
						}
					}
					?>
					<h3><?php echo ents($family['family_name']); ?> family</h3>
					<?php
					$this->_dummy_family->reset();
					$this->_dummy_family->populate(0, $family);
					$this->_dummy_family->printSummaryWithMembers(FALSE, $family['members']);
				}
				?>
				<form class="stop-js" method="post"><input type="submit" name="confirm_import" value="Proceed with import" class="confirm-title btn" title="Proceed with import" />
				<a href="<?php echo build_url(array()); ?>" class="btn">Cancel and start again</a>
				</form>
				<?php
				break;
			case 'done':
				break;
		}
	}
}