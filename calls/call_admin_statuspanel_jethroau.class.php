<?php

require_once __DIR__ . '/call_admin_statuspanel.class.php';
require_once __DIR__ . '/../include/jethroau.php';

/**
 * Status panel for the jethro.au Services section of the System
 * Configuration page. Fetched via AJAX from ?call=admin_statuspanel_jethroau.
 *
 * Attached to the JETHROAU_API_KEY setting, auto-discovered by
 * {@see View_Admin__System_Configuration::printStatusPanel()}: the symbol
 * lowercases to jethroau_api_key, whose longest match is this file.
 *
 * Shows:
 *  1. Configured: at least one jethro.au service is available ({@see JethroAu::services()}).
 *  2. Status: is the key live? For each service: on, off, or terms updated.
 *     Showing it refreshes the JETHROAU_SERVICES mirror.
 *  3. Operations: Turn on/off <service> for each available service,
 *     "Enter a key" (not on provider-managed sites), Deregister.
 *
 * See docs/docs/developer/reference/jethroau-services.md.
 *
 * @see Call_Admin_Statuspanel_Operation_Jethroau  the operations
 */
class Call_Admin_Statuspanel_Jethroau extends Call_Admin_Statuspanel
{
	/** @var array{ok: bool, message: string, data: array<string, mixed>}|null {@see JethroAu::validate()}, once getStatus() ran */
	private ?array $validated = null;

	protected function isConfigured(): \Result
	{
		$services = JethroAu::services();
		if (array_filter(array_column($services, 'available')) !== []) {
			return \Result::success(true);
		}
		$details = '';
		foreach ($services as $service) {
			$details .= '<p><strong>' . ents($service['name']) . ':</strong> ' . $service['unavailable'] . '</p>';
		}
		return \Result::failure([
			'message' => 'conf.php turns off every jethro.au service.',
			'details' => $details,
		]);
	}

	protected function getHelpText(): string
	{
		if (JethroAu::providerKey() !== '') {
			return 'jethro.au provides online services for Jethro sites. Your hosting provider registers this site '
				. 'with jethro.au; each service is turned on separately, by a sysadmin here agreeing to its privacy '
				. 'terms on behalf of your organisation.';
		}
		return 'jethro.au provides online services for Jethro sites. Registering once covers them all; '
			. 'each service is then turned on separately, by someone agreeing to its privacy terms on '
			. 'jethro.au on behalf of your organisation.';
	}

	protected function getStatus(): array
	{
		$apiKey = (string) ifdef('JETHROAU_API_KEY', '');
		if ($apiKey === '') {
			return [
				'success' => false,
				'message' => 'Not registered with jethro.au. Turn a service on below to register.',
			];
		}

		$this->validated = JethroAu::validate($apiKey);
		if (!$this->validated['ok']) {
			return ['success' => false, 'message' => $this->validated['message'] . ' Turn a service on below to register again.'];
		}
		$data = $this->validated['data'];

		$accepted = JethroAu::acceptedServices($data);
		$enabled = JethroAu::enabledServices();
		if (array_diff($accepted, $enabled) !== [] || array_diff($enabled, $accepted) !== []) {
			JethroAu::saveEnabledServices($accepted);
		}

		$details = ['API key' => $data['tokenPrefix'] ?? (substr($apiKey, 0, 8) . '…')];
		if (($data['ownerEmail'] ?? '') !== '') {
			$details['Registered by'] = $data['ownerEmail'] . (!empty($data['provider']) ? ' (' . ucfirst($data['provider']) . ')' : '');
		}
		if (($data['createdByCampaign'] ?? '') !== '') {
			$details['Registered to'] = $data['createdByCampaign'];
		}
		if (!empty($data['created'])) {
			$details['Created'] = $data['created'];
		}
		if (($data['issuedBy'] ?? '') !== '') {
			$details['Managed by'] = (string) $data['issuedBy'];
		}
		if (($data['type'] ?? 'create') !== 'create') {
			$details['Type'] = (string) $data['type'];
		}
		foreach (JethroAu::services() as $id => $service) {
			$details[$service['name']] = $this->serviceState($service, $data['services'][$id] ?? []);
		}

		$services = JethroAu::services();
		$names = array_map(fn (string $id): string => $services[$id]['name'], $accepted);
		return [
			'success' => true,
			'message' => 'Registered: API key validated by ' . JethroAu::BASE . '. '
				. ($names !== [] ? 'On: ' . implode(', ', $names) . '.' : 'No services turned on yet.'),
			'details' => $details,
		];
	}

	/**
	 * One service's line in the details table (plain text).
	 *
	 * @param array{available: bool} $service {@see JethroAu::services()} entry
	 * @param array<string, mixed>   $state   the validate response's services[id]
	 */
	private function serviceState(array $service, array $state): string
	{
		if (empty($state['accepted'])) {
			$text = !empty($state['consented'])
				? 'Off: its terms have changed, and need agreeing to again'
				: 'Off';
		} else {
			$text = 'On';
			if (!empty($state['by']) || !empty($state['at'])) {
				$text .= ' (terms agreed' . (!empty($state['by']) ? ' by ' . $state['by'] : '')
					. (!empty($state['at']) ? ' on ' . substr((string) $state['at'], 0, 10) : '') . ')';
			}
			if (empty($state['current'])) {
				$text .= '; its terms have been updated since: please review them';
			}
		}
		if (!$service['available']) {
			$text .= '. Turned off in conf.php';
		}
		return $text;
	}

	/** @return array<string, string>  operation => label */
	protected function getOperations(): array
	{
		$apiKey = (string) ifdef('JETHROAU_API_KEY', '');
		$keyOk = $apiKey !== '' && ($this->validated['ok'] ?? false);
		$states = $keyOk ? (array) ($this->validated['data']['services'] ?? []) : [];

		$ops = [];
		foreach (JethroAu::services() as $id => $service) {
			if (!$service['available']) {
				continue;
			}
			$state = (array) ($states[$id] ?? []);
			if (empty($state['accepted'])) {
				$ops['enable:' . $id] = 'Turn on ' . $service['name'];
				continue;
			}
			if (empty($state['current'])) {
				$ops['enable:' . $id] = 'Review ' . $service['name'] . ' terms';
			}
			$ops['disable:' . $id] = 'Turn off ' . $service['name'];
		}
		// A provider-managed site gets its key from the provider, never by hand.
		if (JethroAu::providerKey() === '') {
			$ops['manual'] = $apiKey === '' ? 'Enter a key' : 'Enter a different key';
		}
		if ($apiKey !== '') {
			$ops['deregister'] = 'Deregister';
		}
		return $ops;
	}
}
