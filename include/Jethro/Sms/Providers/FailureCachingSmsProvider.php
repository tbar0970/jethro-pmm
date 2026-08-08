<?php

namespace Jethro\Sms\Providers;

/**
 * SmsProvider decorator that remembers failed account lookups for a short
 * time, so an unreachable or misconfigured gateway doesn't stall every page.
 *
 * The SMS modal, sender dropdown, statusline and mobile-number links call
 * getBalance(), getSenderIds(), getSenderNumbers(), verifySenderNumber() and
 * listOptOuts() on ordinary page loads. The raw providers session-cache only
 * successes, so while the gateway is down every page view would wait on each
 * call's HTTP timeout. A failure is cached for {@see TTL} seconds and returned
 * without an upstream call until it expires.
 *
 * Wraps the raw provider directly, sharing its config fingerprint, so a
 * settings change (e.g. a corrected API key) invalidates cached failures.
 * Sends, status polls, cancels and registrations are never cached.
 */
final class FailureCachingSmsProvider extends \Sms\DecoratingSmsProvider
{
	/** Seconds a failed lookup is remembered. */
	public const TTL = 60;

	public function __construct(\Sms\SmsProvider $inner, private \Sms\SmsCache $cache)
	{
		parent::__construct($inner);
	}

	public function getBalance(): \Result
	{
		return $this->remember('balance', fn() => parent::getBalance());
	}

	public function getSenderIds(bool $getAll = false): \Result
	{
		return $this->remember('senderids_' . (int) $getAll, fn() => parent::getSenderIds($getAll));
	}

	public function getSenderNumbers(): \Result
	{
		return $this->remember('sendernumbers', fn() => parent::getSenderNumbers());
	}

	public function verifySenderNumber(\Sms\PhoneNumber $number): \Result
	{
		return $this->remember('verify_' . $number->value, fn() => parent::verifySenderNumber($number));
	}

	public function listOptOuts(): \Result
	{
		return $this->remember('optouts', fn() => parent::listOptOuts());
	}

	/**
	 * Return the cached failure for $key if still fresh, else call $fetch and
	 * cache its result if it failed.
	 *
	 * @param callable(): \Result $fetch
	 */
	private function remember(string $key, callable $fetch): \Result
	{
		$key = 'failed_' . $key;
		$error = $this->cache->get($key);
		if (\is_string($error)) {
			return \Result::failure($error);
		}
		$result = $fetch();
		if ($result->isFailure()) {
			$this->cache->set($key, (string) $result->getError(), self::TTL);
		}
		return $result;
	}
}
