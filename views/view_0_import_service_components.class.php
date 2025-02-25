<?php
require_once 'abstract_view_add_object.class.php';
class View__Import_Service_Components extends View
{
	private $errors = Array();
	private $category = null;
	protected $_captured_errors;

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
			$comp = new Service_Component();
			$fp = fopen($_FILES['datafile']['tmp_name'], 'r');
			if (!$fp) {
				trigger_error("Your data file could not be read.  Please check the file and try again");
				return;
			}
			$toprow = fgetcsv($fp, 0, ",", '"');
			$rowNum = 1;
			$all_ccli = Service_Component::getAllByCCLINumber();
			while ($row = fgetcsv($fp, 0, ",", '"')) {
				$comp->populate(0, Array());
				$this->_captureErrors();
				$data = Array();
				foreach ($row as $k => $v) {
					$data[strtolower($toprow[$k])] = $v;
				}
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
	
				if (!empty($_REQUEST['dupe-match'])
					&& !empty($data['ccli_number'])
					&& isset($all_ccli[$data['ccli_number']])
				) {
					$comp->load($all_ccli[$data['ccli_number']]);
					$comp->fromCSVRow($data);
					foreach ($_REQUEST['congregationids'] as $congid) {
						$comp->addCongregation($congid);
					}
					$comp->save();

				} else {
					$data['categoryid'] = (int)$_REQUEST['categoryid'];
					$comp->fromCSVRow($data);
					if ($errors = $this->_getErrors()) {
						$this->errors[$rowNum] = $errors;
					} else {
						foreach ($_REQUEST['congregationids'] as $congid) {
							$comp->addCongregation($congid);
						}
						$comp->create();
					}
				}
				$rowNum++;
			}
			if (empty($this->errors)) {
				$GLOBALS['system']->doTransaction('COMMIT');
				add_message(($rowNum-1).' rows imported successfully');
				redirect('services__component_library'); // exits
			} else {
				add_message("Errors were found in the CSV file.  Import has not been performed.  Please correct the errors and try again", 'error');
				$GLOBALS['system']->doTransaction('ROLLBACK');
			}
			fclose($fp);
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
			foreach ($this->errors as $rowNum => $errors) {
				echo 'Row #'.$rowNum.':';
				echo '<ul><li>'.implode('</li></li>', $errors).'</li></ul>';
			}
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
							<input type="checkbox" checked="checked" name="dupe-match" value="1">Re-use existing components with matching CCLI numbers</label>
							<p class="help-inline">This option means that when a row's CCLI number matches an existing component, a new component will not be created.  Instead, the existing component will be updated from the CSV and will be linked to the congregations selected above.</p>
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
}