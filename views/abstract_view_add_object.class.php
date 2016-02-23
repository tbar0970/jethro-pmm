<?php
class Abstract_View_Add_Object extends View
{
	var $_new_object;

	var $_create_type = '';
	var $_success_message = 'New object created';
	var $_on_success_view = 'home';
	var $_failure_message = 'New Object not created';
	var $_submit_label = 'Create';
	var $_title = 'Add Object';

	function processView()
	{
		$GLOBALS['system']->includeDBClass($this->_create_type);
		$this->_new_object = new $this->_create_type();

		if (array_get($_REQUEST, 'new_'.$this->_create_type.'_submitted')) {
			$this->_new_object->processForm();
			if ($this->_new_object->create()) {
				$this->_afterCreate();
				add_message($this->_success_message);
				$this->_doSuccessRedirect();
			} else {
				$this->_new_object->id = 0;
				add_message($this->_failure_message, 'failure');
			}
		}
	}

	protected function _doSuccessRedirect()
	{
		redirect($this->_on_success_view, Array($this->_create_type.'id' => $this->_new_object->id));
	}


	function _afterCreate()
	{
	}
	
	function getTitle()
	{
		return $this->_title;
	}


	function printView()
	{
		?>
		<form method="post" id="add-<?php echo $this->_create_type; ?>" action="" novalidate>
			<input type="hidden" name="new_<?php echo $this->_create_type; ?>_submitted" value="1" />
			<?php
			$this->_new_object->printForm();
			?>
			<div class="form-horizontal"><div class="controls">
				<button type="submit" class="btn"><?php echo $this->_submit_label; ?></button>
				<button type="button" class="btn back">Cancel</button>
			</div>
		</form>
		<?php
	}
}
?>
