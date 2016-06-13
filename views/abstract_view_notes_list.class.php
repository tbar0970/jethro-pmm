<?php
class Abstract_View_Notes_List extends View
{
	var $_reassigning = FALSE;
	var $_notes = Array();

	function _compareNoteDates($a, $b)
	{
		return $a['action_date'] > $b['action_date'];
	}

	function processView()
	{
		$this->_notes = $this->_getNotesToShow(array_get($_REQUEST, 'assignee'));

		$this->_reassigning = $GLOBALS['user_system']->havePerm(PERM_BULKNOTE) && !empty($_REQUEST['reassigning']);
		if ($this->_reassigning && !empty($_POST['reassignments_submitted'])) {
			$dummy_note = new Abstract_Note();
			foreach ($this->_notes as $id => $note) {
				$dummy_note->populate($id, $note);
				$dummy_note->setValue('assignee', $_POST['note_'.$id.'_assignee']);
				$dummy_note->save();
				$dummy_note->releaseLock();
			}
			add_message(_("Assignments Saved"));
			$this->_reassigning = FALSE;

			// these will have changed
			$this->_notes = $this->_getNotesToShow();
		}
	}


	function getTitle()
	{
		return '';
	}


	function printView()
	{
		?>
		<form class="well well-small form-inline">
		<input type="hidden" name="view" value="<?php echo $_REQUEST['view']; ?>" />
		<?php echo _('Show notes assigned to');?> 
		<?php 	
		print_widget(
			'assignee', 
			Array(
				'type' => 'reference',
				'references' => 'staff_member',
				'allow_empty' => true,
				'empty_text' => 'Anyone',
				'filter'		=> create_function('$x', 'return $x->getValue("active") && (($x->getValue("permissions") & PERM_EDITNOTE) == PERM_EDITNOTE);'),
			),
			array_get($_REQUEST, 'assignee')
		);
		?>
		<button type="submit" class="btn"><?php echo _('Go');?></button>
		</form>
		<?php

		$reassigning = $this->_reassigning;
		if (empty($this->_notes)) {
			?>
			<p><i><?php echo _('There are no notes to show');?></i></p>
			<?php
		} else {
			if (!$reassigning && $GLOBALS['user_system']->havePerm(PERM_BULKNOTE)) {
				?>
				<p class="pull-right">
					<a href="<?php echo build_url(Array('reassigning' => 1)); ?>"><i class="icon-wrench""></i><?php echo _('Edit the assignees for all these notes');?></a>
				</p>
				<?php
			}
			?>
			<p><b><?php echo count($this->_notes).' '._('notes in total');?></b></p>
			<?php
			$notes =& $this->_notes;
			include 'templates/list_notes_assorted.template.php';
		}
	}
}
?>
