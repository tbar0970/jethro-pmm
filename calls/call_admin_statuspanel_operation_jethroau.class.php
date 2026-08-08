<?php

require_once __DIR__ . '/call_admin_statuspanel_operation.class.php';
require_once __DIR__ . '/../include/jethroau.php';

/**
 * Operation handler for the jethro.au Services status panel
 * ({@see Call_Admin_Statuspanel_Jethroau}).
 *
 * GET responses return the morphable container div containing the operation
 * form; POST responses return it containing the result. Datastar morphs both
 * into the DOM by container ID.
 *
 * URL: ?call=admin_statuspanel_operation_jethroau&operation=<name>
 *
 * Operations:
 *  - enable:<service> ("Turn on"): agree to a service's terms.
 *      Provider-managed site (JETHROAU_PROVIDER_KEY set): a form here; its
 *      POST gets the tenant a key if it has none, and records the agreement
 *      by the logged-in sysadmin with the provider key. Nobody signs in on
 *      jethro.au.
 *      Otherwise, on jethro.au: with a working key, POST /api/consents
 *      returns a one-time consent page; without one, the signup page
 *      registers the site and agrees in one go. Either way jethro.au returns
 *      to operation=return.
 *  - return: where jethro.au sends the browser back.
 *      ?sh_apikey=…&sh_email=…[&sh_consented=<id>] (signup): a confirmation
 *      form whose POST saves the key; saving straight from the GET would let
 *      a link swap in an attacker's key.
 *      ?sh_consented=<id> (consent for the existing key): nothing to save.
 *  - disable:<service> ("Turn off"): withdraw the agreement on jethro.au.
 *  - manual: paste a key.
 *  - deregister: revoke the key on jethro.au and blank JETHROAU_API_KEY.
 *
 * Every change ends by re-reading the key's services from jethro.au into the
 * JETHROAU_SERVICES mirror ({@see JethroAu}).
 *
 * See docs/docs/developer/reference/jethroau-services.md.
 */
class Call_Admin_Statuspanel_Operation_Jethroau extends Call_Admin_Statuspanel_Operation
{
	/**
	 * Dispatch to the requested operation, after {@see parent::run()}'s
	 * sysadmin permission check.
	 */
	public function run(): void
	{
		if (!parent::run()) {
			return;
		}

		[$operation, $service] = array_pad(explode(':', $this->operation(), 2), 2, '');
		match ($operation) {
			'enable'     => $this->doEnable($service),
			'disable'    => $this->doDisable($service),
			'return'     => $this->doReturn(),
			'manual'     => $this->doManual(),
			'deregister' => $this->doDeregister(),
			default      => $this->echoContainer('<p class="text-error">Unknown operation.</p>'),
		};
	}

	// ------------------------------------------------------------------
	// Enable / disable a service
	// ------------------------------------------------------------------

	/**
	 * Provider-managed: the agreement form here (GET) and recording it (POST).
	 * Otherwise GET: say what the service shares, and link to jethro.au to agree.
	 */
	private function doEnable(string $id): void
	{
		$service = $this->service($id);
		if ($service === null) {
			return;
		}
		if (!$service['available']) {
			$this->echoContainer('<div class="alert alert-error">' . $service['unavailable'] . '</div>');
			return;
		}

		if (JethroAu::providerKey() !== '') {
			$this->enableHere($id, $service);
			return;
		}
		$name = ents($service['name']);
		$intro = '';
		$apiKey = (string) ifdef('JETHROAU_API_KEY', '');
		if ($apiKey !== '') {
			$consent = JethroAu::consentUrl($apiKey, $id, $this->returnUrl());
			if ($consent['ok']) {
				$this->echoContainer($this->notice($service) . $this->continueLink(
					$consent['url'],
					"jethro.au will ask you to sign in and agree to the terms of {$name} on behalf of your organisation, "
					. 'then return here. The link works once, for 10 minutes.'
				));
				return;
			}
			if ($consent['status'] !== 401) {
				$this->echoContainer('<div class="alert alert-error">' . ents($consent['message']) . '</div>');
				return;
			}
			$intro = 'The saved API key is no longer valid, so this registers the site again. ';
		}

		$callName = ents($this->callName());
		$this->echoContainer($this->notice($service) . $this->continueLink(
			JethroAu::signupUrl($id, $this->returnUrl()),
			$intro . 'jethro.au will ask you to sign in (Google, GitHub or an emailed link) and agree to the terms of '
			. "{$name} on behalf of your organisation, then return here with an API key for this site. "
			. "If you already have a key, <a href=\"#\" data-on:click__prevent=\"@get('?call={$callName}&amp;operation=manual')\">enter it</a> first."
		));
	}

	/**
	 * Turn a service on for a provider-managed site: the logged-in sysadmin
	 * ticks agreement to its terms here, and the provider vouches for them
	 * to jethro.au. A site without a key of the provider's (none yet, or one
	 * registered some other way) is given one first.
	 *
	 * @param array{name: string, notice: string, privacy: string, enabled: string} $service
	 */
	private function enableHere(string $id, array $service): void
	{
		$name = ents($service['name']);
		$org = ents(defined('SYSTEM_NAME') ? (string) SYSTEM_NAME : 'your organisation');

		if ($_SERVER['REQUEST_METHOD'] === 'GET') {
			$callName = ents($this->callName());
			$op = ents('enable:' . $id);
			// A link inside the label opens the policy without ticking the box.
			$policy = $service['privacy'] !== ''
				? '<a href="' . ents($service['privacy']) . '" target="_blank" rel="noopener noreferrer">privacy policy</a>'
				: 'privacy policy';
			$this->echoContainer($this->notice($service) . <<<HTML
				<form onsubmit="return false" class="status-panel-op-form" data-operation="{$op}" data-signals:jethroauagree="false">
					<input type="hidden" name="operation" value="{$op}">
					<label class="checkbox">
						<input type="checkbox" name="agree" value="1" data-bind:jethroauagree>
						I agree to the {$policy} of {$name} on behalf of {$org}
					</label>
					<button type="button" class="btn btn-primary" data-attr:disabled="!\$jethroauagree"
					        data-on:click="@post('?call={$callName}', {contentType: 'form'})">Turn on {$name}</button>
					<button type="button" class="btn" style="margin-left: 0.5em;"
					        onclick="var c = this.closest('.status-panel-op-container'); c.classList.remove('visible'); c.innerHTML = '';">Cancel</button>
				</form>
				HTML);
			return;
		}

		if (($_POST['agree'] ?? '') !== '1') {
			$this->echoContainer('<div class="alert alert-error">Tick the box to agree to the privacy policy of ' . $name . ' first.</div>');
			return;
		}
		$user = $GLOBALS['user_system']->getCurrentUser();
		$email = trim((string) ($user['email'] ?? ''));
		$person = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
		if ($email === '') {
			$this->echoContainer('<div class="alert alert-error">Your user account needs an email address: '
				. 'jethro.au records who agreed on ' . $org . '\'s behalf.</div>');
			return;
		}

		if (JethroAu::tenantId() === '') {
			$this->echoContainer('<div class="alert alert-error"><strong>This site has no tenant id for jethro.au.</strong> '
				. 'Define <code>JETHROAU_TENANT</code> in conf.php: an id unique to this site that will never change.</div>');
			return;
		}
		$apiKey = (string) ifdef('JETHROAU_API_KEY', '');
		$check = $apiKey !== '' ? JethroAu::validate($apiKey) : ['ok' => false, 'data' => []];
		if (!$check['ok'] || empty($check['data']['tenant'])) {
			$tenantKey = JethroAu::provisionTenantKey();
			if (!$tenantKey['ok']) {
				$this->echoContainer('<div class="alert alert-error"><strong>Could not register this site with jethro.au.</strong> '
					. ents($tenantKey['message']) . '</div>');
				return;
			}
			Config_Manager::saveSetting('JETHROAU_API_KEY', $tenantKey['token']);
			$apiKey = $tenantKey['token'];
		}

		$agreed = JethroAu::vouchConsent($id, $person, $email);
		if (!$agreed['ok']) {
			$this->echoContainer('<div class="alert alert-error"><strong>' . $name . ' was not turned on.</strong> '
				. ents($agreed['message']) . '</div>');
			return;
		}
		$accepted = $this->refreshServices($apiKey);
		if ($accepted === null) {
			return;
		}
		$this->echoDone('alert-success', "<strong>{$name} is on.</strong> " . ents($service['enabled']));
	}

	/**
	 * GET: confirm. POST: withdraw the agreement on jethro.au and stop using the service.
	 */
	private function doDisable(string $id): void
	{
		$service = $this->service($id);
		if ($service === null) {
			return;
		}
		$name = ents($service['name']);

		if ($_SERVER['REQUEST_METHOD'] === 'GET') {
			$this->echoContainer($this->confirmForm(
				'disable:' . $id,
				"<strong>Turn off {$name}?</strong> Jethro stops using it, and your organisation's agreement to its "
				. 'terms is withdrawn on jethro.au. Turning it on again means agreeing again.',
				"Turn off {$name}"
			));
			return;
		}

		$apiKey = (string) ifdef('JETHROAU_API_KEY', '');
		$withdrawn = $apiKey === '' || JethroAu::withdrawConsent($apiKey, $id);
		JethroAu::saveEnabledServices(array_values(array_diff(JethroAu::enabledServices(), [$id])));

		if ($withdrawn) {
			$this->echoDone('alert-success', "<strong>{$name} is off.</strong>");
		} else {
			$this->echoDone('alert-warning', "<strong>{$name} is off here,</strong> but withdrawing the agreement on "
				. 'jethro.au failed. Try again later, or Deregister.');
		}
	}

	// ------------------------------------------------------------------
	// Return from jethro.au
	// ------------------------------------------------------------------

	/**
	 * Handle the browser's return from the jethro.au signup or consent page.
	 */
	private function doReturn(): void
	{
		if (isset($_POST['sh_apikey'])) {
			$this->saveKey((string) $_POST['sh_apikey'], (string) ($_POST['sh_email'] ?? ''));
			return;
		}
		if (isset($_GET['sh_apikey'])) {
			$this->echoContainer($this->buildSignupConfirmForm((string) $_GET['sh_apikey'], (string) ($_GET['sh_email'] ?? '')));
			return;
		}

		// Consent for the existing key: nothing to save; jethro.au is the
		// record, so ask it rather than trusting the query string.
		$id = (string) ($_GET['sh_consented'] ?? '');
		$service = $this->service($id);
		if ($service === null) {
			return;
		}
		$accepted = $this->refreshServices();
		if ($accepted === null) {
			return;
		}
		$name = ents($service['name']);
		if (in_array($id, $accepted, true)) {
			$this->echoDone('alert-success', "<strong>{$name} is on.</strong> " . ents($service['enabled']));
		} else {
			$this->echoDone('alert-warning', "<strong>{$name} is still off:</strong> jethro.au has no agreement to its terms for this site's API key.");
		}
	}

	/**
	 * The confirmation form shown when signup returns with a key.
	 *
	 * The signup flow returns here by top-level navigation, so this page is
	 * the bare container without Datastar: a plain POST form, covered by the
	 * request-origin check.
	 */
	private function buildSignupConfirmForm(string $token, string $email): string
	{
		$callName = ents($this->callName());
		$valid    = self::validKey($token);
		$who      = $email !== '' ? '<strong>' . ents($email) . '</strong>' : 'your account';
		$disabled = $valid ? '' : 'disabled';
		$warning  = $valid ? '' : '<div class="alert alert-error">That return address did not contain a valid key. '
			. '<a href="' . ents($this->settingsUrl()) . '">Return to system settings</a> and try again.</div>';
		$masked   = ents(substr($token, 0, 8) . '…');
		$tokenRaw = ents($token);
		$emailRaw = ents($email);
		$settings = ents($this->settingsUrl());

		return <<<HTML
			{$warning}
			<form method="post" action="?call={$callName}">
				<input type="hidden" name="operation" value="return">
				<input type="hidden" name="sh_apikey" value="{$tokenRaw}">
				<input type="hidden" name="sh_email" value="{$emailRaw}">
				<div class="alert alert-info">
					jethro.au returned an API key for {$who}.
					Save it as this site's jethro.au key?
				</div>
				<p>Key: <code>{$masked}</code></p>
				<button type="submit" class="btn btn-primary" {$disabled}>Save API key</button>
				<a class="btn" href="{$settings}">Cancel</a>
			</form>
			HTML;
	}

	// ------------------------------------------------------------------
	// Manual key entry
	// ------------------------------------------------------------------

	/**
	 * GET: the key-entry form. POST: save the key.
	 */
	private function doManual(): void
	{
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$this->saveKey(trim((string) ($_POST['api_key'] ?? '')), '');
			return;
		}

		$callName = ents($this->callName());
		$this->echoContainer(<<<HTML
			<form onsubmit="return false" class="status-panel-op-form" data-operation="manual">
				<input type="hidden" name="operation" value="manual">
				<div class="control-group">
					<label class="control-label" for="jethroau_apikey_manual">jethro.au API key</label>
					<div class="controls">
						<input type="text" name="api_key" id="jethroau_apikey_manual"
						       placeholder="cus_…" pattern="cus_[a-f0-9]{32}" required
						       style="width: 100%; max-width: 30em;">
						<span class="help-inline">Starts with <code>cus_</code> followed by 32 hex digits.
							Services it has agreed to on jethro.au are turned on.</span>
					</div>
				</div>
				<div class="control-group">
					<div class="controls">
						<button type="button" class="btn btn-primary"
						        data-on:click="@post('?call={$callName}', {contentType: 'form'})">Save API key</button>
					</div>
				</div>
			</form>
			HTML);
	}

	// ------------------------------------------------------------------
	// Deregister
	// ------------------------------------------------------------------

	/**
	 * GET: confirm. POST: revoke the key on jethro.au, blank the settings.
	 */
	private function doDeregister(): void
	{
		$apiKey = (string) ifdef('JETHROAU_API_KEY', '');
		$prefix = ents(substr($apiKey, 0, 8) . '…');

		if ($_SERVER['REQUEST_METHOD'] === 'GET') {
			$this->echoContainer($this->confirmForm(
				'deregister',
				'<strong>Deregister from jethro.au?</strong> This will:<ul>'
				. '<li>Revoke the API key <code>' . $prefix . '</code> on jethro.au, with its agreements to service terms</li>'
				. '<li>Remove your email and organisation name from any shortened URLs (the URLs themselves keep working)</li>'
				. '<li>Remove the key from this configuration, so Jethro stops using every jethro.au service</li></ul>',
				'Yes, deregister'
			));
			return;
		}

		$revoked = $apiKey !== '' && JethroAu::revoke($apiKey);
		// Blank the local settings regardless: the key is no use to this site now.
		Config_Manager::saveSetting('JETHROAU_API_KEY', '');
		JethroAu::saveEnabledServices([]);

		if ($revoked) {
			$this->echoDone('alert-success', '<strong>Deregistered.</strong> The key <code>' . $prefix
				. '</code> has been revoked and removed from your configuration.');
		} else {
			$this->echoDone('alert-warning', '<strong>Partially deregistered.</strong> The key <code>' . $prefix
				. '</code> has been removed from your configuration, but revoking it on jethro.au failed. '
				. 'It may already have been revoked.');
		}
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/** Whether $token looks like a jethro.au API key. */
	private static function validKey(string $token): bool
	{
		return (bool) preg_match('/^cus_[a-f0-9]{32}$/', $token);
	}

	/**
	 * Save $token as JETHROAU_API_KEY if jethro.au accepts it, mirror the
	 * services it may use into JETHROAU_SERVICES, and echo the result.
	 *
	 * @param string $email who signed up ('' when entered by hand)
	 */
	private function saveKey(string $token, string $email): void
	{
		if (!self::validKey($token)) {
			$this->echoContainer('<div class="alert alert-error"><strong>Invalid key.</strong> '
				. 'A jethro.au API key is <code>cus_</code> followed by 32 hex digits.</div>');
			return;
		}
		// Only a key jethro.au accepts is saved.
		$check = JethroAu::validate($token);
		if (!$check['ok']) {
			$this->echoDone('alert-error', '<strong>API key not saved.</strong> ' . ents($check['message']));
			return;
		}
		Config_Manager::saveSetting('JETHROAU_API_KEY', $token);
		$accepted = JethroAu::acceptedServices($check['data']);
		JethroAu::saveEnabledServices($accepted);

		$services = JethroAu::services();
		$names = array_map(fn (string $id): string => $services[$id]['name'], $accepted);
		$saved = '<strong>API key ' . ($email !== '' ? 'registered for ' . ents($email) : 'saved') . '.</strong> '
			. 'The key <code>' . ents(substr($token, 0, 8) . '…') . '</code> has been saved to your configuration. ';
		$saved .= $names !== []
			? 'On: ' . ents(implode(', ', $names)) . '.'
			: 'It has not agreed to the terms of any service yet: use Turn on in the jethro.au Services panel.';
		$this->echoDone('alert-success', $saved);
	}

	/**
	 * Re-read the key's services from jethro.au into JETHROAU_SERVICES.
	 * On failure echoes the error container and returns null.
	 *
	 * @param string|null $apiKey the key, when just saved (the JETHROAU_API_KEY
	 *        constant keeps its old value for the rest of the request); default JETHROAU_API_KEY
	 * @return list<string>|null the services the key may use
	 */
	private function refreshServices(?string $apiKey = null): ?array
	{
		$check = JethroAu::validate($apiKey ?? (string) ifdef('JETHROAU_API_KEY', ''));
		if (!$check['ok']) {
			$this->echoDone('alert-error', ents($check['message']));
			return null;
		}
		$accepted = JethroAu::acceptedServices($check['data']);
		JethroAu::saveEnabledServices($accepted);
		return $accepted;
	}

	/**
	 * The {@see JethroAu::services()} entry for $id; echoes an error and
	 * returns null when Jethro doesn't know it.
	 *
	 * @return array{name: string, available: bool, unavailable: string, notice: string, privacy: string, enabled: string}|null
	 */
	private function service(string $id): ?array
	{
		$service = JethroAu::services()[$id] ?? null;
		if ($service === null) {
			$this->echoContainer('<p class="text-error">Unknown jethro.au service.</p>');
		}
		return $service;
	}

	/**
	 * What a service shares, with its privacy policy link.
	 *
	 * @param array{name: string, notice: string, privacy: string} $service
	 */
	private function notice(array $service): string
	{
		return '<div class="alert alert-warning jethroau-consent"><strong>Privacy:</strong> ' . ents($service['notice'])
			. ($service['privacy'] !== ''
				? ' Please read the <a href="' . ents($service['privacy']) . '" target="_blank" rel="noopener noreferrer">privacy policy</a> first.'
				: '')
			. '</div>';
	}

	/**
	 * A button opening $url (on jethro.au) in a new tab, after $html.
	 */
	private function continueLink(string $url, string $html): string
	{
		return '<p>' . $html . '</p><p><a class="btn btn-primary" href="' . ents($url)
			. '" target="_blank" rel="noopener noreferrer">Continue to jethro.au</a></p>';
	}

	/**
	 * A confirmation alert with a Datastar POST button for $operation, and Cancel.
	 *
	 * @param string $html  the alert's inner HTML
	 * @param string $label the confirm button's text (plain)
	 */
	private function confirmForm(string $operation, string $html, string $label): string
	{
		$callName = ents($this->callName());
		$op = ents($operation);
		$label = ents($label);
		return <<<HTML
			<div class="alert alert-warning">{$html}</div>
			<form onsubmit="return false" class="status-panel-op-form" data-operation="{$op}">
				<input type="hidden" name="operation" value="{$op}">
				<button type="button" class="btn btn-danger"
				        data-on:click="@post('?call={$callName}', {contentType: 'form'})">{$label}</button>
				<button type="button" class="btn" style="margin-left: 0.5em;"
				        onclick="var c = this.closest('.status-panel-op-container'); c.classList.remove('visible'); c.innerHTML = '';">Cancel</button>
			</form>
			HTML;
	}

	/**
	 * Echo a result alert that links back to, and soon reloads, the settings page.
	 *
	 * @param string $class alert class, e.g. 'alert-success'
	 * @param string $html  the alert's inner HTML
	 */
	private function echoDone(string $class, string $html): void
	{
		$settings = ents($this->settingsUrl());
		$this->echoContainer('<div class="alert ' . $class . '">' . $html
			. ' <a href="' . $settings . '">Return to system settings</a>.</div>'
			. ($class === 'alert-success' ? '<meta http-equiv="refresh" content="3;url=' . $settings . '">' : ''));
	}

	/**
	 * Where jethro.au sends the browser back: this handler, operation=return.
	 * Absolute, as jethro.au redirects to it.
	 */
	private function returnUrl(): string
	{
		// request_scheme_and_host() applies the X-Forwarded/ALLOWED_HOSTS
		// rules: a poisoned host would send the new key elsewhere.
		$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		return request_scheme_and_host() . $path . '?call=' . $this->callName() . '&operation=return';
	}

	/** The System Configuration page, where the status panel is shown. */
	private function settingsUrl(): string
	{
		return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) . '?view=admin__system_configuration';
	}
}
