<?php
/**
 * jethro.au services: online services for Jethro installations (URL
 * shortening, …) run at https://jethro.au.
 *
 * One API key per installation (the JETHROAU_API_KEY setting) identifies the
 * organisation to every service. Each service has its own privacy terms, so
 * it is enabled separately: a person signs in on jethro.au and agrees to that
 * service's terms on behalf of the organisation, and jethro.au records the
 * agreement against the key. Services refuse keys without it.
 *
 * A hosting provider can instead put its *provider key* in the shared
 * conf.php (JETHROAU_PROVIDER_KEY). Each tenant then gets its own key from
 * jethro.au without anyone signing in there ({@see provisionTenantKey()}),
 * and a tenant sysadmin turns a service on by agreeing to its terms inside
 * Jethro; the provider vouches for who agreed ({@see vouchConsent()}).
 * Keys, agreements, rate limits and attribution stay per tenant.
 *
 * Jethro mirrors the services the key may use in the hidden
 * JETHROAU_SERVICES setting (comma-separated ids), so composing an SMS needs
 * no request to jethro.au to know what to offer. The mirror is refreshed from
 * GET /api/validate whenever the System Configuration status panel
 * ({@see Call_Admin_Statuspanel_Jethroau}) is shown and after every enable,
 * disable or key change made there.
 *
 * See docs/docs/developer/reference/jethroau-services.md
 */

require_once __DIR__ . '/url_shortener.php';   // urlShortenerConfig()

class JethroAu
{
	/** The jethro.au account: signup, consent, key validation and revocation. */
	public const BASE = 'https://jethro.au';

	/**
	 * The services Jethro can use, keyed by jethro.au service id.
	 *
	 * @return array<string, array{name: string, available: bool, unavailable: string, notice: string, privacy: string, enabled: string}>
	 *         name: what the admin enables, e.g. 'URL shortening';
	 *         available: this site's conf.php lets Jethro use it;
	 *         unavailable: HTML saying how to make it available (when !available);
	 *         notice: plain text saying what using it shares with jethro.au;
	 *         privacy: the service's privacy policy URL;
	 *         enabled: sentence saying what enabling it turned on
	 */
	public static function services(): array
	{
		$url = \urlShortenerConfig();
		return [
			'url-shortener' => [
				'name' => 'URL shortening',
				'available' => $url['supported'],
				'unavailable' => 'Set <code>URLSHORTENER</code> in conf.php to <code>jethroau</code> '
					. '(or leave it undefined) to use it. Current value: <code>' . ents($url['raw']) . '</code>.',
				'notice' => 'Long web addresses in SMS messages are sent to jethro.au and stored there, '
					. 'with your organisation\'s name, so that the short links can redirect to them.',
				'privacy' => self::BASE . '/url-shortener/privacy',
				'enabled' => 'Long URLs in SMS messages can now be shortened.',
			],
		];
	}

	/**
	 * The services JETHROAU_SERVICES says the key may use.
	 *
	 * @return list<string> service ids
	 */
	public static function enabledServices(): array
	{
		$ids = array_map('trim', explode(',', (string) ifdef('JETHROAU_SERVICES', '')));
		return array_values(array_filter($ids, fn (string $id): bool => $id !== ''));
	}

	/**
	 * Whether Jethro should use service $id: a key is set, the key may use
	 * the service (per JETHROAU_SERVICES) and conf.php makes it available.
	 */
	public static function serviceEnabled(string $id): bool
	{
		return (string) ifdef('JETHROAU_API_KEY', '') !== ''
			&& in_array($id, self::enabledServices(), true)
			&& (self::services()[$id]['available'] ?? false);
	}

	/**
	 * Save the JETHROAU_SERVICES mirror. Ids Jethro doesn't know are dropped.
	 *
	 * @param list<string> $ids
	 */
	public static function saveEnabledServices(array $ids): void
	{
		$known = array_values(array_intersect(array_keys(self::services()), $ids));
		\Config_Manager::saveSetting('JETHROAU_SERVICES', implode(',', $known));
	}

	/**
	 * The services a GET /api/validate response says the key may use: those
	 * Jethro knows whose terms it has agreed to, in a version jethro.au still
	 * accepts.
	 *
	 * @param array<string, mixed> $validated {@see validate()}['data']
	 * @return list<string> service ids
	 */
	public static function acceptedServices(array $validated): array
	{
		$ids = [];
		foreach (self::services() as $id => $_) {
			if (!empty($validated['services'][$id]['accepted'])) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * Ask jethro.au whether $apiKey is live, and which services it may use.
	 *
	 * @return array{ok: bool, message: string, data: array<string, mixed>}
	 *         ok: the key is valid; message: why not (plain text) when !ok;
	 *         data: the response (type, created, ownerEmail, createdByCampaign,
	 *         services: {id: {consented, accepted, current, version, by, at, privacyUrl}}, …)
	 */
	public static function validate(string $apiKey): array
	{
		$r = self::request('GET', '/api/validate', $apiKey);
		if ($r['error'] !== '') {
			return ['ok' => false, 'message' => 'Could not reach ' . self::BASE . ': ' . $r['error'], 'data' => []];
		}
		if ($r['status'] === 401 || empty($r['data']['valid'])) {
			return ['ok' => false, 'message' => 'The API key is invalid: it was rejected by ' . self::BASE . '.', 'data' => []];
		}
		return ['ok' => true, 'message' => '', 'data' => $r['data']];
	}

	/**
	 * Start enabling a service for an existing key: jethro.au returns a
	 * one-time page (valid 10 minutes) where a person signs in and agrees to
	 * the service's terms, then returns to $redirect with ?sh_consented=<id>.
	 *
	 * @return array{ok: bool, status: int, message: string, url: string}
	 *         status: HTTP status (0 when unreachable; 401 = the key is no longer valid);
	 *         url: the consent page when ok
	 */
	public static function consentUrl(string $apiKey, string $service, string $redirect): array
	{
		$r = self::request('POST', '/api/consents', $apiKey, ['service' => $service, 'redirect' => $redirect]);
		$url = (string) ($r['data']['consentUrl'] ?? '');
		if ($r['error'] === '' && $r['status'] === 201 && str_starts_with($url, self::BASE . '/')) {
			return ['ok' => true, 'status' => 201, 'message' => '', 'url' => $url];
		}
		return ['ok' => false, 'status' => $r['status'], 'message' => self::failure($r), 'url' => ''];
	}

	/**
	 * The signup page, for a site without a working key: sign in, agree to
	 * $service's terms, and return to $redirect with ?sh_apikey=…&sh_consented=<id>.
	 * The organisation name (SYSTEM_NAME) is sent as the key's campaign.
	 */
	public static function signupUrl(string $service, string $redirect): string
	{
		$query = ['redirect' => $redirect, 'service' => $service];
		$campaign = defined('SYSTEM_NAME') ? (string) SYSTEM_NAME : '';
		if ($campaign !== '') {
			$query['campaign'] = $campaign;
		}
		return self::BASE . '/signup?' . http_build_query($query);
	}

	/**
	 * Withdraw the key's agreement to a service's terms. True when jethro.au
	 * confirmed (or the key is already gone).
	 */
	public static function withdrawConsent(string $apiKey, string $service): bool
	{
		$r = self::request('DELETE', '/api/consents/' . rawurlencode($service), $apiKey);
		return $r['error'] === '' && in_array($r['status'], [200, 401], true);
	}

	/**
	 * Revoke the key on jethro.au, which also scrubs the organisation's name
	 * from its short URLs. True when revoked (or already gone).
	 */
	public static function revoke(string $apiKey): bool
	{
		$r = self::request('DELETE', '/api/keys/mine', $apiKey);
		return $r['error'] === '' && in_array($r['status'], [200, 401, 404], true);
	}

	/**
	 * The hosting provider's key (JETHROAU_PROVIDER_KEY, conf.php only); ''
	 * when this site registers itself with jethro.au.
	 */
	public static function providerKey(): string
	{
		return (string) ifdef('JETHROAU_PROVIDER_KEY', '');
	}

	/**
	 * This tenant's stable id at the provider: JETHROAU_TENANT, else BASE_URL's
	 * host, else the name of the database Jethro is connected to (asked of the
	 * server, since conf.php may give only DB_DSN). jethro.au keeps one key per
	 * provider and tenant id, so it must not change and must differ between
	 * tenants. '' only when none is known.
	 */
	public static function tenantId(): string
	{
		$tenant = (string) ifdef('JETHROAU_TENANT', '');
		if ($tenant === '' && defined('BASE_URL')) {
			$tenant = (string) parse_url((string) BASE_URL, PHP_URL_HOST);
		}
		if ($tenant === '' && isset($GLOBALS['db'])) {
			$tenant = (string) $GLOBALS['db']->queryOne('SELECT DATABASE()');
		}
		return $tenant;
	}

	/**
	 * Get this tenant a key from jethro.au with the provider key. A tenant
	 * that already has one gets a new one in its place, keeping its
	 * agreements. The organisation name (SYSTEM_NAME) is recorded with it.
	 *
	 * @return array{ok: bool, message: string, token: string}
	 */
	public static function provisionTenantKey(): array
	{
		$r = self::request('POST', '/api/tenants', self::providerKey(), [
			'tenant' => self::tenantId(),
			'name'   => defined('SYSTEM_NAME') ? (string) SYSTEM_NAME : '',
		]);
		$token = (string) ($r['data']['token'] ?? '');
		if ($r['error'] === '' && $r['status'] === 201 && $token !== '') {
			return ['ok' => true, 'message' => '', 'token' => $token];
		}
		return ['ok' => false, 'message' => self::failure($r), 'token' => ''];
	}

	/**
	 * Record, with the provider key, that $name <$email>, a sysadmin of this
	 * tenant, agreed to $service's terms on the organisation's behalf.
	 *
	 * @return array{ok: bool, message: string}
	 */
	public static function vouchConsent(string $service, string $name, string $email): array
	{
		$r = self::request(
			'PUT',
			'/api/tenants/' . rawurlencode(self::tenantId()) . '/consents/' . rawurlencode($service),
			self::providerKey(),
			['agree' => true, 'by' => ['name' => $name, 'email' => $email]]
		);
		if ($r['error'] === '' && $r['status'] === 200) {
			return ['ok' => true, 'message' => ''];
		}
		return ['ok' => false, 'message' => self::failure($r)];
	}

	/**
	 * Plain-text reason a request failed.
	 *
	 * @param array{status: int, error: string, data: array<string, mixed>} $r {@see request()}
	 */
	private static function failure(array $r): string
	{
		return $r['error'] !== ''
			? 'Could not reach ' . self::BASE . ': ' . $r['error']
			: self::BASE . ' refused: ' . ($r['data']['error'] ?? 'HTTP ' . $r['status']);
	}

	/**
	 * One request to the jethro.au account API.
	 *
	 * @param array<string, mixed>|null $json request body
	 * @return array{status: int, error: string, data: array<string, mixed>}
	 *         error: curl's error ('' when a response arrived); data: decoded JSON ([] when not JSON)
	 */
	private static function request(string $method, string $path, string $apiKey, ?array $json = null): array
	{
		$headers = ['Authorization: Bearer ' . $apiKey];
		$ch = curl_init(self::BASE . $path);
		$options = [
			CURLOPT_CUSTOMREQUEST  => $method,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 5,
			CURLOPT_CONNECTTIMEOUT => 3,
		];
		if ($json !== null) {
			$headers[] = 'Content-Type: application/json';
			$options[CURLOPT_POSTFIELDS] = json_encode($json);
		}
		$options[CURLOPT_HTTPHEADER] = $headers;
		curl_setopt_array($ch, $options);
		$body = curl_exec($ch);
		$data = json_decode((string) $body, true);
		return [
			'status' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
			'error'  => curl_error($ch),
			'data'   => is_array($data) ? $data : [],
		];
	}
}
