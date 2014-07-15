<?php
class View__Edit_Me extends View
{
	function getTitle()
	{
		if ($this->family) return "Editing ".$this->family->getValue('family_name')." Family";
	}

	function processView()
	{
		$this->family = $GLOBALS['system']->getDBOBject('family', $GLOBALS['member_user_system']->getCurrentMember('familyid'));
		foreach ($this->family->getMemberData() as $id => $member) {
			$this->persons[] = $GLOBALS['system']->getDBObject('person', $id);
		}
		
		if (!empty($_POST)) {
			$this->family->processForm();
			$this->family->save();
			$this->family->releaseLock();
			
			foreach ($this->persons as $person) {
				$person->processForm('person_'.$person->id);
				$person->save(FALSE);
				$person->releaseLock();
			}
			
			add_message("Details saved");
			redirect('home');
			
		}
		
	}
	
	function printView()
	{		
		$ok = $this->family->acquireLock();
		foreach ($this->persons as $p) {
			$ok = $ok && $p->acquireLock();
		}
		
		if (!$ok) {
			print_message("Your family cannot be edited right now.  Please try later");
			
		} else {
			
			?>
			<form method="post">
			<h3>Family Details</h3>
			<?php
			$this->family->printForm('family', Array('address_street', 'address_suburb', 'address_postcode', 'home_tel'));

			foreach ($this->persons as $person) {
				echo '<h3>'.$person->getValue('first_name').' '.$person->getValue('last_name').'</h3>';
				$person->printForm('person_'.$person->id, Array('gender', 'age_bracket', 'email', 'mobile_tel', 'work_tel'));
			}
			
			?>
			<button class="btn" type="submit">Save</button>
			<a class="btn" href="?view=">Cancel</a>
			</form>
			<?php
		}

	}

}
