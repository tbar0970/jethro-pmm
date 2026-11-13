<?php

/**
 * Jethro\Sms\presentSendResult(): what a sender sees after Send — the alerts,
 * which list rows are marked, and whether the send modal stays open.
 */

namespace Test\SmsSendResult;

use function Test\{test, assert_eq, assert_true, assert_contains, assert_not_contains};
use function Jethro\Sms\presentSendResult;

require_once __DIR__ . '/../../include/general.php';
require_once __DIR__ . '/../../include/jethro_sms.php';

function people(array $names): array
{
	$out = [];
	foreach ($names as $id => $name) {
		[$first, $last] = explode(' ', $name, 2);
		$out[$id] = ['first_name' => $first, 'last_name' => $last];
	}
	return $out;
}

test('one person sent to: named, row marked, modal may close', function () {
	$shown = presentSendResult(['sent' => ['count' => 1, 'recipients' => people([7 => 'Ann Lee'])]], false);
	assert_eq($shown['error'], null);
	assert_contains($shown['html'], 'Message successfully sent to Ann Lee');
	assert_eq($shown['rowClasses'], [7 => ['sms-success']]);
	assert_eq($shown['keepOpen'], false);
});

test('several sent to (scheduled): counted, then listed; modal stays open', function () {
	$shown = presentSendResult(['sent' => [
		'count' => 2, 'recipients' => people([1 => 'Ann Lee', 2 => 'Bob <b>Ray</b>']), 'scheduled' => true,
	]], true);
	assert_contains($shown['html'], 'Message scheduled for sending to 2 recipients');
	assert_contains($shown['html'], '<p>Ann Lee, Bob &lt;b&gt;Ray&lt;/b&gt;</p>');
	assert_contains($shown['html'], '(SMS History Log)');
	assert_eq($shown['keepOpen'], true);
});

test('anyone left out keeps the modal open and marks their row as a failure', function () {
	$shown = presentSendResult([
		'sent' => ['count' => 1, 'recipients' => people([1 => 'Ann Lee'])],
		'failed_opted_out' => ['count' => 1, 'recipients' => people([2 => 'Bob Ray'])],
		'note_saved' => true,
	], false);
	assert_contains($shown['html'], 'Bob Ray has opted out of receiving SMS');
	assert_eq($shown['rowClasses'], [1 => ['sms-success', 'note-success'], 2 => ['sms-failure']]);
	assert_eq($shown['keepOpen'], true);
	assert_not_contains($shown['html'], 'SMS History Log');
});

test('provider failures are an error alert, counted when several', function () {
	$shown = presentSendResult(['failed' => ['count' => 2, 'recipients' => people([3 => 'Cy Dee', 4 => 'Di Eve'])]], false);
	assert_contains($shown['html'], '<div class="alert alert-error">SMS sending failed for 2 recipients<p>Cy Dee, Di Eve</p></div>');
	assert_eq($shown['keepOpen'], true);
});

test('an error replaces the alerts and keeps the modal open', function () {
	$shown = presentSendResult(['error' => 'Empty message'], true);
	assert_eq($shown['error'], 'Empty message');
	assert_eq($shown['html'], '');
	assert_eq($shown['rowClasses'], []);
	assert_true($shown['keepOpen']);
});
