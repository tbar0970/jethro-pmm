<?php

/**
 * Jethro\Sms\leftOutNotice(): what the statusline says, before Send, about
 * recipients a send would leave out (no mobile number, archived).
 */

namespace Test\SmsLeftOutNotice;

use function Test\{test, assert_eq};
use function Jethro\Sms\leftOutNotice;

require_once __DIR__ . '/../../include/general.php';
require_once __DIR__ . '/../../include/jethro_sms.php';

test('nobody left out: no notice', function () {
	assert_eq(leftOutNotice(3, 0, 0), null);
});

test('some left out: says how many and why, without blocking', function () {
	assert_eq(leftOutNotice(1, 1, 0), [
		'text' => '1 of the 2 recipients will not be sent this: 1 has no mobile number.',
		'blocking' => false,
	]);
	assert_eq(leftOutNotice(3, 2, 1)['text'],
		'3 of the 6 recipients will not be sent this: 2 have no mobile number and 1 is archived.');
});

test('nobody left to send to: blocks, with the reason', function () {
	assert_eq(leftOutNotice(0, 5, 0), [
		'text' => 'No SMS can be sent: none of the 5 recipients has a mobile number.',
		'blocking' => true,
	]);
	assert_eq(leftOutNotice(0, 0, 2)['text'], 'No SMS can be sent: all 2 recipients are archived.');
	assert_eq(leftOutNotice(0, 4, 1)['text'],
		'No SMS can be sent: of the 5 recipients, 4 have no mobile number and 1 is archived.');
});

test('a single recipient is described as "the recipient"', function () {
	assert_eq(leftOutNotice(0, 1, 0)['text'], 'No SMS can be sent: the recipient has no mobile number.');
	assert_eq(leftOutNotice(0, 0, 1)['text'], 'No SMS can be sent: the recipient is archived.');
});
