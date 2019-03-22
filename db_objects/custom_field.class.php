<?php
include_once 'include/db_object.class.php';
class Custom_Field extends db_object
{
	protected $_save_permission_level = PERM_SYSADMIN;

	public function __construct($id=NULL) {
		parent::__construct($id);
	}

	protected static function _getFields()
	{
		return Array(
			'name'	=> Array(
							'type'		=> 'text',
							'width'		=> 30,
							'maxlength'	=> 128,
							'allow_empty'	=> FALSE,
							'initial_cap'	=> TRUE,
							'placeholder'  => 'New field name...',
						),
			'rank'	=> Array(
							'type'			=> 'int',
							'editable'		=> true,
							'allow_empty'	=> false,
						),
			'type'	=> Array(
							'type'		=> 'select',
							'options'  => Array(
											'text' => 'Text',
											'select' => 'Selection',
											'date' => 'Date',
											'link' => 'Link'
										  ),
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
							'title'		=> 'Whether to allow multiple values to be entered for this field',
						   ),
			'show_add_family'=> Array(
							'type'		=> 'select',
							'options'  => Array('No', 'Yes'),
							'default'	=> 0,
							'title'		=> 'Whether to show this field on the add-family page',
						   ),
			'searchable'=> Array(
							'type'		=> 'select',
							'options'  => Array('No', 'Yes'),
							'default'	=> 0,
							'title'		=> 'Whether to include this field in system-wide search',
						   ),
			'heading_before'=> Array(
							'type'		=> 'text',
							'default'	=> '',
							'class'		=> 'heading',
							'width'		=> 30,
							'maxlength'	=> 128,
							'placeholder'  => 'Heading text...',
						   ),
			'divider_before'=> Array(
							'type'		=> 'select',
							'options'  => Array('No', 'Yes'),
							'default'	=> 0,
							'title'		=> 'Whether to show a divider before this field'
						   ),
			'params'	=> Array(
							'type'		=> 'serialise',
							'default'	=> Array(),
						),
			'tooltip'	=> Array(
							'type'		=> 'text',
							'width'		=> 20,
							'height'	=> 3,
							'allow_empty'	=> TRUE,
							'initial_cap'	=> TRUE,
							'placeholder'  => 'Explanatory text...',
							'class' => 'tooltip-text'
						),

		);
	}

	public function populate($id, $details)
	{
		if (isset($details['options'])) {
			$this->_tmp['options'] = $details['options'];
		} else {
			$this->_tmp['options'] = NULL;
		}
		unset($details['options']);
		$res = parent::populate($id, $details);
		if (empty($details['params'])) $details['params'] = Array();
		return $res;
	}

	/**
	 * If this field is of type 'select', get the available options for the field
	 * @return Array(id => label), in ranked order.
	 * @see Custom_Field_Option class.
	 */
	public function getOptions()
	{
		if ($this->getValue('type') != 'select') return Array();
		if (!isset($this->_tmp['options'])) {
			$this->_tmp['options'] = Array();
			$opts = $GLOBALS['system']->getDBObjectData('custom_field_option', Array('fieldid' => $this->id), 'OR', 'rank');
			foreach ($opts as $id => $val) {
				$this->_tmp['options'][$id] = $val['value'];
			}
		}
		return $this->_tmp['options'];
	}

	/**
	 * Print the interface for CONFIGURING this custom field.
	 * @param string $fieldname	Name of this object's field
	 * @param string $prefix	Prefix to use on HTML element names
	 */
	function printFieldInterface($fieldname, $prefix='')
	{
		switch ($fieldname) {
			case 'heading_before_toggle':
				$title = 'Whether to show a heading before this field';
				print_widget($prefix.$fieldname,
						Array('type' => 'checkbox', 'attrs' => Array('title' => $title)),
						!empty($this->values['heading_before']));
				break;
			case 'tooltip_toggle':
				$title = 'Whether to include a tooltip for this field';
				print_widget($prefix.$fieldname,
						Array('type' => 'checkbox', 'attrs' => Array('title' => $title)),
						!empty($this->values['tooltip']));
				break;
			case 'allow_multiple':
			case 'divider_before':
			case 'show_add_family':
			case 'searchable':
				print_widget($prefix.$fieldname,
						Array('type' => 'checkbox', 'attrs' => Array('title' => $this->fields[$fieldname]['title'])),
						$this->values[$fieldname]);
				break;
			case 'params':
				$params = $this->getValue('params');
				$params['options'] = $this->getOptions();
				if ($this->id) {
					$fn = 'printParams'.ucfirst($this->values['type']);
					call_user_func(Array('Custom_Field', $fn), $prefix, $params);
				} else {
					foreach ($this->fields['type']['options'] as $opt => $label) {
						?>
						<div class="field-params" data-params-type="<?php echo $opt; ?>">
							<?php
							$fn = 'printParams'.ucfirst($opt);
							call_user_func(Array('Custom_Field', $fn), $prefix, $params);
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

	/**
	 * Get details of an SQL query to retrieve instances of this object type in bulk.
	 * Overriden to add options to the results.
	 * @param array $params
	 * @param string $logic
	 * @param string $order
	 * @see DB_Object::getInstancesQueryComps()
	 * @return array  Details of how to construct an SQL query
	 */
	public function getInstancesQueryComps($params, $logic, $order)
	{
		$res = parent::getInstancesQueryComps($params, $logic, $order);
		$res['from'] .= ' LEFT JOIN custom_field_option cfo ON cfo.fieldid = custom_field.id';
		$res['select'][] = 'GROUP_CONCAT(CONCAT(cfo.id, "__:__", cfo.value) ORDER BY cfo.rank ASC SEPARATOR ";;;") as options';
		$res['select'][] = 'GROUP_CONCAT(CONCAT(cfo.id, "__:__", cfo.value) ORDER BY cfo.rank DESC SEPARATOR ";;;") as reverseoptions';
		$res['select'][] = 'params';
		$res['group_by'] = 'custom_field.id';
		return $res;
	}

	/**
	 * Get data of the instances of this object type in bulk.
	 * @param array $params
	 * @param string $logic
	 * @param string $order
	 * @return array (objectID => objectDetails)
	 * @see DB_Object::getInstancesData()
	 */
	public function getInstancesData($params, $logic='OR', $order='')
	{
		$res = parent::getInstancesData($params, $logic, $order);
		foreach ($res as $k => $v) {
			if ($v['type'] == 'select') {
				$opts = Array();
				$res[$k]['options'] = NULL;
				$options = array_get($v, 'options', '');
				if (strlen($options)) {
					$pairs =  explode(';;;', $options);
					$revPairs = explode(';;;', $v['reverseoptions']);
					if (reset($pairs) == end($revPairs)) {
						foreach ($pairs as $pair) {
							list($id, $val) = explode('__:__', $pair);
							$opts[$id] = $val;
						}
						$res[$k]['options'] = $opts;
					} else {
						// Too many options, it was truncated
						// That's OK, the options can get loaded on demand later
						$res[$k]['options'] = NULL;
					}
				}
			}
			unset($res[$k]['reverseoptions']);
			$res[$k]['params'] = unserialize($v['params']);
		}
		return $res;
	}

	/**
	 * Print an interface to configure parameters for this field if its type is 'date'
	 * @param string $prefix
	 * @param array $params  Existing params of this field
	 */
	public static function printParamsDate($prefix, $params)
	{
		?>
		<label class="allownote">
			<?php print_widget($prefix.'allow_note', Array('type'=>'checkbox'), array_get($params, 'allow_note')); ?>
			Allow note
		</label>
		<label class="allownote">
			<?php print_widget($prefix.'allow_blank_year', Array('type'=>'checkbox'), array_get($params, 'allow_blank_year')); ?>
			Allow blank year
		</label>
		<?php
	}

	/**
	 * Print an interface to configure parameters for this field if its type is 'text'
	 * @param string $prefix
	 * @param array $params  Existing params of this field
	 */
	public static function printParamsText($prefix, $params)
	{
		?>
		<label>
		Regex:
		<?php
		print_widget($prefix.'regex', Array('type' => 'text', 'attrs' => Array('placeholder' => '(Optional)')), array_get($params, 'regex'));
		?>
		</label>
		<?php
	}

		/**
	 * Print an interface to configure parameters for this field if its type is 'link'
	 * @param string $prefix
	 * @param array $params  Existing params of this field
	 */
	public static function printParamsLink($prefix, $params)
	{
		?>
		<label>
		Template:
		<?php
		print_widget($prefix.'template', Array('type' => 'text', 'attrs' => Array('placeholder' => '(Optional)')), array_get($params, 'template'));
		?>
		</label>
		<div class="smallprint help-inline"><i>The template can be used to convert <br /> the user-entered text into a valid URL. <br />Eg <code>https://www.facebook.com/<strong>%s</strong></code></i></div>
		<?php
	}

	/**
	 * Print an interface to configure parameters for this field if its type is 'select'
	 * @param string $prefix
	 * @param array $params  Existing params of this field
	 */
	public static function printParamsSelect($prefix, $params)
	{
		?>
		<table class="select-options table-condensed expandable reorderable">
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
				$options = array_get($params, 'options', Array());
				$options[''] = '';
				$i = 0;
				foreach ($options as $optionID => $optionValue) {
					?>
					<tr>
						<td class="cursor-move">
							<?php
							if ($optionID !== '') echo $optionID;
							print_hidden_field($prefix.'option_ids[]', $optionID);
							?>
						</td>
						<td>
							<?php print_widget($prefix.'option_values[]', Array('type' => 'text'), $optionValue); ?>
						</td>
						<td class="center">
							<?php
							if ($optionID !== '') {
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
			<tfoot>
				<tr>
					<td colspan="2">
						<label class="radio">
							<?php
							print_widget($prefix.'allow_other', Array('type' => 'checkbox'), array_get($params, 'allow_other', FALSE));
							echo _('Allow "other"');
							?>
						</label>
			</tfoot>
		</table>
		<?php
	}

	/**
	 * Process an interface for CONFIGURING this custom field
	 * @param string $fieldname
	 * @param string $prefix
	 */
	public function processFieldInterface($fieldname, $prefix = '') {
		switch ($fieldname) {
			case 'allow_multiple':
			case 'divider_before':
			case 'show_add_family':
			case 'searchable':
				$this->setValue($fieldname, !empty($_REQUEST[$prefix.$fieldname]));
				break;
			case 'params':
				$val = Array();
				$val['allow_note'] = !empty($_REQUEST[$prefix.'allow_note']);
				$val['allow_blank_year'] = !empty($_REQUEST[$prefix.'allow_blank_year']);
				$val['regex'] = array_get($_REQUEST, $prefix.'regex', '');
				$val['template'] = array_get($_REQUEST, $prefix.'template', '');

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

					$val['allow_other'] = array_get($_REQUEST, $prefix.'allow_other', FALSE);

				}
				$this->setValue($fieldname, $val);

				break;
			default:
				parent::processFieldInterface($fieldname, $prefix);
				break;
		}
	}

	/**
	 * Save a brand new custom field to the DB
	 * @return boolean
	 */
	public function create()
	{
		$GLOBALS['system']->doTransaction('BEGIN');
		parent::create();
		$this->_saveOptions();
		$GLOBALS['system']->doTransaction('COMMIT');
	}

	/**
	 * Save changes to an existing custom field to the DB
	 * @return boolean
	 */
	public function save()
	{
		$GLOBALS['system']->doTransaction('BEGIN');
		if ($this->getValue('type') !== 'text') {
			$this->setValue('searchable', 0);
		}
		parent::save();
		$this->_saveOptions();
		$GLOBALS['system']->doTransaction('COMMIT');
	}

	/**
	 * If this is a select field, save its options to the DB
	 */
	private function _saveOptions()
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

	/**
	 * Get parameters to use for printing an interface for entering a value for this custom field
	 * @return array
	 */
	private function getWidgetParams()
	{
		$params = Array(
					'type' => $this->getValue('type'),
					'allow_empty' => TRUE,
				);
		if ($this->getValue('type') == 'link') {
			$params['type'] = 'text';
			if (empty($this->values['params']['template'])) {
				// thanks to the interweb
				$params['regex'] = "^([a-z][a-z0-9\\*\\-\\.]*):\\/\\/(?:(?:(?:[\\w\\.\\-\\+!$&'\\(\\)*\\+,;=]|%[0-9a-f]{2})+:)*(?:[\\w\\.\\-\\+%!$&'\\(\\)*\\+,;=]|%[0-9a-f]{2})+@)?(?:(?:[a-z0-9\\-\\.]|%[0-9a-f]{2})+|(?:\\[(?:[0-9a-f]{0,4}:)*(?:[0-9a-f]{0,4})\\]))(?::[0-9]+)?(?:[\\/|\\?](?:[\\w#!:\\.\\?\\+=&@!$'~*,;\\/\\(\\)\\[\\]\\-]|%[0-9a-f]{2})*)?$";
			}
		}
		if ($this->getValue('type') == 'select') {
			$options = $GLOBALS['system']->getDBObjectData('custom_field_option', Array('fieldid' => $this->id), 'OR', 'rank');
			foreach ($options as $id => $detail) {
				$params['options'][$id] = $detail['value'];
			}
			if (!empty($this->values['params']['allow_other'])) {
				$params['options']['other'] = _('Other');
			}
		}
		if (!empty($this->values['params']['allow_blank_year'])) $params['allow_blank_year'] = $this->values['params']['allow_blank_year'];
		if (!empty($this->values['params']['regex'])) $params['regex'] = $this->values['params']['regex'];
		return $params;
	}

	/**
	 * Print an interface for an end user to enter a value for this custom field for a person record
	 * @param mixed $value	Existing value
	 * @param array $extraPrams	Any extra params to pass to print_widget.
	 */
	public function printWidget($value, $extraParams=Array(), $prefix='')
	{
		$widgetParams = $this->getWidgetParams();
		$otherValue = '';
		if (($this->getValue('type') == 'select') && strlen($value) && !empty($this->values['params']['allow_other'])) {
			if (!isset($widgetParams['options'][$value])) {
				$otherValue = $value;
				if (0 === strpos($otherValue, '0 ')) $otherValue = substr($otherValue, 2);
				$value = 'other';
			}
		}
		print_widget($prefix.'custom_'.$this->id.'[]', $extraParams+$widgetParams, $value);
		if (($this->getValue('type') == 'date') && !empty($this->values['params']['allow_note'])) {
			$note = substr($value, 11);
			print_widget($prefix.'custom_'.$this->id.'_note[]', Array('type' => 'text', 'placeholder' => '(Note)'), $note);
		}
		if (($this->getValue('type') == 'select') && !empty($this->values['params']['allow_other'])) {
			print_widget($prefix.'custom_'.$this->id.'_other[]', Array('type' => 'text', 'class' => 'select-other'), $otherValue);
		}
		if (strlen($this->values['tooltip'])) {
			?>
			<i class="clickable icon-question-sign" data-toggle="visible" data-target="#tooltip<?php echo $this->id; ?>"></i>
			<div class="help-block custom-field-tooltip" id="tooltip<?php echo $this->id; ?>"><?php echo nl2br(ents($this->values['tooltip'])); ?></div>
			<?php
		}
	}

	/**
	 * Process an interface where an end user supplies a value for this custom field for a person record
	 * @return mixed
	 */
	public function processWidget($prefix='')
	{
		$res = process_widget($prefix.'custom_'.$this->id, $this->getWidgetParams(), NULL, TRUE);
		if (($this->getValue('type') == 'date') && !empty($this->values['params']['allow_note'])) {
			$notes = process_widget($prefix.'custom_'.$this->id.'_note', Array('type' => 'text'), NULL, TRUE);
			foreach ((array)$notes as $k => $v) {
				if (!empty($res[$k]) && strlen($v)) $res[$k] .= ' '.$v;
			}
		}
		if (($this->getValue('type') == 'select') && !empty($this->values['params']['allow_other'])) {
			if (!empty($res)) {
				foreach ($res as $k => $v) {
					if ($v == 'other') {
						$res[$k] = '0 '.$_REQUEST[$prefix.'custom_'.$this->id.'_other'][$k];
					}
				}
			}
		}
		if (is_array($res)) $res = array_remove_empties($res);
		return $res;
	}

	/**
	 * Format a value of this field into a human readable form
	 * (ie. dates formatted, option IDs rendered as option labels, etc).
	 * @param mixed $val	Raw value
	 * @return string
	 */
	public function formatValue($val)
	{
		if (is_array($val)) return implode(', ', array_map(Array($this, 'formatValue'), $val));
		if (!strlen($val)) return '';

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
				if (!empty($this->values['params']['allow_other'])) {
					if (0 === strpos($val, '0 ')) $val = substr($val, 2);
				}
				return array_get($this->getOptions(), $val, $val);
				break;
			case 'link':
				$template = array_get($this->values['params'], 'template', '');
				if (strlen($template)) {
					return sprintf($template, $val);
				}
				// fallthrough..
			default:
				return $val;
				break;
		}
	}

	/**
	 * Print the HTML for this field's value, eg with <a> tags for links
	 * @param type $val
	 */
	public function printFormattedValue($val)
	{
		if ($this->getValue('type') == 'link') {
			$template = array_get($this->values['params'], 'template', '');
			if (strlen($template)) {
					$url = sprintf($template, $val);
			} else {
				$url = $val;
			}
			echo '<a target="_blank" href="'.ents($url).'">'.ents($val).'</a>';
		} else {
			echo ents($this->formatValue($val));
		}
	}

	/**
	 * Given a string with a value, convert it to appropriate format for storage
	 * In particular, this looks up the option ID for option fields
	 * @see Person::fromCsvRow()
	 */
	public function parseValue($val)
	{
		switch ($this->getValue('type')) {
			case 'date':
				if (!preg_match('/(([-0-9]{4})?-([0-9]{2}-[0-9]{2}))( (.*))?/', $val, $matches)) {
					trigger_error("Badly formed date value '$val'. Dates must be YYYY-MM-DD");
					return NULL;
				}
				break;
			case 'select':
				$lowerVal = strtolower($val);
				foreach ($this->getOptions() as $id => $label) {
					if (strtolower($label) == $lowerVal) {
						return $id;
					}
				}
				if (!empty($this->values['params']['allow_other'])) {
					return '0 '.$val;
				}
				trigger_error("Invalid option '$val'. ".$this->getValue('name')." must be {".implode(',', $this->getOptions())."})");
				return NULL;
		}
		return $val;
	}

	/**
	 * Get SQL expression to retrieve a value suitable for use by formatValue() above.
	 * @param string $valueTableAlias  Alias of the custom_field_value table in the SQL statement
	 * @param string $fieldTableAlias Alias of the custom_field table in the SQL statement
	 * @return string SQL
	 */
	public static function getRawValueSQLExpr($valueTableAlias, $fieldTableAlias)
	{
		return 'TRIM(CONCAT(COALESCE('.$valueTableAlias.'.value_optionid, CONCAT('.$valueTableAlias.'.value_date, " "), ""), COALESCE(CONCAT(IF('.$fieldTableAlias.'.type="select", "0 ", ""), '.$valueTableAlias.'.value_text), "")))';
	}

	/**
	 * Get SQL expression to retrieve a value suitable for sorting.
	 * (ie option fields' RANKs are returned instead of option id or option label).
	 * @param string $tableAlias  Alias of the custom_field_value table in the SQL statement
	 * @return string SQL
	 */
	public static function getSortValueSQLExpr($dataTableAlias, $optionTableAlias)
	{
		return 'COALESCE('.$optionTableAlias.'.rank, '.$dataTableAlias.'.value_date, '.$dataTableAlias.'.value_text)';
	}
}