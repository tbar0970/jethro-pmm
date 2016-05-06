<?php
class View_Persons extends View
{
	// for viewing one persons
	var $_person;
	var $_family;

	function processView()
	{
		if (!empty($_REQUEST['personid'])) {
			$this->_person =& $GLOBALS['system']->getDBObject('person', (int)$_REQUEST['personid']);
			if ($this->_person) {
				$this->_family =& $GLOBALS['system']->getDBObject('family', $this->_person->getValue('familyid'));
			}
		}
	}


	function getTitle()
	{
		if ($this->_person) {
			return 'Viewing Person: '.$this->_person->toString();
		} else {
			return 'Person not found';
		}
	}


	function printView()
	{
		if ($this->_person) {
			$person =& $this->_person;
			$family =& $this->_family;
			include dirname(dirname(__FILE__)).'/templates/view_person.template.php';
		}
	}
	function printAjax() {
		$ajax = array();
		if ($this->_person) {
			$person =& $this->_person;
			$family =& $this->_family;
			if ($GLOBALS['user_system']->havePerm(PERM_VIEWNOTE)) {
			        $notes = $person->getNotesHistory();
				$ajax['notescount'] = count($notes);
				ob_start();
				if ($GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
			        ?>
				     <div class="pull-right"><a href="?view=_add_note_to_person&personid=<?php echo $person->id; ?>"><i class="icon-plus-sign"></i>Add Note</a></div>
				<?php
			        }

				if (empty($notes)) {
			        ?>
			          <p><i>There are no person or family notes for <?php $person->printFieldValue('name'); ?></i></p>
				<?php
				} else {
		                ?>
			          <p><i>Person and Family Notes for <?php $person->printFieldValue('name'); ?>:</i></p>
				<?php
				}
			        $show_edit_link = true;
				include dirname(dirname(__FILE__)).'/templates/list_notes.template.php';
				$ajax['noteshtml'] = ob_get_contents();
				ob_end_clean();
			}
		}
		echo json_encode($ajax);
	}
}
?>
