<?php

/**
 * AJAX endpoint for registering and validating a sender phone number
 * with the SMS provider.
 *
 * Actions:
 *   ?call=sms_sendernum&action=register&number=...&label=...
 *     Initiates sender number registration via the provider.
 *     Returns an HTML snippet showing validation UI for the next step
 *     (e.g. an OTP input for Cellcast, or a message about a verification
 *     link for 5CentSMS v5).  The caller injects this HTML into the page.
 *
 *   ?call=sms_sendernum&action=validate&number=...&label=...&otp=...
 *     Submits validation fields to the provider to complete sender number
 *     verification.  Returns an HTML snippet (success or error).
 *
 * Requires PERM_SENDSMS, and only for the current user's own mobile number.
 */

class Call_SMS_SenderNum extends Call
{
	function run(): void
	{
		$label = (string) ($_REQUEST['label'] ?? '');

		if (!$GLOBALS['user_system']->havePerm(PERM_SENDSMS)) {
			echo $this->errorFragment($label, _('Permission denied'));
			return;
		}

		// register creates the number upstream and texts an OTP; validate
		// submits codes. Both are state changes, so require POST (which also
		// gets the Sec-Fetch-Site origin check).
		if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
			http_response_code(405);
			echo $this->errorFragment($label, _('This action requires a POST request'));
			return;
		}

		require_once JETHRO_ROOT . '/include/jethro_sms.php';

		$requested = trim((string) ($_REQUEST['number'] ?? ''));
		if ($requested === '') {
			echo $this->errorFragment($label, _('Phone number is required'));
			return;
		}

		// Only the logged-in user's own mobile may be registered or verified:
		// a verified number can then be used as the _USER_MOBILE_ sender, so
		// registering someone else's would let one user send as another
		// (SMS review Q9).
		$own = \Jethro\Sms\getCurrentUserMobileNumber();
		try {
			$phone = new \Sms\PhoneNumber($requested);
		} catch (\InvalidArgumentException $e) {
			echo $this->errorFragment($label, _('Invalid phone number'));
			return;
		}
		if ($own === null || !$phone->sameNumberAs($own)) {
			echo $this->errorFragment($label, _('You can only verify your own mobile number, as set on your person record.'));
			return;
		}

		$action = $_REQUEST['action'] ?? '';

		if ($action === 'register') {
			$this->handleRegister($phone, $requested, $label);
			return;
		}

		if ($action === 'validate') {
			$this->handleValidate($phone, $label);
			return;
		}

		echo $this->errorFragment($label, _('Unknown action'));
	}

	/**
	 * The #sms-register-<label> error fragment Datastar morphs into the page.
	 */
	private function errorFragment(string $label, string $message): string
	{
		return '<div id="sms-register-' . ents($label) . '" class="sms-register-status" style="margin-top:4px;font-size:0.9em;color:#c00">'
			. ents($message) . '</div>';
	}

	/**
	 * Initiate sender number registration (Phase 1 — register + schema).
	 */
	private function handleRegister(\Sms\PhoneNumber $phone, string $number, string $label): void
	{
		if ($label === '') {
			echo $this->errorFragment($label, _('Label is required'));
			return;
		}

		$providerResult = \Jethro\Sms\getSmsProvider();
		if ($providerResult->isFailure()) {
			echo $this->errorFragment($label, _('SMS not configured'));
			return;
		}

		$provider = $providerResult->getValue();

		if (!$provider->hasCapability(\Sms\SmsCapability::REGISTER_SENDER_NUMBER)) {
			echo $this->errorFragment($label, _('Not supported by this provider'));
			return;
		}

		$contact = new \Sms\ContactPhoneNumber($phone, $label);
		$result = $provider->registerSenderNumber($contact, null);

		if ($result->isFailure()) {
			echo $this->errorFragment($label, $result->getError());
			return;
		}

		$step = $result->getValue();
		$html = $this->renderRegisterResult($step, $number, $label);
		echo '<div id="sms-register-' . ents($label) . '" class="sms-register-status" style="margin-top:4px;font-size:0.9em">'
			. $html . '</div>';
	}

	/**
	 * Complete sender number validation (Phase 2 — submit).
	 */
	private function handleValidate(\Sms\PhoneNumber $phone, string $label): void
	{
		$providerResult = \Jethro\Sms\getSmsProvider();
		if ($providerResult->isFailure()) {
			echo $this->errorFragment($label, _('SMS not configured'));
			return;
		}

		$provider = $providerResult->getValue();

		if (!$provider->hasCapability(\Sms\SmsCapability::REGISTER_SENDER_NUMBER)) {
			echo $this->errorFragment($label, _('Not supported by this provider'));
			return;
		}

		$params = [];
		foreach ($_REQUEST as $k => $v) {
			if ($k !== 'action' && $k !== 'call' && $k !== 'number' && $k !== 'label') {
				$params[$k] = $v;
			}
		}

		$contact = new \Sms\ContactPhoneNumber($phone, $label);
		$result = $provider->registerSenderNumber($contact, $params);

		if ($result->isFailure()) {
			echo $this->errorFragment($label, $result->getError());
			return;
		}

		$step = $result->getValue();
		$html = \Jethro\Sms\renderRegistrationStepHtml($step);
		echo '<div id="sms-register-' . ents($label) . '" class="sms-register-status" style="margin-top:4px;font-size:0.9em">'
			. $html . '</div>';
	}

	/**
	 * Render the registerSenderNumber Phase 1 result as HTML.
	 *
	 * @param \Sms\RegistrationStep $step    Result from registerSenderNumber Phase 1
	 * @param string $number  Phone number (for hidden field)
	 * @param string $label   Human-readable label (for hidden field)
	 */
	private function renderRegisterResult(\Sms\RegistrationStep $step, string $number, string $label): string
	{
		$html = '';

		if ($step->message !== '') {
			$html .= '<p><em>' . ents($step->message) . '</em></p>';
		}
		if ($step->instructions !== '') {
			$html .= '<p>' . ents($step->instructions) . '</p>';
		}

		// Already registered — no further action needed
		if ($step->registered && $step->isComplete()) {
			return $html;
		}

		if ($step->fields !== []) {
			$html .= '<div class="sms-register-otp" style="display:block">';
			$html .= '<input type="hidden" name="action" value="validate">';
			$html .= '<input type="hidden" name="number" value="' . ents($number) . '">';
			$html .= '<input type="hidden" name="label" value="' . ents($label) . '">';
			// One visible field, bound to the $otp signal (all current providers
			// ask for a single code). The signal value is appended to the URL.
			$otpField = null;
			foreach ($step->fields as $f) {
				if ($f->type === 'hidden') continue;
				$otpField = $f;
				$html .= '<input type="text" class="sms-otp-input"'
					. ' name="' . ents($f->name) . '"'
					. ' data-bind:otp'
					. ' placeholder="' . ents($f->label) . '"'
					. ' style="width:12em">';
				break;
			}
			if ($otpField !== null) {
				$url = '?call=sms_sendernum&action=validate&number=' . urlencode($number)
					. '&label=' . urlencode($label) . '&' . urlencode($otpField->name) . '=';
				$html .= ' <button type="button" class="sms-otp-submit btn"'
					. ' data-on:click="' . ents("@post('" . $url . "' + encodeURIComponent(\$otp))") . '"'
					. '>' . ents(_('Verify')) . '</button>';
			}
			$html .= '<div class="sms-register-error" style="display:none;color:red;margin-top:4px"></div>';
			$html .= '</div>';
		}

		return $html;
	}
}
