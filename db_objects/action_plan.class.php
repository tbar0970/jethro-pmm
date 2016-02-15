<?php
include_once 'include/db_object.class.php';
include_once 'db_objects/action_plan_note.class.php';
class Action_Plan extends DB_Object
{
	function getInitSQL()
	{
		return "
			CREATE TABLE action_plan (
			  id int(11) NOT NULL auto_increment,
			  name varchar(255) not null,
			  actions text not null,
			  default_on_create_family tinyint(1) unsigned,
			  default_on_add_person tinyint(1) unsigned,
			  modified datetime not null,
			  modifier int(11) not null,
			  PRIMARY KEY  (`id`)
			) ENGINE=InnoDB;
		";
	}


	protected static function _getFields()
	{
		return Array(
			'name'				=> Array(
									'type'		=> 'text',
									'width'		=> 30,
									'maxlength'	=> 128,
									'allow_empty'	=> false,
									'initial_cap'	=> true,
								   ),
			'actions'			=> Array(
									'type'			=> 'serialise',
									'editable'		=> false,
									'show_in_summary'	=> false,
									'default'		=> Array(),
								   ),
			'default_on_create_family' => Array(
									'type'			=> 'select',
									'options'		=> Array('No', 'Yes'),
									'default'		=> 0,
								   ),
			'default_on_add_person' => Array(
									'type'			=> 'select',
									'options'		=> Array('No', 'Yes'),
									'default'		=> 0,
								   ),
			'modified'			=> Array(
									'type'			=> 'datetime',
									'editable'		=> false,
									'show_in_summary'	=> false,
								   ),
			'modifier'			=> Array(
									'type'			=> 'reference',
									'editable'		=> false,
									'references'	=> 'person',
									'show_in_summary'	=> false,
								   ),

		);
	}

	function toString()
	{
		return $this->values['name'];
	}

	function _printNoteForm($data, $i) {
		$note = new Action_Plan_Note();
		$note->populate(0, $data);
		$note->printForm('note_'.$i.'_');
	}

	function _processNoteForm($i) {
		if (!empty($_POST['note_'.$i.'_subject']) || !empty($_POST['note_'.$i.'_details'])) {
			$note = new Action_Plan_Note();
			$note->processForm('note_'.$i.'_');
			if (!empty($note->values['subject'])) {
				return $note->values;
			}
		}
		return null;
	}

	function printForm()
	{
		$GLOBALS['system']->includeDBClass('person_group');
		$actions = $this->getValue('actions');
		$notes = array_get($actions, 'notes');
		if (empty($notes)) {
			$notes = Array(Array()); // 1 blank note to start us off
		}
		?>
		<table class="action-plan">
			<tbody>
				<tr>
					<th>Name</th>
					<td><?php $this->printFieldInterface('name'); ?></td>
				</tr>
				<tr>
					<th>Notes</th>
					<td>
						When this plan is executed, add these notes to the person/family:
						<table class="expandable">
						<?php
						foreach ($notes as $i => $note) {
							?>
							<tr>
								<td>
									<div class="well">
									<?php
									$this->_printNoteForm($note, $i); 
									?>
									</div>
								</td>
							</tr>
							<?php
						}
						?>
						</table>
					</td>
				</tr>
				<tr>
					<th>Groups</th>
					<td>
						When this plan is executed, <b>add</b> the persons / famiy members to these groups:
						<?php
						Person_Group::printMultiChooser('groups', array_get($actions, 'groups', Array()), Array(), TRUE);
						?>
					</td>
				</tr>
				<tr>
					<th></th>
					<td>
						When this plan is executed, <b>remove</b> the persons / famiy members from these groups:
						<?php
						Person_Group::printMultiChooser('groups_remove', array_get($actions, 'groups_remove', Array()), Array(), TRUE);
						?>
					</td>
				</tr>
				<tr>
					<th>Fields</th>
					<td>
						When this plan is executed, for each person / family member:
						<table class="fields">
						<?php
						$fields = array_get($actions, 'fields', Array());
						$dummy = new Person();
						foreach (Array('congregationid', 'status', 'age_bracket') as $field) {
							$value = '';
							$addToExisting = FALSE;
							$v = array_get($fields, $field);
							if ($v) {
								$value = $v['value'];
								$addToExisting = $v['add'];
							}
							$dummy->fields[$field]['allow_empty'] = TRUE;
							$dummy->fields[$field]['empty_text'] = '(No change)';
							if (strlen($value)) $dummy->setValue($field, $value);
							echo '<tr><td>';
							print_widget('fields_enabled['.$field.']', Array('type'=>'checkbox'), strlen($value));
							echo 'Set '.$dummy->getFieldLabel($field).' </td><td>';
							$dummy->printFieldInterface($field);
							echo '</td></tr>';
						}

						$customFields = $GLOBALS['system']->getDBObjectData('custom_field', Array(), 'OR', 'rank');
						$dummy = new Custom_Field();
						$addParams = Array(
										'type' => 'select',
										'options' => Array('Replacing existing values ', 'Adding to existing values')
									);
						foreach ($customFields as $fieldid => $fieldDetails) {
							$value = '';
							$addToExisting = FALSE;
							$v = array_get($fields, 'custom_'.$fieldid);
							if ($v) {
								$value = $v['value'];
								$addToExisting = $v['add'];
							}
							$dummy->populate($fieldid, $fieldDetails);
							echo '<tr><td>';
							print_widget('fields_enabled[custom_'.$fieldid.']', Array('type'=>'checkbox'), strlen($value));
							echo 'Set '.ents($dummy->getValue('name')).'&nbsp;</td><td>';
							if ($fieldDetails['type'] == 'date') {
								// For now, we only support setting date fields to the reference date.
								// But there is room for future expansion to support fixed dates too.
								$dateVal = $noteVal = '';
								if (strlen($value)) {
									list($dateVal, $noteVal) = explode('===', $value);
								}
								echo 'to the reference date, with note ';
								print_widget('custom_'.$fieldid.'_note', Array('type' => 'text'), $noteVal);
							} else {
								$dummy->printWidget($value);
							}
							if ($dummy->getValue('allow_multiple')) {
								print_widget('fields_addvalue[custom_'.$fieldid.']', $addParams, $addToExisting);
							}
							echo '</td></tr>';
						}
						/*
						$dates = array_get($actions, 'dates');
						if (empty($dates)) $dates = Array('' => '');
						foreach ($dates as $typeid => $note) {
							?>
							<tr>
								<td class="nowrap">
								Set 
								<?php print_widget('datetypes[]', $datetype_params, "$typeid"); ?>
								to the reference date, with note
								<?php print_widget('datenotes[]', $datenote_params, $note); ?>
								</td>
							</tr>
							<?php
						}
						 */
						?>
						</table>
					</td>
				</tr>
				<tr>
					<th>Options</th>
					<td>
						
						<input type="hidden" name="default_on_create_family" value="0" />
						<label class="checkbox">
							<input type="checkbox" id="default_on_create_family" name="default_on_create_family" value="1" <?php if ($this->getValue('default_on_create_family')) echo 'checked="checked"'; ?>>
							By default, execute this plan when creating a new family
						</label>

						<input type="hidden" name="default_on_add_person" value="0" />
						<label class="checkbox">
							<input type="checkbox" id="default_on_add_person" name="default_on_add_person" value="1" <?php if ($this->getValue('default_on_add_person')) echo 'checked="checked"'; ?>>
							By default, execute this plan when adding a new person to an existing family
						</label>
					</td>
				</tr>
			</tbody>
		</table>
		<script>
		$('form').submit(function() {
			var ok = true;
			$('.note').each(function() {
				var t = $(this);
				if ((t.find('textarea:first').val() != '') && (t.find('input[name$="_subject"]').val() == '')) {
					t.find('input[name$="_subject"]').select();
					alert("All notes to be added must include a subject");
					ok = false;
				}
				if (!ok) return false;
				if (t.find('input[type=radio][value=relative]').attr('checked') && t.find('input[name$=action_date_offset]').val() == '') {
					t.find('input[name$=action_date_offset]').focus();
					alert("If a relative action date is selected you must specify a number of days");
					ok = false;
				}
			});
			return ok;
		});
		</script>
		<?php
	}

	function processForm()
	{
		parent::processForm();
		$actions = Array(
					'notes' => Array(),
					'groups' => Array(),
					'groups_remove' => Array(),
					'dates' => Array(),
				   );
		$i = 0;
		while ($note = $this->_processNoteForm($i)) {
			$actions['notes'][] = $note;
			$i++;
		}
		$i = 0;
		while (isset($_POST['groups'][$i])) {
			if ($groupid = (int)$_POST['groups'][$i]) {
				$actions['groups'][] = $groupid;
			}
			$i++;
		}
		$i = 0;
		while (isset($_POST['groups_remove'][$i])) {
			if ($groupid = (int)$_POST['groups_remove'][$i]) {
				$actions['groups_remove'][] = $groupid;
			}
			$i++;
		}
		$addValue = array_get($_POST, 'fields_addvalue', Array());
		foreach ($_POST['fields_enabled'] as $k => $v) {
			if (0 === strpos($k, 'custom_')) {
				$fieldID = substr($k, 7);
				$field = new Custom_Field($fieldID);
				if ($field->getValue('type') == 'date') {
					// FUture expansion: allow static dates here; for now we just support
					// the reference date, represented by magic number -1.
					$val = '-1==='.$_POST['custom_'.$fieldID.'_note'];
				} else {
					$val = $field->processWidget();
					$val = reset($val); // it comes wrapped in an array 
				}
			} else {
				$val = $_POST[$k];
			}
			$actions['fields'][$k] = Array(
				'value' => $val,
				'add' => array_get($addValue, $k, FALSE)
			);
		}
		$this->setValue('actions', $actions);
	}

	function create() {
		$this->setValue('modified', date('Y-m-d H:i:s'));
		$this->setValue('modifier', $GLOBALS['user_system']->getCurrentUser('id'));
		return parent::create();
	}


	function save()
	{
		$this->setValue('modified', date('Y-m-d H:i:s'));
		$this->setValue('modifier', $GLOBALS['user_system']->getCurrentUser('id'));
		return parent::save();
	}

	static function getMultiChooser($name, $value_or_context)
	{
		$res = Array();
		$plans = $GLOBALS['system']->getDBObjectData('action_plan', Array(), 'OR', 'name');
		foreach ($plans as $id => $plan) {
			$selected = false;
			if (is_array($value_or_context)) {
				$selected = in_array($id, $value_or_context);
			} else if ($value_or_context == 'create_family') {
				$selected = $plan['default_on_create_family'];
			} else if ($value_or_context == 'add_person') {
				$selected = $plan['default_on_add_person'];
			}
			$res[] = '<label class="checkbox">
						<input type="checkbox" name="'.ents($name).'[]"  value="'.(int)$id.'" id="'.$name.'_'.$id.'" '.($selected ? 'checked="checked"' : '').'>
						'.ents($plan['name']).'
					</label>
					';
		}
		return implode(" \n", $res);
	}

	/**
	* Execute this plan.
	*
	* If executed against a person, add notes to the person and put the person
	* in the specified groups.  If executed against a family, add notes to the
	* family and add the family members to the specified groups.
	*/
	function execute($subject_type, $subject_id, $reference_date)
	{
		//bam("Executing ".$this->getValue('name').' against '.$subject_type.' #'.$subject_id.' with ref date '.$reference_date);

		if ($subject_type == 'family') {
			$family = $GLOBALS['system']->getDBObject('family', (int)$subject_id);
			$personids = array_keys($family->getMemberData());
			if (count($personids) == 1) {
				// Not allowed to add family notes to single-member families, so pretend we're runninng against the person
				$subject_type = 'person';
				$subject_id = reset($personids);
			}
		} else if ($subject_type == 'person') {
			$personids = Array($subject_id);
		} else {
			trigger_error("Cannot execute plan against a $subject_type");
			return;
		}

		$actions = $this->getValue('actions');
		foreach (array_get($actions, 'groups', Array()) as $groupid) {
			$group = $GLOBALS['system']->getDBObject('person_group', $groupid);
			foreach ($personids as $personid) {
				$group->addMember($personid);
			}
		}
		foreach (array_get($actions, 'groups_remove', Array()) as $groupid) {
			$group = $GLOBALS['system']->getDBObject('person_group', $groupid);
			$group->removeMembers($personids);
		}

		$note_type = $subject_type.'_note';
		$GLOBALS['system']->includeDBClass($note_type);
		foreach (array_get($actions, 'notes', Array()) as $notedata) {
			$note = new $note_type();
			$notedata = Action_Plan_Note::getAbstractNoteData($notedata, $reference_date);

			$footnote = '[Added automatically by action plan "'.$this->getValue('name').'" (#'.$this->id.')]';
			if (strlen($notedata['details'])) {
				$notedata['details'] .= "\n$footnote";
			} else {
				$notedata['details'] = $footnote;
			}

			$note->populate(0, $notedata);
			$note->setValue($subject_type.'id', $subject_id);
			$note->create();
		}

		if ($fields = array_get($actions, 'fields')) {
			foreach ($personids as $personid) {
				$person = $GLOBALS['system']->getDBObject('person', $personid);
				foreach ($fields as $k => $v) {
					if (0 === strpos($k, 'custom_')) {
						if (0 === strpos($v['value'], '-1===')) {
							$v['value'] = $reference_date.' '.substr($v['value'], 5);
						}
						$fieldID = substr($k, 7);
						$person->setCustomValue($fieldID, $v['value'], $v['add']);
					} else {
						$person->setValue($k, $v['value']);
					}
				}
				$person->save();
			}
			//exit;
		}



	}

	public function getValue($name)
	{
		$res = parent::getValue($name);
		if (($name == 'actions') && isset($res['dates'])) {
			// Convert old format to new format
			foreach ($res['dates'] as $fieldid => $note) {
				$res['fields']['custom-'.$fieldid] = Array(
					'value' => "-1==={$note}",
					'add' => true,
				);
			}
			unset($res['dates']);
		}
		return $res;
	}
}
