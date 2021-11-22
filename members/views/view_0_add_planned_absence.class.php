<?php
require_once dirname(__FILE__).'/../../views/abstract_view_add_object.class.php';
class View__Add_Planned_Absence 
{
	private $_create_type = 'planned_absence';
	private $_success_message = 'Planned absence saved';
	private $_on_success_view = 'persons';
	private $_failure_message = 'Error saving planned absence';
	private $_submit_label = 'Save';
	private $_title = 'Add Planned Absence';

	var $_new_object;

	function processView()
	{
		$GLOBALS['system']->includeDBClass($this->_create_type);
		$this->_new_object = new $this->_create_type();

		if (array_get($_REQUEST, 'new_'.$this->_create_type.'_submitted')) {
			$this->_new_object->processForm();
			$this->_beforeCreate();
			
			// TODO: check all the persons selected are in the current'  user's family
			// TODO: Save an object for every person involved
			
			if ($this->_new_object->create()) {
				$this->_afterCreate();
				add_message(_($this->_success_message));
				$this->_doSuccessRedirect();
			} else {
				$this->_new_object->id = 0;
				add_message(_($this->_failure_message), 'failure');
			}
		}
	}

	protected function _doSuccessRedirect()
	{
		redirect($this->_on_success_view, Array($this->_create_type.'id' => $this->_new_object->id));
	}

	
	function _beforeCreate()
	{
		
	}

	function _afterCreate()
	{
	}
	
	function getTitle()
	{
		return _($this->_title);
	}


	function printView()
	{
		?>
		<form method="post" id="add-<?php echo $this->_create_type; ?>">
			<input type="hidden" name="new_<?php echo $this->_create_type; ?>_submitted" value="1" />
			<?php
			
			// TODO: print multi-person select restricted to the poeple in the current user's famliy
			$this->_new_object->printForm();
			?>
			<div class="form-horizontal"><div class="controls">
				<button type="submit" class="btn"><?php echo _($this->_submit_label); ?></button>
				<button type="button" class="btn back"><?php echo _('Cancel');?></button>
			</div></div>
		</form>
		<?php
	}


	
	function getTitle()
	{
		return 'Add Planned Absence';
	}

	function processView() 
	{
		parent::processView();
	}
	
	function _beforeCreate()
	{
		$this->_new_object->setValue('personid', (int)$_REQUEST['personid']);
	}

	protected function _doSuccessRedirect()
	{
		if ($this->_new_object->hasRosterAssignments()) {
			$person = new Person((int)$_REQUEST['personid']);
			add_message($person->toString().' is already assigned to roster roles during the absent period. You should edit the roster to address this.', 'warning');
		}
		redirect($this->_on_success_view, Array('personid' => $_REQUEST['personid']), 'rosters');
	}
	
}