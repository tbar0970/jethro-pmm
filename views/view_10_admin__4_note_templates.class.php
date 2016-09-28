<?php
class View_Admin__Note_Templates extends View
{
	private $_template = NULL;

	static function getMenuPermissionLevel()
	{
		return PERM_SYSADMIN;
	}

	/**
	 * Load up a template object and save its details if applicable.
	 */
	function processView()
	{
		if (isset($_REQUEST['templateid'])) {
			$this->_template =  new Note_Template((int)$_REQUEST['templateid']);
			if ($this->_template->id) $this->_template->acquireLock();
		}
		if (!empty($_POST['delete'])) {
			$this->_template->delete();
			add_message("Template deleted");
			redirect($_REQUEST['view'], Array('*' => NULL)); // exits
		}
		if (!empty($_REQUEST['template_submitted'])) {
			$this->_template->processForm();
			if ($this->_template->id) {
				if ($this->_template->save()) {
					add_message("Template saved");
					redirect($_REQUEST['view'], Array('*' => NULL));
				}
			} else {
				if ($this->_template->create()) {
					add_message("Template added");
					redirect($_REQUEST['view'], Array('*' => NULL));
				}
			}
		}
	}

	function getTitle()
	{
		if (!$this->_template) {
			return 'Configure Note Templates';
		} else if (!$this->_template->id) {
			return 'Add Note Template';
		} else {
			return 'Edit Note Template';
		}
	}

	function printView()
	{
		if ($this->_template) {
			$this->printTemplateDetails();
		} else {
			$this->printTemplateList();
		}
	}

	/**
	 * Print the list of templates to choose from
	 */
	private function printTemplateList()
	{
		?>
		<p class="text alert alert-info">When you create a note template here, it then becomes available for use when adding a note to a person. Templates are useful if you often need to add notes containing a fixed set of details, for example an incident report or training record. </p>
		<p>
			<a href="<?php echo build_url(Array('*' => NULL, 'view' => $_REQUEST['view'], 'templateid' => 0)); ?>"><i class="icon-plus-sign"></i>Create a new note template</a>
		</p>
		<?php

		$templates = $GLOBALS['system']->getDBObjectData('note_template', Array(), 'OR', 'name');
		if ($templates) {
			?>
			<table class="table table-auto-width">
				<thead>
					<tr>
						<th>ID</th>
						<th>Name</th>
						<th class="action-cell">Actions</th>
					</tr>
				</thead>
				<tbody>
				<?php
				foreach ($templates as $id => $template) {
					?>
					<tr>
						<td><?php echo $id; ?></td>
						<td><?php echo ents($template['name']); ?></td>
						<td class="action-cell">
							<a href="<?php echo build_url(Array('templateid' => $id)); ?>"><i class="icon-wrench"></i>Edit</a>
							&nbsp;
							<a href="<?php echo build_url(Array('templateid' => $id, 'delete' => 1)); ?>" data-method="post" data-confirm="Are you sure you want to delete this note template?  This cannot be undone."><i class="icon-trash"></i>Delete</a>
						</td>
					</tr>
					<?php
				}
				?>
				</tbody>

			</table>
			<?php
		}

	}

	/**
	 * Print the details of a template for editing
	 */
	private function printTemplateDetails()
	{
		?>
		<form method="post" class="form-horizontal" id="add-person_group">
			<input type="hidden" name="templateid" value="<?php echo $this->_template->id; ?>">
			<input type="hidden" name="template_submitted" value="1">
			<?php
			$this->_template->printForm();
			?>
			<div class="controls">
				<input class="btn" type="submit" value="Save">
				<a href="?view=admin__note_templates" class="btn">Cancel</a>
			</div>
		</form>
		<?php

	}
}