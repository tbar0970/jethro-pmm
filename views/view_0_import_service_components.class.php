<?php
require_once 'abstract_view_add_object.class.php';

class View__Import_Service_Components extends View
{
	private $errors = Array();
	private $category = null;
	private $_captured_errors;
	private $all_by_ccli;
	private $all_by_title;

	static function getMenuPermissionLevel()
	{
		return PERM_SERVICECOMPS;
	}

	function processView()
	{
		$GLOBALS['system']->includeDBClass('service_component_category');
		$this->category = new Service_Component_Category($_REQUEST['categoryid']);
		if (!empty($_FILES['datafile']) && !empty($_FILES['datafile']['tmp_name'])) {
			$GLOBALS['system']->doTransaction('BEGIN');
			$GLOBALS['system']->includeDBClass('service_component');
			$fp = fopen($_FILES['datafile']['tmp_name'], 'r');
			if (!$fp) {
				trigger_error("Your data file could not be read.  Please check the file and try again");
				return;
			}
			$toprow = fgetcsv($fp, 0, ",", '"', "");
			$rowNum = 1;
			$updatedCount = 0;
			$createdCount = 0;
			while ($row = fgetcsv($fp, 0, ",", '"', "")) {
				$this->_captureErrors();
				$data = $this->getLabelledRow($toprow, $row);

				if (isset($data['content'])) {
					$c = trim($data['content']);
					$c = str_replace("\r", "", $c);
					$c = str_replace("\n\n", "</p><p>", $c);
					$c = str_replace("\n", "<br />", $c);
					$data['content_html'] = '<p>'.$c.'</p>';
					unset($data['content']);
				}
				if (isset($data['show_in_handout'])) {
					$val = $data['show_in_handout'];
					$map = Array(
							'y' => 'full',
							'n' => 0,
							'yes' => 'full',
							'no' => 0,
						  );
					$val = array_get($map, strtolower($val), $val);
					if (!in_array($val, Array('0', 'title', 'full'))) {
						$val = '0';
					}
					$data['show_in_handout'] = $val;
				}

				$comp = $this->maybeLoadFromCCLINumber($data);
				if (!$comp) $comp = $this->maybeLoadFromExistingIfTitleMatches($data);
				if (!$comp) {
					$comp = $this->createNew($data);
					$new = true;
				} else {
					$new = false;
				}

				// Update/set fields from CSV
				$comp->fromCSVRow($data);

				if (!($errors = $this->_getErrors())) {
					// If no errors, create/save
					foreach ($_REQUEST['congregationids'] as $congid) {
						$comp->addCongregation($congid);
					}
					if ($new) {
						$comp->create();
						$createdCount++;
					} else {
						$comp->save();
						$updatedCount++;
					}
				} else {
					$this->errors[$rowNum] = $errors;
				}
				$this->createAndAssociateTags($comp, $data);

				$rowNum++;
			}

			if (empty($this->errors)) {
				$GLOBALS['system']->doTransaction('COMMIT');
				add_message($createdCount.' songs created<br>'.
					$updatedCount.' songs updated<br>'.
					($rowNum - 1).' CSV rows processed',
					'success', true);
				redirect('services__component_library'); // exits
			} else {
				add_message("Errors were found in the CSV file.  Import has not been performed.  Please correct the errors and try again", 'error');
				$GLOBALS['system']->doTransaction('ROLLBACK');
			}
			fclose($fp);
		}
	}

	/** Looks for columns starting with 'tag' (case-insensitive) e.g. "Tags" or "Tag 1", "Tag 2", and tags the imported song with those tags, creating them if necessary.
	 * @param Service_Component $comp
	 * @param array $data CSV row data
	 * @return void
	 */
	private function createAndAssociateTags(Service_Component $comp, array $data)
	{
		foreach ($data as $header => $tagStr) {
			if (stripos($header, 'tag') === 0) {   // case-insensitive "starts with"
				$tagStr = trim($tagStr);
				if (!empty($tagStr)) {
					$tag = new Service_Component_Tag();
					$tag->setValue('tag', $tagStr);
					$tag->createIfNew();
					$tagAssoc = new Service_Component_Tagging();
					$tagAssoc->setValue('tagid', $tag->id);
					$tagAssoc->setValue('componentid', $comp->id);
					$tagAssoc->createIfNew();
				}
			}
		}
	}

	public function getTitle()
	{
		return 'Import '.$this->category->toString();
	}

	public function printView()
	{
		if ($this->errors) {
			echo 'Errors found: <br />';
			foreach ($this->errors as $rowNum => $errs) {
				echo 'Row #'.(int)$rowNum.': <ul><li>'
					.implode('</li><li>', array_map(function ($err) { return ents($err); }, $errs))
					.'</li></ul>';
			}
            echo "<hr>";
		}
		
		?>

		<form method="post" enctype="multipart/form-data">
			<div class="form-horizontal">
				<div class="control-group">
					<label class="control-label">
						Data file
					</label>
					<div class="controls">
						<input type="file" name="datafile" />
						(<a href="resources/sample_service_comp_import.csv">Sample file</a>)
					</div>
				</div>
				<div class="control-group">
					<label class="control-label">
						Congregations
					</label>
					<div class="controls">
						<?php
						print_widget('congregationids', Array(
									'type'				=> 'reference',
									'references'		=> 'congregation',
									'show_id'			=> FALSE,
									'order_by'			=> 'meeting_time',
									'allow_empty'		=> false,
									'allow_multiple'	=> true,
									'filter'			=> function($x) {$y = $x->getValue("meeting_time"); return !empty($y);},
							), Array());
						?>
					</div>
				</div>
				<div class="control-group">
					<label class="control-label">
						Duplicate Matching
					</label>
					<div class="controls">
						<label class="checkbox">
							<input type="checkbox" checked="checked" name="dupe-ccli-match" value="1">Re-use existing components with matching CCLI numbers</label>
							<p class="help-inline">When a row's CCLI number matches an existing component, a new component will not be created.  Instead, the existing component will be updated from the CSV and will be linked to the congregations selected above.</p>
						</label>

						<label class="checkbox">
							<input type="checkbox" checked="checked" name="dupe-title-match" value="1">Re-use existing components with matching song titles</label>
							<p class="help-inline">When a row's Title (and if set, Alt_Title) matches an existing component, a new component will not be created.  Instead, the existing component will be updated from the CSV and will be linked to the congregations selected above.</p>
						</label>
					</div>
				</div>
				<div class="control-group">
					<div class="controls">
						<input type="hidden" name="categoryid" value="<?php echo (int)$this->category->id; ?>" />
						<input type="submit" class="btn" value="Import" />
					</div>
				</div>
			</div>
		</form>
		<?php
	}

	function _captureErrors()
	{
		$this->_captured_errors = Array();
		set_error_handler(Array($this, '_handleError'));
	}

	function _handleError($errno, $errstr, $errfile, $errline)
	{
		if (in_array($errno, array(E_USER_NOTICE, E_USER_WARNING, E_NOTICE, E_WARNING))) {
			if (JETHRO_VERSION == 'DEV') {
				$errstr .= ' (Line '.$errline.' of '.$errfile.')';
			}
			$this->_captured_errors[] = $errstr;
		}
	}

	function _getErrors()
	{
		$res = $this->_captured_errors;
		$this->_captured_errors = Array();
		restore_error_handler();
		return $res;
	}

	/**
	 * Given the top CSV row containing headers, and a particular data row, return an associative array mapping lowercase headers to the corresponding data row value. Records an error if $row has non-blank fields not known labelled in $toprow.
	 * @param array $toprow E.g. ["Title", "Length_Mins"]
	 * @param array $row  E.g. ["MySong", "0"]
	 * @return array E.g. ["title" => "MySong", "length_mins" => "0"]
	 */
	private function getLabelledRow(array $toprow, array $row)
	{
		$rowlen = count($row);
		$headerlen = count($toprow);
		if (count($row) > count($toprow)) {
			// More columns in this row than our header declared!
			$excess = array_slice($row, count($toprow), null, false);
			if ($excess) {
				// What's in the 'excess' data columns?
				$onlyBlanks = count(array_filter($excess, function($s) {
					return trim($s) !== '';
				})) === 0;
				if (!$onlyBlanks) {
					// The excess columns have non-blank data. Fail.
					trigger_error('Row has '.count($row).' fields, when only '.count($toprow).' are expected.', E_USER_NOTICE);
				}
			}
		}
		$data = [];
		for ($i=0; $i<min($rowlen, $headerlen); $i++) {
			$data[strtolower($toprow[$i])] = trim($row[$i]);
		}
		return $data;
	}

	/**
	 * Return a Service_Component loaded with an existing Song's data, if the CSV row ($data) matches the CCLI.
	 * @param array $data CSV row data
	 * @return Service_Component|null Null if we don't have a CCLI-matched song
	 */
	private function maybeLoadFromCCLINumber(array $data)
	{
		if ($this->all_by_ccli === null) {
			$this->all_by_ccli = Service_Component::getAllByCCLINumber();
		}
		if (!empty($_REQUEST['dupe-ccli-match'])
			&& !empty($data['ccli_number'])
			&& isset($this->all_by_ccli[$data['ccli_number']])
		) {
			$matchedid = $this->all_by_ccli[$data['ccli_number']];
			return new Service_Component($matchedid);
		}
		return null;
	}

	/**
	 * Return a Service_Component loaded with an existing Song's data, if the CSV row ($data) matches.
	 * @param array $data CSV row data
	 * @return Service_Component|null Null if we don't have a title-matched song.
	 */
	private function maybeLoadFromExistingIfTitleMatches(array $data)
	{
		if ($this->all_by_title === null) {
			$this->all_by_title = Service_Component::getAllByTitle();
		}
		if (!empty($_REQUEST['dupe-title-match'])
			&& !empty($data['title'])
			&& isset($this->all_by_title[$data['title']])
		) {
			$titleMatches = $this->all_by_title[$data['title']];
			foreach ($titleMatches as $titleMatch) {
				$alt = $titleMatch['alt_title'] ?? null;
				$id = $titleMatch['id'] ?? null;
				// If someone initially imports a song with a just Title, then edits the CSV to set Alt_Title and reimports, we want the existing record updated.
				// However if Jethro has a song with Title and Alt_Title already set, and the CSV row matches the title not the Alt_Title, we want a new record created.
				if ($alt) {
					if (strtolower($alt) === strtolower($data['alt_title'])) {
						// Matched both title and alt title.
						return new Service_Component($id);
					} else {
						// Matched the title, but the CSV has a different alt title to ours. Keep going - perhaps another song with the same title matches
					}
				} else {
					// Title matched, and we don't have an alt title to compare, so it's a match
					return new Service_Component($id);
				}
			}

		}
		return null;
	}

	/**
	 * @param $data
	 * @return Service_Component
	 */
	private function createNew(&$data)
	{
		$data['categoryid'] = (int)$_REQUEST['categoryid'];
		$comp = new Service_Component();
		$comp->populate(0, Array());
		return $comp;
	}

}