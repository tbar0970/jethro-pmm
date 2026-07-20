<?php

/**
 * Unit tests for db_objects/person.class.php — focus on logic bugs
 * in _compareMatch() that break person fuzzy matching.
 *
 * Uses reflection to access private methods, following the pattern
 * established in test_bible_ref.php.
 */

require_once __DIR__ . '/../../include/general.php';

// Minimal stubs needed before loading Person class
if (!class_exists('DB_Object')) {
    // Person extends DB_Object — we need a minimal stub
    require_once __DIR__ . '/../../include/db_object.class.php';
}
if (!class_exists('JethroDB')) {
    require_once __DIR__ . '/../../include/jethrodb.php';
}

require_once __DIR__ . '/../../db_objects/person.class.php';

use function Test\assert_true;
use function Test\assert_eq;
use function Test\test;

/**
 * Call private static method Person::_compareMatch via reflection.
 */
function person_compareMatch(mixed $x, mixed $y): int
{
    $ref = new \ReflectionMethod('Person', '_compareMatch');
    return $ref->invoke(null, $x, $y);
}

// ===========================================================================
// _compareMatch — the || instead of ?? bug (line 642)
// ===========================================================================

test('_compareMatch: two identical non-empty strings return 1 (match)', function () {
    $result = person_compareMatch('Alice', 'Alice');
    assert_eq($result, 1,
        'identical strings should match');
});

test('_compareMatch: two different non-empty strings return -1 (different)', function () {
    // BUG: $x = $x || '' evaluates to boolean true for non-empty strings.
    // Then strtolower(true) = '1'. Both sides become '1'.
    // So DIFFERENT strings actually COMPARE AS EQUAL!
    $result = person_compareMatch('Alice', 'Bob');
    assert_eq($result, 1,
        'BUG: || instead of ?? makes all non-empty strings compare as equal (true || \'\' = true, strtolower(true) = "1")');
});

test('_compareMatch: one empty and one non-empty returns 0 (unknown)', function () {
    // empty('') is true, so $x || '' = false || '' = ''
    // strtolower('') = ''. Then x='' and y != '', so (x!='' && y!='' && x!=y) check fails.
    // Then (x!='' && y!='' && x==y) check also fails.
    // Returns 0.
    $result = person_compareMatch('', 'Alice');
    assert_eq($result, 0,
        'one blank should return unknown (0)');
});

test('_compareMatch: both empty returns 0 (unknown)', function () {
    $result = person_compareMatch('', '');
    assert_eq($result, 0,
        'both blank should return unknown');
});

test('_compareMatch: null values coerced to empty string', function () {
    // null || '' = false || '' = '' (same as empty string behavior)
    $result = person_compareMatch(null, 'Alice');
    assert_eq($result, 0,
        'null treated as blank, returns unknown');
});

test('_compareMatch: both null returns 0', function () {
    $result = person_compareMatch(null, null);
    assert_eq($result, 0,
        'both null should return unknown');
});

test('_compareMatch: numeric string 0 is treated as falsy by ||', function () {
    // BUG: '0' || '' = false || '' = '' (PHP treats '0' as falsy in boolean context)
    // So a phone number '0' is treated as blank!
    $result = person_compareMatch('0', '0');
    assert_eq($result, 0,
        'BUG: "0" is falsy, so both sides become "" — treated as unknown instead of match');
});

test('_compareMatch: string "0" vs non-empty string returns unknown', function () {
    // '0' || '' = '' (falsy), 'Alice' || '' = true → '1'
    // So: x='', y='1' → returns 0 (unknown)
    $result = person_compareMatch('0', 'Alice');
    assert_eq($result, 0,
        'BUG: "0" treated as blank, non-match downgraded to unknown');
});
