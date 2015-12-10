<?php
include_once 'include/db_object.class.php';
class Custom_Field extends db_object
{
	var $_save_permission_level = PERM_SYSADMIN;

	public function __construct($id=NULL) {
		parent::__construct($id);
	}

	function _getFields()
	{
		return Array(
			'name'	=> Array(
							'type'		=> 'text',
							'width'		=> 30,
							'maxlength'	=> 128,
							'allow_empty'	=> FALSE,
							'initial_cap'	=> TRUE,
						),
			'rank'	=> Array(
							'type'			=> 'int',
							'editable'		=> true,
							'allow_empty'	=> false,
						),
			'type'	=> Array(
							'type'		=> 'select',
							'options'  => Array('text' => 'Text', 'select' => 'Selection', 'date' => 'Date'),
							'default'	=> 'text',
							'attrs'		=> Array(
											'data-toggle' => 'visible',
											'data-target' => 'row .field-params',
											'data-match-attr' => 'data-params-type'
											),
						   ),
			'allow_multiple'=> Array(
							'type'		=> 'select',
							'options'  => Array('No', 'Yes'),
							'default'	=> 0,
						   ),
			'params'	=> Array(
							'type'		=> 'serialise',
							'default'	=> Array(),
						),
		);
	}

	public function populate($id, $details)
	{
		if (!empty($details['options'])) {
			$this->_tmp['options'] = $details['options'];
		}
		unset($details['options']);
		return parent::populate($id, $details);
	}
	
	public function getOptions()
	{
		if (!isset($this->_tmp['options'])) {
			$this->_tmp['options'] = $GLOBALS['system']->getDBObjectData('custom_field_option', Array('fieldid' => $this->id), 'OR', 'rank');
		}
		return $this->_tmp['options'];
	}



	function printFieldInterface($fieldname, $prefix='')
	{
		switch ($fieldname) {
			case 'allow_multiple':
				print_widget($prefix.$fieldname, Array('type' => 'checkbox'), $this->values[$fieldname] );
				break;
			case 'params':
				if ($this->id) {
					$fn = 'printParams'.ucfirst($this->values['type']);
					$this->$fn($prefix);
				} else {
					foreach ($this->fields['type']['options'] as $opt => $label) {
						?>
						<div class="field-params" data-params-type="<?php echo $opt; ?>">
							<?php
							$fn = 'printParams'.ucfirst($opt);
							$this->$fn($prefix);
							?>
						</div>
						<?php
					}
				}
				break;
			default:
				parent::printFieldInterface($fieldname, $prefix);
		}
	}
	public function getInstancesQueryComps($params, $logic, $order)
	{
		$res = parent::getInstancesQueryComps($params, $logic, $order);
		$res['from'] .= ' LEFT JOIN custom_field_option cfo ON cfo.fieldid = custom_field.id';
		$res['select'][] = 'GROUP_CONCAT(CONCAT(cfo.id, "__:__", cfo.value) ORDER BY cfo.rank SEPARATOR ";;;") as options';
		$res['select'][] = 'params';
		$res['group_by'] = 'custom_field.id';
		return $res;
	}

	public function getInstancesData($params, $logic='OR', $order='')
	{
		$res = parent::getInstancesData($params, $logic, $order);
		foreach ($res as $k => $v) {
			$opts = Array();
			if ($v['type'] == 'select') {
				foreach (explode(';;;', $v['options']) as $pair) {
					list($id, $val) = explode('__:__', $pair);
					$opts[$id] = $val;
				}
			}
			$res[$k]['options'] = $opts;
		}
		$res[$k]['params'] = unserialize($v['params']);
		return $res;
	}
	
	function printParamsDate($prefix)
	{
		?>
		<label class="allownote">
			<?php print_widget($prefix.'allow_note', Array('type'=>'checkbox'), array_get($this->values['params'], 'allow_note')); ?>
			Allow note
		</label>
		<label class="allownote">
			<?php print_widget($prefix.'allow_blank_year', Array('type'=>'checkbox'), array_get($this->values['params'], 'allow_blank_year')); ?>
			Allow blank year
		</label>
		<?php
	}

	function printParamsText($prefix)
	{
		?>
		<label>
		Regex:
		<?php
		print_widget($prefix.'regex', Array('type' => 'text', 'attrs' => Array('placeholder' => '(Optional)')), array_get($this->values['params'], 'regex'));
		?>
		</label>
		<?php
	}

	function printParamsSelect($prefix)
	{
		?>
		<table class="select-options table-condensed expandable">
			<thead>
				<tr>
					<td colspan="2">
						Options: &nbsp;
						<small><i>(Drag to re-order)</i></small>
					</td>
					<td><i class="icon-trash"></i></td>
				</tr>
			</thead>
			<tbody>
				<?php
				$options = $this->getOptions();
				$options[''] = Array('value' => '');
				$i = 0;
				foreach ($options as $optionID => $optionDetails) {
					?>
					<tr>
						<td>
							<?php
							if ($optionID) echo $optionID;
							print_hidden_field($prefix.'option_ids[]', $optionID);
							?>
						</td>
						<td>
							<?php print_widget($prefix.'option_values[]', Array('type' => 'text'), $optionDetails['value']); ?>
						</td>
						<td class="center">
							<?php
							if ($optionID) {
								?>
								<input type="checkbox" name="<?php echo $prefix; ?>options_delete[]" value="<?php echo $optionID; ?>"
								data-toggle="strikethrough" data-target="row"
								title="Click to delete this option" />
								<?php
							}
							?>
						</td>
					</tr>
					<?php
					$i++;
				}
				?>
			</tbody>
		</table>
		<?php
	}

	public function processFieldInterface($fieldname, $prefix = '') {
		switch ($fieldname) {
			case 'allow_multiple':
				$this->setValue('allow_multiple', !empty($_REQUEST[$prefix.$fieldname]));
				break;
			case 'params':
				$val = Array();
				$val['allow_note'] = !empty($_REQUEST[$prefix.'allow_note']);
				$val['allow_blank_year'] = !empty($_REQUEST[$prefix.'allow_blank_year']);
				$val['regex'] = array_get($_REQUEST, $prefix.'regex', '');
				$this->setValue($fieldname, $val);

				// Also process the options for select fields:
				if (!empty($_REQUEST[$prefix.'option_ids'])) {
					$newOptions = $updateOptions = $deleteOptions = Array();
					foreach ($_REQUEST[$prefix.'option_ids'] as $rank => $optionID) {
						$value = $_REQUEST[$prefix.'option_values'][$rank];
						if ($optionID) {
							// Update
							if (in_array($optionID, array_get($_REQUEST, $prefix.'options_delete', Array()))) {
								$deleteOptions[] = $GLOBALS['system']->getDBObject('custom_field_option', $optionID);
							} else {
								$updateOptions[$optionID] = $GLOBALS['system']->getDBObject('custom_field_option', $optionID);
								$updateOptions[$optionID]->setValue('value', $value);
								$updateOptions[$optionID]->setValue('rank', $rank);
							}
						} else {
							// New
							if (strlen($value)) {
								$newOption = new Custom_Field_Option();
								$newOption->setValue('value', $value);
								$newOption->setValue('rank', $rank);
								$newOptions[] = $newOption;
							}
						}
					}
					$this->_tmp['new_options'] = $newOptions;
					$this->_tmp['update_options'] = $updateOptions;
					$this->_tmp['delete_options'] = $deleteOptions;
				}

				break;
			default:
				parent::processFieldInterface($fieldname, $prefix);
				break;
		}
	}

	public function save()
	{
		$GLOBALS['system']->doTransaction('BEGIN');
		parent::save();
		$this->saveOptions();
		$GLOBALS['system']->doTransaction('COMMIT');
	}
	
	public function create()
	{
		$GLOBALS['system']->doTransaction('BEGIN');
		parent::create();
		$this->saveOptions();
		$GLOBALS['system']->doTransaction('COMMIT');		
	}

	private function saveOptions()
	{
		if (!empty($this->_tmp['delete_options'])) {
			foreach ($this->_tmp['delete_options'] as $id => $obj) {
				$obj->acquireLock();
				$obj->delete();
			}
		}
		if (!empty($this->_tmp['update_options'])) {
			foreach ($this->_tmp['update_options'] as $id => $obj) {
				$obj->acquireLock();
				$obj->save();
				$obj->releaseLock();
			}
		}
		if (!empty($this->_tmp['new_options'])) {
			foreach ($this->_tmp['new_options'] as $obj) {
				$obj->setValue('fieldid', $this->id);
				$obj->create();
			}
		}
	}

	private function getWidgetParams()
	{
		$params = Array(
					'type' => $this->getValue('type'),
					'allow_empty' => TRUE,
				);
		if ($this->getValue('type') == 'select') {
			$options = $GLOBALS['system']->getDBObjectData('custom_field_option', Array('fieldid' => $this->id), 'OR', 'rank');
			foreach ($options as $id => $detail) {
				$params['options'][$id] = $detail['value'];
			}
		}
		if (!empty($this->values['params']['allow_blank_year'])) $params['allow_blank_year'] = $this->values['params']['allow_blank_year'];
		if (!empty($this->values['params']['regex'])) $params['regex'] = $this->values['params']['regex'];
		return $params;
	}

	/**
	 * Print widget for an end user to enter a value for this field for a person record
	 */
	public function printWidget($value)
	{
		print_widget('custom_'.$this->id.'[]', $this->getWidgetParams(), $value);
		if (($this->getValue('type') == 'date') && !empty($this->values['params']['allow_note'])) {
			$bits = explode(' ', $value);
			$note = array_get($bits, 1);
			print_widget('custom_'.$this->id.'_note[]', Array('type' => 'text', 'placeholder' => '(Note)'), $note);
		}
	}

	public function processWidget()
	{
		$res = process_widget('custom_'.$this->id, $this->getWidgetParams());
		if (($this->getValue('type') == 'date') && !empty($this->values['params']['allow_note'])) {
			$notes = process_widget('custom_'.$this->id.'_note', Array('type' => 'text'));
			foreach ((array)$notes as $k => $v) {
				if (!empty($res[$k])) $res[$k] .= ' '.$v;
			}
		}
		return $res;
	}

	public function formatValue($val)
	{
		if (is_array($val)) return implode(', ', array_map(Array($this, 'formatValue'), $val));
		switch ($this->getValue('type')) {
			case 'date':
				if (!preg_match('/(([-0-9]{4})?-([0-9]{2}-[0-9]{2}))( (.*))?/', $val, $matches)) {
					return "! Malformed value $val";
				}
				$res = format_date($matches[1]);
				if (!empty($matches[5])) $res .= '  ('.$matches[5].')';
				return $res;
				break;
			case 'select':
				if (empty($val)) return '';
				return array_get($this->getOptions(), $val, '(Invalid option)');
				break;
			default:
				return $val;
				break;
		}

	}

	/**
	 * Get SQL expression to retrieve a value suitable for use by formatValue() above.
	 * @param string $tableAlias  Alias of the custom_field_value table in the SQL statement
	 * @return string SQL
	 */
	public static function getRawValueSQLExpr($tableAlias)
	{
		return 'TRIM(CONCAT(COALESCE('.$tableAlias.'.value_optionid, CONCAT('.$tableAlias.'.value_date, " "), ""), COALESCE('.$tableAlias.'.value_text, "")))';
	}

	/**
	 * Get SQL expression to retrieve a value suitable for sorting
	 * (ie option fields' RANKs are returned instead of option id or option label).
	 * @param string $tableAlias  Alias of the custom_field_value table in the SQL statement
	 * @return string SQL
	 */
	public static function getSortValueSQLExpr($dataTableAlias, $optionTableAlias)
	{
		return 'COALESCE('.$optionTableAlias.'.rank, '.$dataTableAlias.'.value_date, '.$dataTableAlias.'.value_text)';
	}
}
?>
