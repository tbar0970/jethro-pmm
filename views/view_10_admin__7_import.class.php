<?php
require_once JETHRO_ROOT.'/include/person_importer.class.php';
require_once JETHRO_ROOT.'/include/import_source.class.php';

class View_Admin__Import extends View
{
	private $_stage = 'begin';
	private $_sess = NULL;

	static function getMenuPermissionLevel()
	{
		return PERM_SYSADMIN;
	}

	function getTitle()
	{
		return 'Import Persons';
	}

	function printView()
	{
		switch ($this->_stage) {
			case 'begin':
				$this->_printBeginView();
				break;

			case 'confirm':
				$this->_printConfirmView();
				break;

			case 'done':
				break;
		}
	}

	function processView()
	{
		$this->_sess =& $_SESSION['import'];

		$GLOBALS['system']->includeDBClass('family');
		$GLOBALS['system']->includeDBClass('person');
		$GLOBALS['system']->includeDBClass('person_group');
		$GLOBALS['system']->includeDBClass('congregation');
		$GLOBALS['system']->includeDBClass('person_note');

		if (!empty($_REQUEST['done'])) {
			$this->_stage = 'done';
		} else if (!empty($_POST['confirm_import'])) {
			$this->_processImport();
		} else if (isset($_POST['data_source']) || !empty($_FILES['import'])) {
			$this->_preparePreview();
		}
	}


	private function _printBeginView()
	{
		$text = _('This page allows you to import persons, families, groups and notes from spreadsheet data containing one person per row.  
			The data must be formatted like this sample file.  (Column order is flexible, but correct column headers are important.) 
			If you select the option to update existing persons, and an import row matches an existing person, surrounding rows will be imported as new members of the same family if applicable.
			An optional <code>personid</code> column (or <code>ID</code>, as in Jethro\'s own CSV exports) matches rows to existing persons by their person ID instead of by name.  Leave it blank for new persons.
			A <code>note</code> column allows you to add a note to the person record.
			You can add multiple <code>group</code> columns containing the name of groups to add the person to. 
			You can also tell Jethro to search within the column content to determine a group to add to, by using a header such as <code>groupif{men\'s events}!{women\'s}[men\'s events team]</code>.');
		$s = _('sample file');
		$text = str_replace($s, '<a href="?call=sample_import">'.$s.'</a>', $text);
		$text = '<p class="text">'.str_replace("\n", '</p><p class="text">', $text);
		print_message($text, 'info', true);
		?>
		<form method="post" enctype="multipart/form-data">
		<table class="table">
			<tr>
				<th class="narrow">Import Data&nbsp</th>
				<td>
					<?php Import_Source::printChooser($_REQUEST); ?>
				</td>
			</tr>
			<tr>
				<th>Group</th>
				<td>
					<label>
						<input type="radio" name="group_type" value="new" 
							<?php if (array_get($_REQUEST, 'group_type', 'new') == 'new') echo 'checked="checked"'; ?>
							data-toggle="visible" data-target=".grouptype" data-match-attr="data-group-source"  />
						Create a new group
						<span class="grouptype" data-group-source="new"
							<?php if (array_get($_REQUEST, 'group_type', 'new') != 'new') echo 'style="display:none"'; ?>	>
							called
							<input type="text" name="new_group_name" value="<?php echo ents(array_get($_REQUEST, 'new_group_name', '')); ?>"/>
							in category
							<?php
							Person_Group_Category::printChooser('new_group_categoryid', array_get($_REQUEST, 'new_group_categoryid'));
							?>
						</span>
					</label>
					<label>
						<input type="radio" name="group_type" value="existing"
						<?php if (array_get($_REQUEST, 'group_type', 'new') == 'existing') echo 'checked="checked"'; ?>
						data-toggle="visible" data-target=".grouptype" data-match-attr="data-group-source" />
						Add to an existing group
						<span class="grouptype" data-group-source="existing"
							<?php if (array_get($_REQUEST, 'group_type', 'new') != 'existing') echo 'style="display:none"'; ?>	>
							<?php Person_Group::printChooser('groupid', array_get($_REQUEST, 'groupid', 0)); ?>
						</span>
					</label>											
					<p class="smallprint">All created/updated persons will be added to this group as a record of this import</p>
				</td>
			</tr>
			<tr>
				<th>Options</th>
				<td>
					<label class="checkbox">
						<input type="checkbox" name="match_existing" value="1" <?php if (array_get($_REQUEST, 'match_existing', 1)) echo 'checked="checked"';?> data-toggle="enable" data-target="#match-options *"/>
						Update existing persons if they match (by person ID, or first & last names)
					</label>
					<div class="indent-left" id="match-options">
						<label class="checkbox">
							<input type="checkbox" name="match_email" value="1" <?php if (array_get($_REQUEST, 'match_email', 0)) echo 'checked="checked"';?>/>
							Don't match if email address is different
						</label>
						<label class="checkbox">
							<input type="checkbox" name="match_mobile_tel" value="1" <?php if (array_get($_REQUEST, 'match_mobile_tel', 0)) echo 'checked="checked"';?>/>
							Don't match if mobile number is different
						</label>
						If a field value differs,
						<label class="checkbox" checked="checked">
							<input type="radio" name="overwrite_existing" <?php if (array_get($_REQUEST, 'overwrite_existing', 1)) echo 'checked="checked"';?> value="1" />
							use the value from the import file
						</label>
						<label class="checkbox">
							<input type="radio" name="overwrite_existing" <?php if (!array_get($_REQUEST, 'overwrite_existing', 1)) echo 'checked="checked"';?> value="0" />
							preserve the existing value in Jethro
						</label>
					</div>
				</td>
			</tr>
			<tr>
				<td>&nbsp;</td>
				<td>
					<input type="submit" class="btn" value="Continue &raquo;" /> 
					<p class="smallprint">(<?php echo _('You will be asked to confirm at the next step'); ?>)</p>
				</td>
			</tr>
		</table>
		</form>
		<?php
	}


	private function _preparePreview()
	{
		switch ($_REQUEST['group_type']) {
			case 'existing':
				if (empty($_REQUEST['groupid'])) {
					add_message(_("You must choose a group first"), 'error');
					$this->_stage = 'begin';
					return;
				}
				break;
			case 'new':
				if (!strlen(array_get($_REQUEST, 'new_group_name'))) {
					add_message(_("You must enter a name for the new group"), 'error');
					$this->_stage = 'begin';
					return;
				}
				break;
		}
		
		$source = Import_Source::get(isset($_REQUEST['data_source']) ? $_REQUEST['data_source'] : 'upload');
		if (!$source) {
			trigger_error("Invalid data_source value");
			return;
		}
		try {
			$fetched = $source->fetch($_REQUEST, $_FILES);
		} catch (\RuntimeException $e) {
			add_message(ents($e->getMessage()), 'error');
			return;
		}
		$datafile = $fetched['path'];
		$separator = $fetched['separator'];
		
		$importer = new Person_Importer([
			'overwrite_existing' => array_get($_REQUEST, 'overwrite_existing', 1),
			'match_existing' => !empty($_REQUEST['match_existing']),
			'match_email' => !empty($_REQUEST['match_email']),
			'match_mobile_tel' => !empty($_REQUEST['match_mobile_tel']),
			'groupid' => (int)array_get($_POST, 'groupid'),
			'new_group_name' => array_get($_POST, 'new_group_name'),
			'new_group_categoryid' => (int)array_get($_POST, 'new_group_categoryid'),
		]);
		$ok = $importer->parse($datafile, $separator);
		foreach ($importer->getMessages() as $message) {
			add_message($message['text'], $message['type']);
		}
		$this->_sess = $importer->getPlan();
		$row_errors = $importer->getRowErrors();
		if (!empty($row_errors)) {
			$msg = _('Your import file is not valid.  Please correct the following errors and try again:').'<ul>';
			foreach ($row_errors as $line => $errors) {
				$msg .= '<li>Row '.($line).': '.implode('; ', $errors).'</li>';
			}
			$msg .= '</ul>';
			add_message($msg, 'failure', true);
		}
		$this->_stage = ($ok && empty($row_errors)) ? 'confirm' : 'begin';
		unlink($datafile);
	}


	private function _printConfirmView()
	{
		if (($this->_sess['groupid'])) {
			$groupname = $GLOBALS['system']->getDBObject('person_group', $_SESSION['import']['groupid'])->toString();
		} else {
			$groupname = $this->_sess['new_group_name'];
		}
		$GLOBALS['system']->includeDBClass('family');
		?>
		<p class="alert alert-info text">
			Your import is ready to run. Check the details below then click "Proceed" at the bottom.
		</p>
		<h2>Summary</h2>
		<table class="table table-bordered table-auto-width">
			<tr>
				<td>New families to be created</td>
				<td><?php echo count($this->_sess['new_families']); ?></td>
			</tr>
			<tr>
				<td>New persons to be created</td>
				<td>
					<?php
					$printed = FALSE;
					if ($this->_sess['total_new_persons']) {
						echo $this->_sess['total_new_persons'].' within new families';
						$printed = TRUE;
					}
					if ($np = count($this->_sess['new_persons'])) {
						if ($printed) echo '<br />';
						echo $np.' within existing families';
						$printed = TRUE;
					}
					if (!$printed) echo '0';
					?>
				</td>
			</tr>
			<tr>
				<td>Existing families to be updated</td>
				<td><?php echo count($this->_sess['family_updates']); ?></td>
			</tr>
			<tr>
				<td>Existing persons to be updated</td>
				<td><?php echo count($this->_sess['person_updates']); ?></td>
			</tr>
		<?php
		if (!empty($this->_sess['new_groups']) || !empty($this->_sess['matched_groups'])) {
			?>
			<tr>
				<td>New groups to be created</td>
				<td><?php echo count($this->_sess['new_groups']); ?></td>
			</tr>
			<tr>
				<td>Existing groups to have members added</td>
				<td><?php echo count($this->_sess['matched_groups']); ?></td>
			</tr>
			<?php
		}
		?>
		</table>
		<?php
		printf('<p>'._('All new and updated persons will be added to the %s group.').'</p>', '<i>'.ents($groupname).'</i>');

		if (!empty($this->_sess['new_groups'])) {
			?>
			<h3>New groups to be created</h3>
			<ul>
				<li>
					<?php echo implode('</li><li>', $this->_sess['new_groups']); ?>
				</li>
			</ul>
			<?php
		}

		if (!empty($this->_sess['new_families'])) {
			?>
			<h3>New families and persons to be created</h3>
			<table class="table table-bordered">
				<thead>
					<tr>
						<th>Family Name</th>
						<th>Home Tel</th>
						<th>Address</th>

						<?php
						$this->_printPersonHeaders();
						?>

					</tr>
				</thead>
				<tbody>
				<?php
				foreach ($this->_sess['new_families'] as $familydata) {
					$rowspan = 'rowspan="'.count($familydata['members']).'"';
					?>
					<tr>
						<td <?php echo $rowspan; ?> class="nowrap"><?php echo ents(array_get($familydata, 'family_name')); ?></td>
						<td <?php echo $rowspan; ?> class="nowrap"><?php echo ents(array_get($familydata, 'home_tel')); ?></td>
						<td <?php echo $rowspan; ?> class="nowrap">
							<?php
							if (!empty($familydata['address_street'])) {
								echo nl2br(ents($familydata['address_street']));
								echo '<br />'.$familydata['address_suburb'].' '.$familydata['address_state'].' '.$familydata['address_postcode'];
							}
							?>
						</td>
					<?php
					$this->_printPersonRows($familydata['members'], TRUE);
				}
				?>
				</tbody>
			</table>
			<?php
		}

		if (!empty($this->_sess['new_persons'])) {
			?>

			<h3>New persons to be added to existing families</h3>
			<table class="table table-bordered">
				<thead>
					<tr>
						<th>Family</th>
						<?php
						$this->_printPersonHeaders();
						?>
					</tr>
				</thead>
				<tbody>
				<?php
				$this->_printPersonRows($this->_sess['new_persons'], FALSE, TRUE)
				?>
				</tbody>
			</table>
			<?php
		}

		if (!empty($this->_sess['family_updates'])) {
			?>
			<h3>Existing families to be updated</h3>
			<table class="table table-bordered table-auto-width">
				<thead>
					<tr>
						<th>ID</th>
						<th>Family Name</th>
						<th>Home Tel</th>
						<th>Address</th>
					</tr>
				</thead>
				<tbody>
				<?php
				foreach ($this->_sess['family_updates'] as $id => $familydata) {
					?>
					<tr>
						<td>
							<a href="?view=families&familyid=<?php echo (int)$id; ?>" class="med-popup">#<?php echo (int)$id; ?></a>
						</td>
						<td class="nowrap"><?php echo ents(array_get($familydata, 'family_name')); ?></td>
						<td class="nowrap"><?php echo ents(array_get($familydata, 'home_tel')); ?></td>
						<td class="nowrap">
							<?php
							if (!empty($familydata['address_street'])) {
								echo nl2br(ents($familydata['address_street']));
								echo '<br />'.$familydata['address_suburb'].' '.$familydata['address_state'].' '.$familydata['address_postcode'];
							}
							?>
						</td>
					</tr>
					<?php
				}
				?>
				</tbody>
			</table>
			<?php
		}

		if (!empty($this->_sess['person_updates'])) {
			?>
			<h3>Existing persons to be updated</h3>
			<table class="table table-bordered table-auto-width">
				<thead>
					<tr>
						<th>ID</th>
						<?php $this->_printPersonHeaders(); ?>
					</tr>
				</thead>
				<tbody>
				<?php
				$this->_printPersonRows($this->_sess['person_updates'], FALSE, FALSE, TRUE);
				?>
				</tbody>
			</table>
			<?php
		}
		?>

		<form method="post"><input type="submit" name="confirm_import" value="Proceed with import" class="confirm-title btn btn-danger" title="Proceed with import" />
		<a href="<?php echo build_url(array()); ?>" class="btn">Cancel and start again</a>
		</form>
		<?php
	}

	private function _processImport()
	{
		ini_set('zlib.output_compression', 'Off');
		include_once 'templates/head.template.php';
		?>

		<h1 style="position: absolute; text-align: center; top: 40%; color: #ccc; width: 100%">Importing...</h1>
		<div style="border: 1px solid; width: 50%; height: 30px; top: 50%; left: 25%; position: absolute"><div id="progress" style="background: blue; height: 30px; width: 2%; overflow: visible; line-height: 30px; text-align: center; color: white" /></div>
		<p style="text-align: center; color: #888">If this indicator stops making progress, your import may still be running in the background.<br />You should <a href="<?php echo build_url(Array('view' => 'persons__list_all')); ?>">check your system for the imported persons</a> before running the import again.</p>
		<?php

		$importer = new Person_Importer();
		$importer->setPlan($this->_sess);
		$result = $importer->apply(function ($done, $todo) {
			$this->_printProgress($done, $todo);
		});

		$url = '?view='.$_REQUEST['view'];
		if (!$result['ok']) {
			$msg = _('Errors during import - import aborted').'. <ul><li>'.implode('</li></li>', array_get($result['errors'], 0, [])).'</li></ul>';
			add_message($msg, 'failure', true);
		} else {
			add_message(_('Import complete'), 'success',TRUE);
			if (!empty($result['group_id'])) {
				$url = build_url(Array('view' => 'groups', 'groupid' => $result['group_id']));
			}
		}

		?><script>document.location = '<?php echo $url; ?>';</script>
		<?php
		exit;
	}


	//////////////////// PRIVATE HELPERS //////////////////////////////

	private function _printPersonHeaders()
	{
		?>
				<th>First Name</th>
				<th>Last Name</th>
				<th>Age</th>
				<th>Gender</th>
				<th>Congregation</th>
				<th>Status</th>
				<th>Email</th>
				<th>Mobile Tel</th>
				<th>Work Tel</th>
				<?php
				foreach ($this->_sess['used_custom_fields'] as $fieldid => $label) {
					?>
					<th><?php echo ents($label); ?></th>
					<?php
				}
				?>
				<th>Note</th>
				<th>To be added to groups</th>
		<?php
	}

	private function _printPersonRows($persons, $omitFirstTR=FALSE, $includeFamilyLink=FALSE, $includePersonLink=FALSE)
	{
		$first = TRUE;
		foreach ($persons as $key => $person) {
			if (!$first || !$omitFirstTR) {
				echo '<tr>';
			}
			$first = FALSE;

			if ($includeFamilyLink) {
				?>
				<td>
					<a href="?view=families&familyid=<?php echo (int)$person['familyid']; ?>" class="med-popup">#<?php echo (int)$person['familyid']; ?></a>
				</td>
				<?php
			}
			if ($includePersonLink) {
				?>
				<td>
					<a href="?view=persons&personid=<?php echo (int)$key; ?>" class="med-popup">#<?php echo (int)$key; ?></a>
				</td>
				<?php

			}
			?>
			<td><?php echo ents($person['first_name']); ?></td>
			<td><?php echo ents($person['last_name']); ?></td>
			<td><?php echo ents($person['age_bracket']); ?></td>
			<td><?php echo ents(array_get($person, 'gender')); ?></td>
			<td><?php echo ents(array_get($person, 'congregation')); ?></td>
			<td><?php echo ents(array_get($person, 'status', '')); ?></td>
			<td><?php echo ents(array_get($person, 'email')); ?></td>
			<td><?php echo ents(array_get($person, 'mobile_tel')); ?></td>
			<td><?php echo ents(array_get($person, 'work_tel')); ?></td>
			<?php
			foreach ($this->_sess['used_custom_fields'] as $fieldid => $label) {
				?>
				<td><?php echo ents(array_get($person, $label, '')); ?></td>
				<?php
			}
			?>
			<td><?php echo ents(array_get($person, '_note', '')); ?></td>
			<td><?php echo implode('; ', array_map('ents', array_get($person, '_groups', Array()))); ?></td>
			<?php
			echo '</tr>';
		}
	}


	private function _printProgress($done, $todo)
	{
		if ($done % 5 == 0) {
			$message = ((int)(($done/$todo)*100)).'% complete';
			?>
			<script>
				var d = document.getElementById('progress');
				d.innerHTML = "<?php echo ents($message); ?>";
				d.style.width = '<?php echo (int)(($done/$todo)*100); ?>%';
			</script>
			<?php
			echo str_repeat('    ', 1024*4);
		}
	}

	/**
	 * Column headers recognised by the importer. Delegates to Person_Importer.
	 *
	 * @param bool $required_fields_only Whether to return only the columns every import file must contain
	 * @return list<string>
	 */
	public static function getSampleHeader($required_fields_only=FALSE)
	{
		return Person_Importer::getSampleHeader($required_fields_only);
	}

}
