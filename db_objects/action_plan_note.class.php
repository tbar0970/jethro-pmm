<?php
include_once 'abstract_note.class.php';
class Action_Plan_Note extends Abstract_Note {

	static function getAbstractNoteData($action_note_data, $reference_date)
	{
		if ($action_note_data['action_date_type'] == 'relative') {
			$exp = $action_note_data['action_date_operator'].' '.$action_note_data['action_date_offset'].' days';
			$action_note_data['action_date'] = date('Y-m-d', strtotime($exp, strtotime($reference_date)));
		}
		unset($action_note_data['action_date_type']);
		unset($action_note_data['action_date_offset']);
		unset($action_note_data['action_date_operator']);
		return $action_note_data;
	}

	function getInitSQL($table_name=NULL)
	{
		return '';
	}

	function getForeignKeys() {
		return Array();
	}

	protected static function _getFields()
	{

		$res = parent::_getFields();
		$res['action_date']['note'] = '';
		$res['assignee']['note'] = '';
		$res['action_date_type'] = Array(
									'type' => 'select',
									'options' => Array('fixed'=>'fixed', 'relative'=>'relative'),
									'default' => 'relative',
									'editable' => false
								   );
		$res['action_date_offset'] = Array(
									'type' => 'int',
									'editable' => false,
									'default' => 0,
									);
		$res['action_date_operator'] = Array(
										'type' => 'select',
										'options' => Array('+' => 'after', '-' => 'before'),
										'default' => '+',
										'editable' => false
									  );
		return $res;
	}


	function printFieldInterface($name, $prefix='')
	{
		if ($name == 'action_date') {
			?>
			<div class="preserve-value">
				<span class="radio-list">
					<input type="radio" name="<?php echo $prefix; ?>action_date_type" value="fixed"
					<?php if ($this->getValue('action_date_type') == 'fixed') echo 'checked="checked"'; ?>
					>
					<?php echo print_widget($prefix.$name, Array('type' => 'date'), $this->getValue('action_date')); ?>
				</span>
				<br />
				<span class="radio-list">
					<input type="radio" name="<?php echo $prefix; ?>action_date_type" value="relative"
					<?php if ($this->getValue('action_date_type') == 'relative') echo 'checked="checked"'; ?>
					>
					<?php $this->printFieldInterface('action_date_offset', $prefix); ?> days
					<?php $this->printFieldInterface('action_date_operator', $prefix); ?>
					the reference date
				</span>
			</div>
			<?php
		} else {
			return parent::printFieldInterface($name, $prefix);
		}
	}

	function processForm($prefix='', $fields=NULL) {
		parent::processForm($prefix);
		if ($this->values['subject']) {
			$this->processFieldInterface('action_date_type', $prefix);
			$this->processFieldInterface('action_date_offset', $prefix);
			$this->processFieldInterface('action_date_operator', $prefix);
		}
	}

}