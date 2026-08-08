<?php

/**
 * Person tokens per recipient (Jethro\Sms\recipientTokens()), including the
 * %selected.*% tokens naming who the recipient is sent the message for, and
 * how family-mode recipients learn their selected persons
 * (getRecipientsFromPersonRecords()).
 */

namespace Test\SmsRecipientTokens;

use function Test\{test, assert_eq};
use function Jethro\Sms\{recipientTokens, getRecipientsFromPersonRecords, getAvailableTokens};
use Jethro\Sms\JethroSmsRecipient;

require_once __DIR__ . '/../../include/general.php';
require_once __DIR__ . '/../../include/jethro_sms.php';

const PEOPLE = [
	1 => ['first_name' => 'Carol', 'last_name' => 'Smith'],
	2 => ['first_name' => 'Ann', 'last_name' => 'Smith'],
	3 => ['first_name' => 'Ben', 'last_name' => 'Jones'],
];

function tokensFor(JethroSmsRecipient $r): array
{
	return recipientTokens($r, fn(int $id): array => PEOPLE[$id] ?? []);
}

function recipient(int $personId, array $selectedPersonIds = []): JethroSmsRecipient
{
	return new JethroSmsRecipient($personId, new \Sms\PhoneNumber('0491570156'), $selectedPersonIds);
}

test('every available token gets a value', function () {
	assert_eq(array_keys(tokensFor(recipient(1, [2]))), getAvailableTokens());
});

test('selected.* name the selected persons; the plain tokens the recipient', function () {
	$t = tokensFor(recipient(1, [2]));
	assert_eq([$t['firstname'], $t['selected.firstname'], $t['selected.fullname']], ['Carol', 'Ann', 'Ann Smith']);
});

test('several selected persons are joined " & "', function () {
	$t = tokensFor(recipient(1, [2, 3]));
	assert_eq($t['selected.firstname'], 'Ann & Ben');
	assert_eq($t['selected.lastname'], 'Smith & Jones');
});

test('a directly selected recipient is their own selected person', function () {
	$t = tokensFor(recipient(3));
	assert_eq([$t['selected.firstname'], $t['selected.fullname']], ['Ben', 'Ben Jones']);
});

test('unknown persons contribute nothing to the join', function () {
	assert_eq(tokensFor(recipient(1, [99, 2]))['selected.firstname'], 'Ann');
});

test('family mode: each recipient carries its own family\'s selected persons', function () {
	$recipients = getRecipientsFromPersonRecords(
		[
			10 => ['mobile_tel' => '0491570156', 'familyid' => 5],
			11 => ['mobile_tel' => '0491570157', 'familyid' => 6],
			12 => ['mobile_tel' => '0491570158', 'familyid' => 7],
		],
		[5 => [2, 3], 6 => [4]],
	);
	assert_eq(array_map(fn(JethroSmsRecipient $r): array => $r->selectedPersonIds, $recipients), [[2, 3], [4], []]);
});
