<?php
class View__Edit_Me extends View
{
	private $family = NULL;
	private $persons = Array();
	private $hasAdult = FALSE;
	private $hasHidden = FALSE;
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
			if ($p) {
				$this->persons[$id] = $p;
				if (in_array($p->getValue('age_bracketid'), Age_Bracket::getAdults())) {
					$this->hasAdult = TRUE;
				}
			} else {
				// Person object will be null if user lacks permission to view this family member
				// (e.g. when user has congregation-restricted permissions and family member is in a different congregation). #1173
				$this->hasHidden = TRUE;
			}
		}
		
		if (array_get($_POST, 'family_details_submitted')) {
			if ($this->canEditFamily()) {
				$this->family->processForm();
				$this->family->save();
				$this->family->releaseLock();
			}
			foreach ($this->persons as $person) {
				$fields = $this->editablePersonFields($person);
				if (!$fields) {
					// People with 2FA control-centre accounts can't be edited via members area, so skip
				} else if ($this->canEditFamily() || $this->isMe($person)) {
					$person->processForm('person_'.$person->id, $fields);
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

			if ($this->hasHidden) {
				?>
				<div class="alert alert-info">
					<strong>Note:</strong> Some family members are not shown here due to permission restrictions.
					You can only view and edit family members from congregations and groups you have access to.
				</div>
				<?php
			}

			?>
			<form method="post" enctype="multipart/form-data">
			<input type="hidden" name="family_details_submitted" value="1" />
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

				$fields = $this->editablePersonFields($person);
				if (!$fields) {
					echo '<p><i>This person has a control centre account, so their details can only be edited via the <a href="?view=persons&personid='.(int)$person->id.'">control centre</a></i></p>';
					$this->printReadOnlyStaffContacts($person);
				} else if ($this->canEditFamily() || $this->isMe($person)) {
					$person->printForm('person_'.$person->id, $fields);
					if (!in_array('email', $fields)) {
						$this->printReadOnlyStaffContacts($person);
					}
				}
			}
				
			?>
			<button class="btn" type="submit">Save</button>
			<a class="btn" href="?">Cancel</a>
			</form>
			<?php
		}

	}

	/**
	 * Display staff recovery contacts without granting member-area edit access.
	 * The inputs are unnamed, so they are never submitted; values and sizes match
	 * the editable widgets from print_widget().
	 * @see docs/docs/developer/reference/members-contact-protection.mdx
	 * @param Person $person
	 * @return void
	 */
	private function printReadOnlyStaffContacts(Person $person): void
	{
		$reason = 'For security, staff email addresses and mobile numbers can only be changed in the control centre.';
		$helpID = 'staff-contact-help-'.$person->id;
		$contacts = Array(
			'email' => Array(
				'value' => $person->getValue('email'),
				'size' => $person->fields['email']['width'],
			),
			'mobile_tel' => Array(
				'value' => $person->getFormattedValue('mobile_tel'),
				'size' => max(get_phone_format_lengths($person->fields['mobile_tel']['formats'])) + 3,
			),
		);
		?>
		<div class="form-horizontal">
			<?php foreach ($contacts as $field => $contact) {
				$inputID = 'person_'.$person->id.$field;
				?>
				<div class="control-group">
					<label class="control-label" for="<?php echo ents($inputID); ?>"><?php echo ents($person->getFieldLabel($field)); ?></label>
					<div class="controls">
						<input type="text" id="<?php echo ents($inputID); ?>" size="<?php echo (int)$contact['size']; ?>" value="<?php echo ents($contact['value']); ?>" readonly title="<?php echo ents($reason); ?>" aria-describedby="<?php echo ents($helpID); ?>" />
					</div>
				</div>
			<?php } ?>
			<p id="<?php echo ents($helpID); ?>"><i><?php echo ents($reason); ?></i></p>
		</div>
		<?php
	}

	/**
	 * Member-editable fields; staff recovery contacts are control-centre-only.
	 * Staff requiring 2FA retain the full member-area edit prohibition.
	 *
	 * @see docs/docs/developer/reference/members-contact-protection.mdx
	 * @param Person $person
	 * @return array<int, string>
	 */
	private function editablePersonFields(Person $person): array
	{
		$staff = new Staff_Member($person->id);
		if (!$staff->id) return $this->person_fields;
		if ($staff->requires2FA()) return Array();
		return array_diff($this->person_fields, Array('email', 'mobile_tel'));
	}

	private function isMe($person)
	{
		return $person->id == $GLOBALS['user_system']->getCurrentMember('id');
	}

}
