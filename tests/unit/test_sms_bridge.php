<?php

/**
 * Unit tests for the Jethro SMS bridge (include/jethro_sms.php,
 * include/Jethro/Sms/) that need no database or gateway.
 *
 * Regressions:
 *  - loadSmsBatch() built each recipient from the person's current mobile and
 *    threw on a blank one (and an inner JOIN dropped raw-number deliveries),
 *    so a scheduled SMS couldn't be cancelled once any recipient's mobile was
 *    cleared.
 *  - Provider lookups made on ordinary page loads (balance, sender IDs,
 *    opt-outs) were cached only on success, so an unreachable gateway cost
 *    one HTTP timeout per call on every page.
 */

namespace Test\SmsBridge;

use function Test\{test, assert_eq, assert_true};

require_once __DIR__ . '/../../include/general.php';
require_once __DIR__ . '/../../include/jethro_sms.php';

/** Stands in for JethroDB: canned results for loadSmsBatch()'s two queries. */
final class FakeDb
{
	public function __construct(private ?array $smsRow, private array $deliveryRows) {}

	public function queryRow(string $sql): ?array
	{
		return $this->smsRow;
	}

	public function queryAll(string $sql): array
	{
		return $this->deliveryRows;
	}
}

/** HttpClient that fails every request, counting them. */
final class UnreachableHttpClient implements \Sms\HttpClient
{
	public int $requests = 0;

	public function send(\Sms\HttpRequest $request): \Result
	{
		$this->requests++;
		return \Result::failure('Connection timed out');
	}
}

function withDb(FakeDb $db, callable $fn): mixed
{
	$saved = $GLOBALS['db'] ?? null;
	$GLOBALS['db'] = $db;
	try {
		return $fn();
	} finally {
		$GLOBALS['db'] = $saved;
	}
}

test('loadSmsBatch: blank, digitless and NULL-person recipients still load', function () {
	$db = new FakeDb(['sender' => '7'], [
		['id' => '1', 'personid' => '10', 'remote_id' => 'r1', 'status' => 'scheduled', 'body' => 'Hi', 'mobile_tel' => '0491570156'],
		['id' => '2', 'personid' => '11', 'remote_id' => 'r2', 'status' => 'scheduled', 'body' => 'Hi', 'mobile_tel' => ''],
		['id' => '3', 'personid' => '12', 'remote_id' => 'r3', 'status' => 'scheduled', 'body' => 'Hi', 'mobile_tel' => '-'],
		['id' => '4', 'personid' => null, 'remote_id' => 'r4', 'status' => 'scheduled', 'body' => 'Hi', 'mobile_tel' => null],
	]);
	$result = withDb($db, fn() => \Jethro\Sms\loadSmsBatch(5));

	assert_true($result->isSuccess());
	$batch = $result->getValue();
	assert_eq($batch->senderPersonId, 7);
	assert_eq(count($batch->deliveries), 4, 'no delivery dropped');
	assert_eq(array_map(fn($d) => $d->remoteId(), $batch->deliveries), ['r1', 'r2', 'r3', 'r4']);
	assert_eq($batch->deliveries[0]->recipient()->value, '0491570156');
	assert_eq($batch->deliveries[1]->recipientPersonId(), 11);
	assert_eq($batch->deliveries[3]->recipientPersonId(), null, 'raw-number delivery has no person');
});

test('loadSmsBatch: unknown sms id is a failure', function () {
	$result = withDb(new FakeDb(null, []), fn() => \Jethro\Sms\loadSmsBatch(99));
	assert_true($result->isFailure());
});

test('FailureCachingSmsProvider: a failed lookup is not retried upstream while cached', function () {
	$savedSession = $_SESSION ?? [];
	$_SESSION = [];
	$http = new UnreachableHttpClient();
	$raw = new \Sms\Providers\FiveCentSmsV5Provider(url: 'https://api.test.example/v5', keyId: 'k', keySecret: 's', httpClient: $http);
	$cache = new \Jethro\Sms\SessionSmsCache('sms_cache_', 'fp');
	$provider = new \Jethro\Sms\Providers\FailureCachingSmsProvider($raw, $cache);

	$first = $provider->getBalance();
	$requests = $http->requests;
	$second = $provider->getBalance();

	assert_true($first->isFailure());
	assert_true($second->isFailure());
	assert_eq($second->getError(), $first->getError(), 'the cached failure carries the original error');
	assert_eq($http->requests, $requests, 'no upstream call while the failure is cached');

	// A different config fingerprint (settings changed) retries upstream.
	$fresh = new \Jethro\Sms\Providers\FailureCachingSmsProvider($raw, new \Jethro\Sms\SessionSmsCache('sms_cache_', 'fp2'));
	$fresh->getBalance();
	assert_true($http->requests > $requests, 'new settings retry upstream');
	$_SESSION = $savedSession;
});
