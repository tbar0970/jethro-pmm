<?php

namespace Jethro\Sms;

use Sms\SmsCache;

/**
 * SmsCache implementation backed by PHP $_SESSION.
 *
 * Keys are prefixed with 'sms_cache_' to avoid collisions with other
 * session data.  The session is lazily initialised if not already started
 * (important for CLI scripts that don't run through the web SAPI).
 *
 * No TTL — entries persist until the session ends or the provider
 * explicitly deletes them (e.g. balance is deleted after send).
 *
 * Used by FiveCentSmsV5Provider to cache balance and sender IDs across
 * requests.  TemplateSmsProvider ignores the cache (balance is unsupported).
 *
 * ## Config fingerprint
 *
 * An optional fingerprint parameter lets the caller identify the provider
 * configuration that produced the cached data.  When the fingerprint
 * changes — because the user changed SMS_PROVIDER, API keys, or other
 * constants — all previously-cached entries are treated as misses,
 * preventing stale sender IDs or balances from surviving a provider
 * switch.  Entries written without a fingerprint (legacy) and entries
 * whose fingerprint matches continue to be returned as before.
 */
final class SessionSmsCache implements \Sms\SmsCache
{
	private string $prefix;
	private string $fingerprint;

	public function __construct(string $prefix = 'sms_cache_', string $fingerprint = '')
	{
		$this->prefix = $prefix;
		$this->fingerprint = $fingerprint;
		if (!isset($_SESSION)) {
			$_SESSION = [];
		}
	}

	public function get(string $key): mixed
	{
		$entry = $_SESSION[$this->prefix . $key] ?? null;
		if (!\is_array($entry) || !isset($entry['value'])) {
			// Legacy: plain value stored before TTL was added
			return $entry;
		}
		if ($this->fingerprint !== '' && ($entry['fingerprint'] ?? '') !== $this->fingerprint) {
			unset($_SESSION[$this->prefix . $key]);
			return null;
		}
		if ($entry['expires'] > 0 && time() > $entry['expires']) {
			unset($_SESSION[$this->prefix . $key]);
			return null;
		}
		return $entry['value'];
	}

	public function set(string $key, mixed $value, int $ttl = 0): void
	{
		$_SESSION[$this->prefix . $key] = [
			'value'       => $value,
			'expires'     => $ttl > 0 ? time() + $ttl : 0,
			'fingerprint' => $this->fingerprint,
		];
	}

	public function delete(string $key): void
	{
		unset($_SESSION[$this->prefix . $key]);
	}
}
