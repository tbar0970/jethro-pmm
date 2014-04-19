<?php
class View__Mixed_Search extends View
{
	var $_family_data;
	var $_person_data;

	function processView()
	{
		$this->_search_params = Array();
		$tel = preg_replace('/[^0-9]/', '', $_REQUEST['tel']);

		if (!empty($tel)) {
			$this->_family_data = $GLOBALS['system']->getDBObjectData('family', Array('home_tel' => $tel));
			$this->_person_data = $GLOBALS['system']->getDBObjectData('person', Array('mobile_tel' => $tel, 'work_tel' => $tel));
		}
		
		if (empty($this->_family_data) && (count($this->_person_data) == 1)) {
			add_message("One matching person found");
			redirect('persons', Array('personid' => key($this->_person_data)));
		} else if (empty($this->_person_data) && (count($this->_family_data) == 1)) {
			add_message("One matching family found");
			redirect('families', Array('familyid' => key($this->_person_data)));
		}		
	}

	function getTitle()
	{
		return 'Search results';
	}

	
	function printView()
	{
		if (empty($this->_person_data) && empty($this->_family_data)) {
			echo '<p><i>No matching persons or families were found.  Try searching again.</i></p>';
			echo '<form class="form form-horizontal"><input type="hidden" name="view" value="_mixed_search">';
			echo '<input type="text" name="tel" placeholder="Enter tel number" />';
			echo '<button type="submit" class="btn">Search</button></form>';
			return;
		}
		
		?>
		<table class="table table-hover table-striped table-min-width clickable-rows">
		<?php
		if (!empty($this->_person_data)) {
			foreach ($this->_person_data as $id => $values) {
				?>
				<tr>
					<td><?php echo htmlentities($values['first_name']).' '.htmlentities($values['last_name']); ?></td>
					<td class="narrow">
						<a href="?view=persons&personid=<?php echo $id; ?>"><i class="icon-user"></i>View</a> &nbsp;
						<a href="?view=_edit_person&personid=<?php echo $id; ?>"><i class="icon-wrench"></i>Edit</a>
					</td>
				</tr>
				<?php
			}
		}
		if (!empty($this->_family_data)) {
			foreach ($this->_family_data as $id => $values) {
				?>
				<tr>
					<td><?php echo htmlentities($values['family_name']); ?> Family</td>
					<td class="narrow">
						<a href="?view=families&familyid=<?php echo $id; ?>"><i class="icon-home"></i>View</a> &nbsp;
						<a href="?view=_edit_family&familyid=<?php echo $id; ?>"><i class="icon-wrench"></i>Edit</a>
					</td>
				</tr>
				<?php
			}
		}
		?>
		</table>
		<?php
	}
}
?>