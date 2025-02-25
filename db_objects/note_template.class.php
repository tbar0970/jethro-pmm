<?php
include_once 'include/db_object.class.php';
class Note_Template extends db_object
{
	public $_save_permission_level = PERM_SYSADMIN;

	private $_fields = Array();
	private $_fields_to_delete = Array();

	private $_field_values = Array();

	protected static function _getFields()
	{
		return Array(
			'name'	=> Array(
							'type'		=> 'text',
							'width'		=> 30,
							'maxlength'	=> 128,
							'allow_empty'	=> FALSE,
							'initial_cap'	=> TRUE,
							'label' => 'Template name',
						),
			'subject' => Array(
							'type'		=> 'text',
							'width'		=> 30,
							'maxlength'	=> 128,
							'allow_empty'	=> FALSE,
							'initial_cap'	=> TRUE,
							'label' => 'Note Subject',
							'divider_before' => true,
						),
		);
	}

	/**
	 * Print the form for CONFIGURING this note template.
	 * @see DB_Object::printForm()
	 * @param string $prefix
	 */
	function printForm($prefix='', $fields=NULL)
	{
		$this->fields['fields'] = Array();
		parent::printForm();
		unset($this->fields['fields']);
	}

	/**
	 * Process the form for CONFIGURING this note template.
	 * @see DB_Object::processForm()
	 * @param string $prefix
	 * @param array|NULL $fields
	 */
	function processForm($prefix='', $fields=NULL) {
		$this->fields['fields'] = Array();
		$res = parent::processForm($prefix, $fields);
		unset($this->fields['fields']);
		return $res;
	}

	/**
	 * Print an interface for CONFIGURING this note template
	 *
	 * @param string $fieldname
	 * @param string $prefix
	 */
	function printFieldInterface($fieldname, $prefix='')
	{
		switch ($fieldname) {
			case 'fields':
				$this->_printFieldsConfiguration();
				break;
			default:
				parent::printFieldInterface($fieldname, $prefix);
		}
	}

	/**
	 * Print the interface for configuring the fields WITHIN this note template
	 */
	private function _printFieldsConfiguration()
	{
		$fields = $GLOBALS['system']->getDBObjectData('note_template_field', Array('templateid' => $this->id), 'OR', 'rank');

		$fieldTypeParams = Array(
			'type' => 'select',
			'options' => Array(
							'custom' => 'Person Field',
							'independent' => 'Independent Field',
						),
			'attrs' => Array(
				'data-toggle' => 'visible',
				'data-target' => 'row td.template-field-props>*',
				'data-match-attr' => 'data-fieldtype',
			)
		);
		?>
		<table class="table expandable reorderable">
			<thead>
				<tr>
					<th class="narrow">ID</th>
					<th class="narrow">Type</th>
					<th>Name</th>
					<th>Details</th>
					<th class="narrow"><i class="icon-trash"></i></th>
				</tr>
			</thead>
			<tbody>

			<?php
			$i = 0;
			$dummyField = new Note_Template_Field();

			// Hack this field because we don't want 'empty' in the dropdown.
			$dummyField->fields['customfieldid']['allow_empty'] = FALSE;

			$fields += Array(0 => Array());
			foreach ($fields as $id => $field) {
				$prefix = 'fields_'.$i.'_';
				$dummyField->populate($id, $field);
				$dummyField->acquireLock();
				if (!$id) $fieldTypeParams['options'] = Array('' => '--Choose new field type--') + $fieldTypeParams['options'];
				?>
				<tr>
					<td class="narrow cursor-move">
						<?php
						print_hidden_field('index[]', $i);
						print_hidden_field($prefix.'id', $id);
						if ($id) echo $id;
						?>
					</td>
					<td class="narrow">
						<?php
						$fieldType = $id ? (array_get($field, 'customfieldid') ? 'custom' : 'independent') : '';
						print_widget($prefix.'fieldtype', $fieldTypeParams, $fieldType);
						?>
					</td>
					<td class="template-field-props">
						<div data-fieldtype="custom">
							<?php $dummyField->printFieldInterface('customfieldid', $prefix); ?>
						</div>

						<div data-fieldtype="independent">
							<?php
							$dummyField->printFieldInterface('label', $prefix);
							?>
						</div>
					</td>
					<td class="template-field-props">
						<div data-fieldtype="independent">
							&nbsp;Type:
							<?php $dummyField->printFieldInterface('type', $prefix); ?>
							<br />
							<?php $dummyField->printFieldInterface('params', $prefix); ?>
						</div>

					</td>
					<td>
						<?php
						if ($id) {
							?>
							<input type="checkbox" name="<?php echo $prefix; ?>delete" data-toggle="strikethrough" data-target="row" value="<?php echo $id; ?>" />
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
		<small>When someone uses this template to add a note to a person, they will be prompted to enter values for these fields.<br />
			Values for "independent" fields are saved only within the note itself. <br />
			Values for "person" fields are saved within the note and will also update the corresponding
			<a href="<?php build_url(Array('view' => 'admin__custom_fields')); ?>">custom field</a>
			in the person record.</small>
		<?php
	}

	/**
	 * Process an interface for CONFIGURING this note template
	 * @param string $fieldname
	 * @param string $prefix
	 */
	public function processFieldInterface($fieldname, $prefix='') {
		switch ($fieldname) {
			case 'fields':
				$ranks = array_flip(array_get($_REQUEST, 'index', Array()));
				$i = 0;
				while (isset($_REQUEST['fields_'.$i.'_fieldtype'])) {
					$prefix = 'fields_'.$i.'_';
					if (!empty($_REQUEST[$prefix.'delete'])) {
						$this->_fields_to_delete[] = $_REQUEST[$prefix.'delete'];
					} else if (!empty($_REQUEST['fields_'.$i.'_fieldtype'])) {
						$fieldObj = new Note_Template_Field($_REQUEST[$prefix.'id']);
						$fieldObj->setValue('rank', $ranks[$i]);
						if ($_REQUEST[$prefix.'fieldtype'] == 'custom') {
							// Set the customfieldid only
							$fieldObj->processFieldInterface('customfieldid', $prefix);
						} else {
							// Set everything except the customfieldid
							$fieldObj->processForm($prefix);
							$fieldObj->setValue('customfieldid', NULL);
						}
						if ($fieldObj->getValue('customfieldid') || $fieldObj->getValue('label')) {
							$this->_fields[] = $fieldObj;
						}
					}
					$i++;
				}
				break;
			default:
				parent::processFieldInterface($fieldname, $prefix);
				break;
		}
	}


	/**
	 * Save a brand new note template to the DB
	 * @return boolean
	 */
	public function create()
	{
		$GLOBALS['system']->doTransaction('BEGIN');
		if (parent::create()) {
			$this->_saveFields();
			$GLOBALS['system']->doTransaction('COMMIT');
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Save changes to an existing note template to the DB
	 * @return boolean
	 */
	public function save()
	{
		$GLOBALS['system']->doTransaction('BEGIN');
		if (parent::save()) {
			$this->_saveFields();
			$GLOBALS['system']->doTransaction('COMMIT');
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Save the configuration of the note fields within this template
	 */
	private function _saveFields()
	{
		$dummy = new Note_Template_Field();
		foreach ($this->_fields_to_delete as $fieldid) {
			$dummy->populate($fieldid, Array());
			$dummy->delete();
		}
		foreach ($this->_fields as $field) {
			$field->setValue('templateid', $this->id);
			if ($field->id) {
				$field->save();
			} else {
				$field->create();
			}
		}
	}

	/**
	 * Print interface for POPULATING the fields within this note template
	 * (used when adding a note to a person)
	 */
	public function printNoteFieldWidgets()
	{
		$fields = $GLOBALS['system']->getDBObjectData('note_template_field', Array('templateid' => $this->id), 'OR', 'rank');
		?>
		<div class="note-template-fields">
			<?php
			foreach ($fields as $id => $details) {
				?>
				<div class="control-group">
					<label class="control-label">
						<?php echo ents($details['customfieldid'] ? $details['customfieldname'] : $details['label']); ?>
					</label>
					<div class="controls">
						<?php
						if ($details['customfieldid']) {
							$f = $GLOBALS['system']->getDBObject('custom_field', $details['customfieldid']);
							$f->printWidget(NULL, Array('allow_empty' => FALSE, 'default_empty' => TRUE));
						} else {
							$params = unserialize($details['params']);
							$params['type'] = $details['type'];
							$params['allow_empty'] = FALSE;
							$params['default_empty'] = TRUE;
							print_widget('template_field_'.$id, $params, NULL);
						}
						?>
					</div>
				</div>
				<?php
			}
			?>
		</div>
		<?php
	}

	/**
	 * Process the interface for POPULATING fields within this note template
	 * (used when adding a note to a person)
	 */
	public function processNoteFieldWidgets()
	{
		$fields = $GLOBALS['system']->getDBObjectData('note_template_field', Array('templateid' => $this->id), 'OR', 'rank');
		foreach ($fields as $id => $details) {
			if ($details['customfieldid']) {
				$cf = $GLOBALS['system']->getDBObject('custom_field', $details['customfieldid']);
				$this->_field_values[$id] = $cf->processWidget();
			} else {
				$params = unserialize($details['params']);
				$params['type'] = $details['type'];
				$this->_field_values[$id] = process_widget('template_field_'.$id, $params);;
			}
		}
	}

	/**
	 * Returns whether or not this note template refers to and sets custom person fields
	 * (rather than just standalone fields within the note)
	 * @return boolean
	 */
	public function usesCustomFields()
	{
		$fields = $GLOBALS['system']->getDBObjectData('note_template_field', Array('templateid' => $this->id), 'OR', 'rank');
		foreach ($fields as $id => $details) {
			if ($details['customfieldid']) return TRUE;
		}
		return FALSE;
	}

	/**
	 * Apply the values of this template's fields to a person record's custom fields (where applicable).
	 * Used when saving a note.
	 * @param Person $person  The person record to set custom values on.
	 */
	public function applyFieldValues($person)
	{
		$fields = $GLOBALS['system']->getDBObjectData('note_template_field', Array('templateid' => $this->id), 'OR', 'rank');
		foreach ($fields as $id => $details) {
			if ($details['customfieldid']) {
				$person->setCustomValue($details['customfieldid'], $this->_field_values[$id], FALSE);
			}
		}
	}

	/**
	 * Add a block of text to the top of the specified note showing the values supplied for this template's fields
	 * Used when saving a note.
	 * @param Person_Note $note	The note object to append text to.
	 */
	public function applyDataBlock($note)
	{
		$res = '';
		$fields = $GLOBALS['system']->getDBObjectData('note_template_field', Array('templateid' => $this->id), 'OR', 'rank');
		$maxLength = 0;
		foreach ($fields as $id => $details) {
			$params = unserialize($details['params']);
			$params['type'] = $details['type'];
			$line = ($details['customfieldid'] ? $details['customfieldname'] : $details['label']).': ';
			if ($details['customfieldid']) {
				$cf = $GLOBALS['system']->getDBObject('custom_field', $details['customfieldid']);
				$line .= $cf->formatValue($this->_field_values[$id]);
			} else {
				$line .= format_value($this->_field_values[$id], $params);
			}
			$maxLength = max($maxLength, strlen($line));
			$res .= $line."\n";
		}
		$divider = str_repeat('-', (int)($maxLength*1.6));
		$res = $res.$divider."\n";
		$note->setValue('details', $res.$note->getValue('details'));
	}

	public static function printTemplateChooserRow($selectedID)
	{
		$templates = $GLOBALS['system']->getDBObjectData('note_template', Array(), 'OR', 'name');
		if ($templates) {
			$templateParams = Array(
								'type' => 'select',
								'options' => Array(NULL => '(No template)'),
								'attrs' => Array('id' => 'note_template_chooser')
							 );
			foreach ($templates as $id => $tpl)  $templateParams['options'][$id] = $tpl['name'];
			?>
			<div class="control-group">
				<label class="control-label">Note Template</label>
				<div class="controls">
					<?php
					print_widget('note_template_id', $templateParams, $selectedID);
					?>
				</div>
			</div>
			<hr />
			<?php
		}
	}
}
