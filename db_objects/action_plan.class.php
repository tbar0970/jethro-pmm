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
		<table>
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
			<?php
			if ($GLOBALS['system']->featureEnabled('DATES')) {
				$datetype_params = Array(
									'type' => 'select',
									'options' => Array('' => '(Choose type)'),
									);
				$dateFields = $GLOBALS['system']->getDBObjectData('custom_field', Array('type' => 'date'), 'OR', 'rank');
				foreach ($dateFields as $fieldID => $fieldDetails) {
					$datetype_params['options'][$fieldID] = $fieldDetails['name'];
				}
				$datenote_params = Array(
									'type' => 'text',
									'width' => 40
									);
				?>
				<tr>
					<th>Dates</th>
					<td>
						When this plan is executed, for each person / family member:
						<table class="expandable">
						<?php
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
						?>
						</table>
					</td>
				</tr>
				<?php
			}
			?>
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
		if (isset($_POST['datetypes'])) {
			$i = 0;
			while (isset($_POST['datetypes'][$i])) {
				if ($_POST['datetypes'][$i]) {
					$actions['dates'][$_POST['datetypes'][$i]] = $_POST['datenotes'][$i];
				}
				$i++;
			}
		}
		$actions['dates'] = Array();
		foreach ($_REQUEST['datetypes'] as $i => $customFieldID) {
			if ($customFieldID) {
				$actions['dates'][$customFieldID] = $_REQUEST['datenotes'][$i];
			}
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

		if (array_get($actions, 'dates')) {
			foreach ($personids as $personid) {
				$person = $GLOBALS['system']->getDBObject('person', $personid);
				foreach (array_get($actions, 'dates', Array()) as $fieldID => $note) {
					$person->setCustomValue($fieldID, $reference_date.' '.$note, TRUE);
				}
				$person->save();
			}
		}

	}
}
