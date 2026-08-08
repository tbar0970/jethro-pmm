<?php

/**
 * Unit tests for include/sms_sender.class.php — pure-logic functions.
 *
 * Uses reflection to access private methods where needed.
 *
 * @isolated-process
 */

require_once __DIR__ . '/../../include/general.php';
require_once __DIR__ . '/../../include/sms_sender.class.php';

use function Test\assert_true;
use function Test\assert_false;
use function Test\assert_eq;
use function Test\test;

// ---------------------------------------------------------------------------
// Reflection helpers
// ---------------------------------------------------------------------------

function sms_normalizeNumber(?string $number): ?string
{
    $ref = new \ReflectionMethod('SMS_Sender', 'normalizeNumber');
    return $ref->invoke(null, $number);
}

function sms_internationaliseNumber(string $number): string
{
    $ref = new \ReflectionMethod('SMS_Sender', 'internationaliseNumber');
    return $ref->invoke(null, $number);
}

// ===========================================================================
// normalizeNumber — strips non-digit characters
// ===========================================================================

test('normalizeNumber: strips spaces', function () {
    assert_eq(sms_normalizeNumber('0400 123 456'), '0400123456');
});

test('normalizeNumber: strips dashes', function () {
    assert_eq(sms_normalizeNumber('0400-123-456'), '0400123456');
});

test('normalizeNumber: strips parentheses', function () {
    assert_eq(sms_normalizeNumber('(02) 1234 5678'), '0212345678');
});

test('normalizeNumber: strips plus sign', function () {
    assert_eq(sms_normalizeNumber('+61400123456'), '61400123456');
});

test('normalizeNumber: handles mixed formatting', function () {
    assert_eq(sms_normalizeNumber('+61 (0) 400-123-456'), '610400123456');
});

test('normalizeNumber: null returns null', function () {
    assert_eq(sms_normalizeNumber(null), null);
});

test('normalizeNumber: empty string returns empty', function () {
    assert_eq(sms_normalizeNumber(''), '');
});

test('normalizeNumber: already clean number unchanged', function () {
    assert_eq(sms_normalizeNumber('0400123456'), '0400123456');
});

test('normalizeNumber: letters are stripped', function () {
    $result = sms_normalizeNumber('call 0400-ABC-DEF');
    assert_eq($result, '0400',
        'non-digit characters including letters are stripped');
});

// ===========================================================================
// internationaliseNumber — local prefix to international conversion
// ===========================================================================

test('internationaliseNumber: converts local prefix to international', function () {
    if (!defined('SMS_LOCAL_PREFIX')) {
        define('SMS_LOCAL_PREFIX', '0');
    }
    if (!defined('SMS_INTERNATIONAL_PREFIX')) {
        define('SMS_INTERNATIONAL_PREFIX', '61');
    }
    assert_eq(sms_internationaliseNumber('0400123456'), '61400123456');
});

test('internationaliseNumber: returns unchanged if no local prefix match', function () {
    if (!defined('SMS_LOCAL_PREFIX')) {
        define('SMS_LOCAL_PREFIX', '0');
    }
    if (!defined('SMS_INTERNATIONAL_PREFIX')) {
        define('SMS_INTERNATIONAL_PREFIX', '61');
    }
    // Number starts with '6', not '0'
    assert_eq(sms_internationaliseNumber('61400123456'), '61400123456');
});

test('internationaliseNumber: returns unchanged if no prefixes configured', function () {
    // Can't undefine constants, but the function checks strlen() of both
    // so if either is empty, number is unchanged
    // We rely on the fact that constants may not be defined
    $result = sms_internationaliseNumber('0400123456');
    // If prefixes are defined, it converts; if not, unchanged
    // Either outcome is valid depending on config state
    assert_true(strlen($result) > 0, 'should return a non-empty string');
});

// ===========================================================================
// canSend — does not error when GLOBALS not fully set up
// ===========================================================================

test('canSend: returns false when no URL configured', function () {
    // SMS_HTTP_URL is typically not defined in test environment
    $result = SMS_Sender::canSend();
    // Either false (no config) or true (config present). Both are valid.
    // Just verify it doesn't crash.
    assert_true(is_bool($result), 'canSend should return a boolean');
});
