<?php
class View__Edit_Me extends View
{
	private $family = NULL;
	private $persons = Array();
	private $hasAdult = FALSE;
	
	function getTitle()
	{
		if ($this->family) return "Editing ".$this->family->getValue('family_name')." Family";
	}
	
	function canEdit()
	{
		// Non-adults can only edit if there are no adults in the family
		return
			($GLOBALS['user_system']->getCurrentMember('age_bracket') == '0')
			|| !$this->hasAdult
		;
	}
	
	function processView()
	{
		$this->family = $GLOBALS['system']->getDBOBject('family', $GLOBALS['user_system']->getCurrentMember('familyid'));
		foreach ($this->family->getMemberData() as $id => $member) {
			$p = $GLOBALS['system']->getDBObject('person', $id);
			$this->persons[] = $p;
			if ($p->getValue('age_bracket') == '0') $this->hasAdult = TRUE;
		}
		
		if (!empty($_POST) && $this->canEdit()) {
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
		if (!$this->canEdit()) {
			print_message("Sorry, only adults are able to edit this family.", 'error');
			return;
		}
		
		$ok = $this->family->acquireLock();
		foreach ($this->persons as $p) {
			$ok = $ok && $p->acquireLock();
		}
		
		if (!$ok) {
			print_message("Your family cannot be edited right now.  Please try later", 'error');
			
		} else {

				if (defined('MEMBER_REGO_HELP_EMAIL')) {
				?>
				<p><i>If you need to change names or other details which are not listed in this form, please contact  <a href="mailto:<?php echo ents(MEMBER_REGO_HELP_EMAIL); ?>"><?php echo ents(MEMBER_REGO_HELP_EMAIL); ?></a>.</i></p>
				<?php
			}

			
			?>
			<form method="post" enctype="multipart/form-data">
			<h3>Family Details</h3>
			<?php
			$this->family->printForm('family', Array('address_street', 'address_suburb', 'address_postcode', 'home_tel'));

			foreach ($this->persons as $person) {
				echo '<h3>'.$person->getValue('first_name').' '.$person->getValue('last_name').'</h3>';
				$person->printForm('person_'.$person->id, Array('gender', 'age_bracket', 'email', 'mobile_tel', 'work_tel', 'photo'));
			}
				
			?>
			<button class="btn" type="submit">Save</button>
			<a class="btn" href="?">Cancel</a>
			</form>
			<?php
		}

	}

}
