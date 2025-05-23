<?php
abstract class Abstract_View_Notes_List extends View
{
	var $_reassigning = FALSE;
	var $_notes = Array();

	static function getMenuPermissionLevel()
	{
		return PERM_VIEWMYNOTES;
	}

	function _compareNoteDates($a, $b)
	{
		return $a['action_date'] > $b['action_date'] ? 1 : -1;
	}
	
	protected function _getNotes($conds)
	{
		$conds['status'] = 'pending';

		$assigneeID = array_get($_REQUEST, 'assignee');
		if ($assigneeID) {
			$conds['assignee'] = $assigneeID;
		}

		$search = array_get($_REQUEST, 'search');
		if ($search) {
			$conds['subject'] = '%'.$search.'%';
		}

		$res = $GLOBALS['system']->getDBObjectData('person_note', $conds, 'AND', '', TRUE) + $GLOBALS['system']->getDBObjectData('family_note', $conds, 'AND', '', TRUE);
		uasort($res, Array($this, '_compareNoteDates'));
		return $res;
	}

	function processView()
	{
		$this->_reassigning = $GLOBALS['user_system']->havePerm(PERM_BULKNOTE) && !empty($_REQUEST['reassigning']);
		if ($this->_reassigning && !empty($_POST['reassignments_submitted'])) {
			$this->_notes = $this->_getNotesToShow(array_get($_REQUEST, 'assignee'), array_get($_REQUEST, 'search'));
			$dummy_note = new Abstract_Note();
			foreach ($this->_notes as $id => $note) {
				$dummy_note->reset();
				$dummy_note->populate($id, $note);
				$dummy_note->setValue('assignee', $_POST['note_'.$id.'_assignee']);
				$dummy_note->save();
				$dummy_note->releaseLock();
			}
			add_message(_("Assignments Saved"));
			$this->_reassigning = FALSE;

		}
		// these will have changed
		$this->_notes = $this->_getNotesToShow(array_get($_REQUEST, 'assignee'), array_get($_REQUEST, 'search'));
	}


	function printView()
	{
		if (!$GLOBALS['user_system']->havePerm(PERM_VIEWNOTE)) {
			print_message('You only have access to view notes assigned to you', 'info');
		}
		?>
		<form class="well well-small form-inline">
		<input type="hidden" name="view" value="<?php echo $_REQUEST['view']; ?>" />
		<?php
		$string = "Show %s of notes assigned to %s with subject containing %s";

		if ($GLOBALS['user_system']->havePerm(PERM_VIEWNOTE)) {
			$filterfunc = function($x) {return $x->getValue("active") && (($x->getValue("permissions") & PERM_VIEWMYNOTES) == PERM_VIEWMYNOTES);};
			$allowempty = TRUE;
		} else {
			$filterfunc = function($x) {return $x->id == $GLOBALS['user_system']->getCurrentUser('id');};
			$allowempty = FALSE;
		}
		ob_start();
		print_widget(
			'assignee',
			Array(
				'type' => 'reference',
				'references' => 'staff_member',
				'allow_empty' => $allowempty,
				'empty_text' => 'Anyone',
				'filter'		=> $filterfunc,
			),
			array_get($_REQUEST, 'assignee')
		);
		echo '<br class="visible-phone" />';
		$assignee_widget = ob_get_clean();

		ob_start();
		print_widget(
			'display_full',
			Array(
				'type' => 'select',
				'options' => Array('summary', 'full content'),
			),
			array_get($_REQUEST, 'display_full', 0)
		);
		echo '<br class="visible-phone" />';
		$display_widget = ob_get_clean();

		ob_start();
		print_widget(
			'search',
			Array('type' => 'text', 'width' => 10),
			array_get($_REQUEST, 'search', '')
		);
		$search_widget = ob_get_clean();

		printf(_($string), $display_widget, $assignee_widget, $search_widget);
		?>
		<button type="submit" class="btn"><?php echo _('Go'); ?></button>
		</form>
		<?php

		$reassigning = $this->_reassigning;
		if (empty($this->_notes)) {
			?>
			<p><i><?php echo _('There are no notes to show'); ?></i></p>
			<?php
		} else {
			if (!$reassigning && empty($_REQUEST['display_full']) && $GLOBALS['user_system']->havePerm(PERM_BULKNOTE)) {
				?>
				<p class="pull-right">
					<a href="<?php echo build_url(Array('reassigning' => 1)); ?>">
						<i class="icon-wrench""></i><?php echo _('Edit the assignees for all these notes'); ?>
					</a>
				</p>
				<?php
			}
			?>
			<p><b><?php echo sprintf(_('%s notes in total'), count($this->_notes)); ?></b></p>
			<?php
			$notes =& $this->_notes;
			if (empty($_REQUEST['display_full'])) {
				include 'templates/list_notes_assorted.template.php';
			} else {
				$show_names = TRUE;
				$show_edit_link = TRUE;
				include 'templates/list_notes.template.php';
			}
		}
	}
}