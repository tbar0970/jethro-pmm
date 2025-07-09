<?php
class View__Edit_Me extends View
{
	private $family = NULL;
	private $persons = Array();
	private $hasAdult = FALSE;
	private $person_fields = Array('gender', 'age_bracketid', 'email', 'mobile_tel', 'work_tel', 'photo');

	public function __construct()
	{
		if (!ifdef('MEMBERS_SEE_AGE_BRACKET', TRUE)) {
			$map = array_flip($this->person_fields);
			unset($this->person_fields[$map['age_bracketid']]);
		}
	}
	
	function getTitle()
	{
		if ($this->family) return "Editing ".$this->family->getValue('family_name')." Family";
	}
	
	function canEditFamily()
	{
		// Non-adults can only edit if there are no adults in the family
		return
			in_array($GLOBALS['user_system']->getCurrentMember('age_bracketid'), Age_Bracket::getAdults())
			|| !$this->hasAdult
		;
	}
	
	function processView()
	{
		$this->family = $GLOBALS['system']->getDBOBject('family', $GLOBALS['user_system']->getCurrentMember('familyid'));
		foreach ($this->family->getMemberData() as $id => $member) {
			$p = $GLOBALS['system']->getDBObject('person', $id);
			$this->persons[$id] = $p;
			if (in_array($p->getValue('age_bracketid'), Age_Bracket::getAdults())) {
				$this->hasAdult = TRUE;
			}
		}
		
		if ($this->EditMeFormSubmitted()) {
			if ($this->canEditFamily()) {
				$this->family->processForm();
				$this->family->save();
				$this->family->releaseLock();
			}
			$fields = Array('gender', 'age_bracket', 'email', 'mobile_tel', 'work_tel');
			foreach ($this->persons as $person) {
				$sm = new Staff_Member($person->id);
				if ($sm && $sm->requires2FA()) {
					// People with 2FA control-centre accounts can't be edited via members area, so skip
				} else if ($this->canEditFamily() || $this->isMe($person)) {
					$person->processForm('person_'.$person->id, $this->person_fields);
					$person->save(FALSE);
					$person->releaseLock();
				}
			}
			
			add_message("Details saved");
			redirect('home');
			
		}
		
	}
	
	function printView()
	{
		$ok = true;

		if ($this->canEditFamily()) {
			$ok = $this->family->acquireLock();
			foreach ($this->persons as $p) {
				$ok = $ok && $p->acquireLock();
			}
		} else {
			$p = $this->persons[$GLOBALS['user_system']->getCurrentMember('id')];
			$ok = $ok && $p->acquireLock();
		}

		if (!$ok) {
			print_message("Your family cannot be edited right now.  Please try later", 'error');
			
		} else {

			if (ifdef('MEMBER_REGO_HELP_EMAIL')) {
				?>
				<p><i>If you need to change names or other details which are not listed in this form, please contact  <a href="mailto:<?php echo ents(MEMBER_REGO_HELP_EMAIL); ?>"><?php echo ents(MEMBER_REGO_HELP_EMAIL); ?></a>.</i></p>
				<?php
			}
			
			?>
			<form method="post" enctype="multipart/form-data">
			<h3>Family Details</h3>
			<?php
			if ($this->canEditFamily()) {
				$this->family->printForm('family', Array('address_street', 'address_suburb', 'address_postcode', 'home_tel', 'photo'));
			} else {
				$this->family->printSummary();
				echo '<i>Only adults can edit family details</i>';
			}

			foreach ($this->persons as $person) {
				echo '<h3>'.$person->getValue('first_name').' '.$person->getValue('last_name').'</h3>';

				$sm = new Staff_Member($person->id);
				if ($sm && $sm->requires2FA()) {
					echo '<p><i>This person has a control centre account, so their details can only be edited via the <a href="'.BASE_URL.'?view=persons&personid='.(int)$person->id.'">control centre</a></i></p>';
				} else if ($this->canEditFamily() || $this->isMe($person)) {
					$person->printForm('person_'.$person->id, $this->person_fields);
				}
			}
				
			?>
			<button class="btn" type="submit">Save</button>
			<a class="btn" href="?">Cancel</a>
			</form>
			<?php
		}

	}

	private function isMe($person)
	{
		return $person->id == $GLOBALS['user_system']->getCurrentMember('id');
	}

    /**
     * Examine $_POST to see if it contains what we expect from the edit_me form being submitted. This will not be true if the initial request was unauthenticated and User_System served up the login form instead - then $_POST will contain just 'email' and 'password', and we can't proceed. See #1243.
     * @return bool Whether to process the form as if submitted
     */
    public function EditMeFormSubmitted(): bool
    {
        if (empty($_POST)) return false;

        // This is pretty ugly. We have no single parameter we can check that we (edit_me) definitely set, but we will have a bunch of person_* params, each of which will have a csrf token. So if we see any of those, proceed.
        foreach (array_keys($_POST) as $key) {
            if (preg_match('/^person_.*token$/', $key)) {
                return true;
            }
        }
        return false;
    }

}
