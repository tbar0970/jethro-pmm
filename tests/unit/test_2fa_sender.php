<?php

/**
 * 2FA sender resolution: which sender a 2FA code is sent as, and why the login
 * is refused when there is none (User_System::resolve2FASender()).
 *
 * Regression: 2FA used to send as the 2FA_SENDER_ID default 'Jethro', an
 * alphanumeric sender ID the ACMA has shown as 'Unverified' since 1 July 2026.
 * With the default now blank, the sender must come from a literal SMS_SENDER or
 * the provider's first ACMA-approved sender ID — and if none exists, the login
 * must be refused with a reason rather than sending an unusable code.
 */

namespace Test\TwoFactorSender;

use Sms\PhoneNumber;
use Sms\SenderID;
use function Test\{test, assert_eq, assert_true, assert_contains};

require_once __DIR__ . '/../../include/general.php';
require_once __DIR__ . '/../../include/jethro_sms.php';
require_once __DIR__ . '/../../include/user_system.class.php';

test('2FA_SENDER_ID wins over everything', function () {
	list($sender, $problem) = \User_System::resolve2FASender('StChurch', '000000000', [
		new SenderID('Approved', acmaApproved: true),
	]);
	assert_eq((string) $sender, 'StChurch');
	assert_eq($problem, null);
});

test('a numeric 2FA_SENDER_ID is a phone number', function () {
	list($sender, $problem) = \User_System::resolve2FASender('+61412345678', '', []);
	assert_true($sender instanceof PhoneNumber);
	assert_eq($sender->value, '61412345678');
	assert_eq($problem, null);
});

test('a literal SMS_SENDER is used when 2FA_SENDER_ID is blank', function () {
	list($sender, $problem) = \User_System::resolve2FASender('', 'MyChurch', [
		new SenderID('Approved', acmaApproved: true),
	]);
	assert_eq((string) $sender, 'MyChurch');
	assert_eq($problem, null);
});

test('SMS_SENDER=_USER_MOBILE_ cannot be resolved before login', function () {
	list($sender, $problem) = \User_System::resolve2FASender('', '_USER_MOBILE_', [
		new SenderID('Pending', acmaApproved: false),
		new SenderID('Approved', acmaApproved: true),
	]);
	assert_eq((string) $sender, 'Approved');
	assert_eq($problem, null);
});

test('only ACMA-approved provider sender IDs qualify', function () {
	list($sender, $problem) = \User_System::resolve2FASender('', '', [
		new SenderID('Unknown', acmaApproved: null),
		new SenderID('Rejected', acmaApproved: false),
		new SenderID('Registered', acmaApproved: true),
		new SenderID('AlsoRegistered', acmaApproved: true),
	]);
	assert_eq((string) $sender, 'Registered');
	assert_eq($problem, null);
});

test('no usable sender refuses the login with a reason', function () {
	list($sender, $problem) = \User_System::resolve2FASender('', '', [
		new SenderID('Unknown', acmaApproved: null),
	]);
	assert_eq($sender, null);
	assert_contains($problem, 'ACMA-registered');
});

test('an unreadable provider refuses the login with a reason', function () {
	list($sender, $problem) = \User_System::resolve2FASender('', '', null);
	assert_eq($sender, null);
	assert_contains($problem, 'could be read');
});
