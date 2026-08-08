<?php

/**
 * The two times a send is recorded with:
 *   - effectiveScheduledSendAt(): the time a deferred send actually goes out,
 *     which a provider may push later than requested (MobileMessage clamps to
 *     now+90 s because its API downgrades anything ≤60 s ahead to immediate).
 *   - DbLoggingSmsProvider::observedDeliveredAt(): when to record delivery,
 *     falling back to the status check's own time when the provider reports
 *     no carrier timestamp (MobileMessage).
 */

namespace Test\SmsScheduledAndDeliveredTimes;

use function Test\{test, assert_eq};
use function Jethro\Sms\effectiveScheduledSendAt;
use Jethro\Sms\Providers\DbLoggingSmsProvider;
use \Sms\{SmsDelivery, SmsStatus, PhoneNumber};

require_once __DIR__ . '/../../include/general.php';
require_once __DIR__ . '/../../include/jethro_sms.php';
require_once __DIR__ . '/../../include/Jethro/Sms/Providers/DbLoggingSmsProvider.php';

function delivery(SmsStatus $status, ?int $sendTimestamp = null, ?int $deliveryTimestamp = null): SmsDelivery
{
	return new SmsDelivery(
		recipient: new PhoneNumber('61491570156'),
		status: $status,
		sendTimestamp: $sendTimestamp,
		deliveryTimestamp: $deliveryTimestamp,
	);
}

test('effectiveScheduledSendAt: immediate send stays null', function () {
	assert_eq(effectiveScheduledSendAt(null, [delivery(SmsStatus::SENT, 1000)]), null);
});

test('effectiveScheduledSendAt: the provider pushing the send later wins', function () {
	// Requested 30 s out; MobileMessage reported 90 s out.
	assert_eq(effectiveScheduledSendAt(1030, [delivery(SmsStatus::SCHEDULED, 1090)]), 1090);
});

test('effectiveScheduledSendAt: providers that report no time keep the requested time', function () {
	assert_eq(effectiveScheduledSendAt(1030, [delivery(SmsStatus::SCHEDULED)]), 1030);
	assert_eq(effectiveScheduledSendAt(1030, []), 1030);
});

test('effectiveScheduledSendAt: the first reported time is used', function () {
	$deliveries = [delivery(SmsStatus::SCHEDULED), delivery(SmsStatus::SCHEDULED, 1090), delivery(SmsStatus::SCHEDULED, 1090)];
	assert_eq(effectiveScheduledSendAt(1030, $deliveries), 1090);
});

test('observedDeliveredAt: the carrier timestamp when the provider gives one', function () {
	$observed = DbLoggingSmsProvider::observedDeliveredAt(delivery(SmsStatus::DELIVERED, deliveryTimestamp: 1700000000), 1700005000);
	assert_eq($observed, date('Y-m-d H:i:s', 1700000000));
});

test('observedDeliveredAt: the status check time when the provider gives none', function () {
	// MobileMessage reports DELIVERED with no carrier timestamp.
	$observed = DbLoggingSmsProvider::observedDeliveredAt(delivery(SmsStatus::DELIVERED), 1700005000);
	assert_eq($observed, date('Y-m-d H:i:s', 1700005000));
});

test('observedDeliveredAt: nothing recorded before delivery', function () {
	assert_eq(DbLoggingSmsProvider::observedDeliveredAt(delivery(SmsStatus::SENT), 1700005000), null);
	assert_eq(DbLoggingSmsProvider::observedDeliveredAt(delivery(SmsStatus::SCHEDULED), 1700005000), null);
});
