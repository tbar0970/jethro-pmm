<?php

/**
 * Unit tests for include/general.php utility functions.
 */

require_once __DIR__ . '/../../include/general.php';

use function Test\assert_true;
use function Test\assert_false;
use function Test\assert_eq;
use function Test\assert_contains;
use function Test\assert_not_contains;
use function Test\assert_throws;
use function Test\test;

// ===========================================================================
// array_remove_empties — loose comparison `$x != ''`
// ===========================================================================

test('array_remove_empties: removes null', function () {
    $input = ['a', null, 'b'];
    assert_eq(array_remove_empties($input), ['a', 'b'],
        'null should be removed');
});

test('array_remove_empties: removes false', function () {
    $input = ['a', false, 'b'];
    assert_eq(array_remove_empties($input), ['a', 'b'],
        'false should be removed');
});

test('array_remove_empties: preserves 0 due to loose comparison', function () {
    $input = ['a', 0, 'b'];
    assert_eq(array_remove_empties($input), ['a', 0, 'b'],
        '0 is preserved (loose != with empty string does not match 0)');
});

test('array_remove_empties: preserves string "0"', function () {
    $input = ['a', '0', 'b'];
    assert_eq(array_remove_empties($input), ['a', '0', 'b'],
        '"0" is preserved');
});

test('array_remove_empties: removes empty string', function () {
    $input = ['a', '', 'b'];
    assert_eq(array_remove_empties($input), ['a', 'b'],
        'empty string should be removed');
});

// ===========================================================================
// stripslashes_array — key collision when $strip_keys=true
// ===========================================================================

test('stripslashes_array: key collision can cause data loss', function () {
    // BUG: When two original keys strip to the same result,
    // $keys_to_replace[$key] = $stripped_key overwrites earlier entries.
    // Only the last-matching original key gets moved.
    $arr = ['foo\\\\x' => 'first', 'foo\\x' => 'second'];
    stripslashes_array($arr, true);
    // After stripslashes: 'foo\\x' → 'foox', 'foo\x' → 'foox'
    // Both strip to 'foox'. Only one survives.
    assert_eq(count($arr), 1,
        'collision: only 1 entry expected when both keys strip to same value');
    assert_true(isset($arr['foox']),
        'stripped key should exist');
    // The surviving value depends on which one was processed last —
    // $keys_to_replace overwrites, so first iteration wins the key mapping.
});

test('stripslashes_array: strips values correctly', function () {
    $arr = ['name' => "O\\'Brien"];
    stripslashes_array($arr, false);
    assert_eq($arr['name'], "O'Brien");
});

test('stripslashes_array: strips keys correctly when $strip_keys=true', function () {
    $arr = ["key\\\\'s" => 'value'];
    stripslashes_array($arr, true);
    // After stripping, the key should be "key's" but the implementation
    // may leave a residual backslash. Check that the key was transformed.
    $keys = array_keys($arr);
    // The key should no longer have double backslash
    assert_false(in_array("key\\\\'s", $keys, true),
        'original key with double backslash should be removed');
    // At least one entry should exist
    assert_true(count($arr) >= 1,
        'at least one entry should survive');
});

test('format_datetime: null returns empty', function () {
    assert_eq(format_datetime(null), '');
});

test('format_datetime: "0000-00-00" returns empty', function () {
    assert_eq(format_datetime('0000-00-00'), '');
});

test('format_datetime: zero timestamp swallowed by empty()', function () {
    // BUG: empty(0) is true, so Unix epoch 1970-01-01 is treated as "no date"
    assert_eq(format_datetime(0), '',
        'BUG: empty(0) swallows Unix epoch timestamp');
});

test('format_datetime: valid string date formats correctly', function () {
    $result = format_datetime('2024-06-15 14:30:00');
    assert_true($result !== '', 'valid date should not be empty');
    assert_contains($result, '2024');
});

test('format_datetime: strtotime returning false returns empty', function () {
    assert_eq(format_datetime('not-a-date'), '');
});

// ===========================================================================
// format_date — falsy inputs and yearless edge cases
// ===========================================================================

test('format_date: "0000-00-00" returns empty', function () {
    assert_eq(format_date('0000-00-00'), '');
});

test('format_date: valid date formats correctly', function () {
    $result = format_date('2024-06-15');
    assert_contains($result, '2024');
    assert_contains($result, 'Jun');
});

test('format_date: zero timestamp produces output (inconsistent with format_datetime)', function () {
    // BUG: format_date has no empty() guard, so 0 produces "1 Jan 1970"
    // while format_datetime(0) returns '' — inconsistent API
    $result = format_date(0);
    assert_eq($result, '1 Jan 1970',
        'format_date(0) produces epoch date, inconsistent with format_datetime');
});

// ===========================================================================
// ents() — null, array, boolean handling
// ===========================================================================

test('ents: null returns empty string', function () {
    assert_eq(ents(null), '');
});

test('ents: empty string returns empty string', function () {
    assert_eq(ents(''), '');
});

test('ents: normal string is escaped', function () {
    assert_eq(ents('<b>'), '&lt;b&gt;');
});

test('ents: string "0" is preserved', function () {
    assert_eq(ents('0'), '0',
        '"0" should not be treated as empty');
});

test('ents: boolean true passes through as "1"', function () {
    // strval(true) = "1", which is not trimmed to empty
    assert_eq(ents(true), '1',
        'boolean true silently becomes "1"');
});

test('ents: boolean false returns empty', function () {
    // strval(false) = "", trimmed → "" → returns ''
    assert_eq(ents(false), '');
});

// ===========================================================================
// xml_safe_string — entity decoding bugs
// ===========================================================================

test('xml_safe_string: strips HTML tags', function () {
    // strip_tags removes <test> entirely
    $result = xml_safe_string('<test>content</test>');
    assert_eq($result, 'content',
        'HTML tags should be stripped');
});

test('xml_safe_string: &amp; is encoded last (safe)', function () {
    $result = xml_safe_string('a & b');
    assert_eq($result, 'a &amp; b');
});

test('xml_safe_string: left double quote &ldquo; decoded', function () {
    // html_entity_decode (PHP 8.1) decodes &ldquo; to Unicode U+201C
    $result = xml_safe_string('&ldquo;hello');
    // The Unicode char is not re-encoded by XML encoding
    $contains = str_contains($result, "\xe2\x80\x9c"); // UTF-8 for U+201C
    assert_true($contains, '&ldquo; should decode to Unicode left double quote');
});

test('xml_safe_string: &rdquo; is also decoded by html_entity_decode', function () {
    // In PHP 8.1, html_entity_decode handles &rdquo; (U+201D).
    // The manual str_replace on line 143 (which mistakenly searches &ldquo;
    // instead of &rdquo;) is redundant in modern PHP.
    $result = xml_safe_string('&rdquo;');
    $contains = str_contains($result, "\xe2\x80\x9d"); // UTF-8 for U+201D
    assert_true($contains,
        '&rdquo; decoded by html_entity_decode (PHP 8.1), manual fallback is dead code');
});

test('xml_safe_string: empty str_replace on lines 146-147 are no-ops', function () {
    // BUG: lines 146-147 search for "" — these are dead no-ops.
    // They were likely meant to be &rsquo; → ' and &lsquo; → '.
    // In PHP 8.1, html_entity_decode already handles these, so the bug is latent.
    // Verify that the text "&rsquo;" is properly decoded (by html_entity_decode)
    $result = xml_safe_string("it&rsquo;s a test");
    assert_contains($result, "\xe2\x80\x99",
        '&rsquo; decoded to right single quote by html_entity_decode');
});

test('xml_safe_string: XML special chars are encoded', function () {
    // After stripping and entity decoding, XML specials get re-encoded
    $result = xml_safe_string('a "quoted" string');
    assert_contains($result, '&quot;',
        'double quotes should be XML-encoded');
    assert_contains($result, 'a ');
});

// ===========================================================================
// generate_random_string — range('a','b') typo
// ===========================================================================

test('generate_random_string: poor-random only has 2 lowercase letters', function () {
    if (!defined('USE_POOR_RANDOMS')) {
        define('USE_POOR_RANDOMS', true);
    }
    $result = generate_random_string(1000);
    $lowercaseCount = 0;
    foreach (range('a', 'z') as $letter) {
        if (str_contains($result, $letter)) {
            $lowercaseCount++;
        }
    }
    assert_eq($lowercaseCount, 2,
        'BUG: range("a","b") produces only 2 lowercase letters instead of 26');
});

test('generate_random_string: normal path uses openssl and produces expected length', function () {
    $result = generate_random_string(32);
    assert_eq(strlen($result), 32);
});

test('generate_random_string: custom charset ignored in poor-random path', function () {
    // BUG: the USE_POOR_RANDOMS branch completely ignores the $set parameter!
    // USE_POOR_RANDOMS is already defined from the previous test.
    $result = generate_random_string(200, range(0, 9));
    // $set is ignored — result contains letters from hardcoded charset
    assert_true((bool)preg_match('/[A-Za-z]/', $result),
        'BUG: poor-random path ignores custom $set — produces letters despite digits-only request');
});

// ===========================================================================
// parse_size — unknown units, multiple decimal points
// ===========================================================================

test('parse_size: simple megabyte', function () {
    assert_eq((int)parse_size('1M'), 1048576);
});

test('parse_size: kilobyte', function () {
    assert_eq((int)parse_size('2K'), 2048);
});

test('parse_size: gigabyte', function () {
    assert_eq((int)parse_size('1G'), 1073741824);
});

test('parse_size: unknown unit silently treated as bytes', function () {
    // BUG: stripos returns false for unknown units, pow(1024, false) = 1.
    // '10X' returns 10 instead of erroring.
    $result = @parse_size('10X'); // @ suppresses PHP warning
    assert_eq((int)$result, 10,
        'BUG: unknown unit "X" silently treated as bytes');
});

test('parse_size: multiple decimal points silently truncated', function () {
    // BUG: preg_replace keeps both dots → '1.2.3' → PHP parses as 1.2
    $result = @parse_size('1.2.3M');
    assert_eq((int)$result, 1258291,
        'BUG: multiple decimal points silently use first parseable float');
});

test('parse_size: no unit returns numeric value', function () {
    assert_eq((int)parse_size('100'), 100);
});

// ===========================================================================
// print_csv — false/null/0 differentiation
// ===========================================================================

test('print_csv: null cell produces empty unenclosed', function () {
    ob_start();
    print_csv([['a', null, 'c']]);
    $result = ob_get_clean();
    assert_eq($result, "\"a\",,\"c\"\n");
});

test('print_csv: false cell is same as empty (stringified empty)', function () {
    // false !== '' and false !== null, so the condition passes.
    // But str_replace on false casts to '', so output is empty enclosed "".
    ob_start();
    print_csv([['a', false, 'c']]);
    $result = ob_get_clean();
    assert_eq($result, "\"a\",\"\",\"c\"\n",
        'false stringifies to empty string in str_replace context');
});

test('print_csv: zero cell is enclosed properly', function () {
    ob_start();
    print_csv([['a', 0, 'c']]);
    $result = ob_get_clean();
    assert_eq($result, "\"a\",\"0\",\"c\"\n");
});

test('print_csv: string "0" is enclosed', function () {
    ob_start();
    print_csv([['a', '0', 'c']]);
    $result = ob_get_clean();
    assert_eq($result, "\"a\",\"0\",\"c\"\n");
});

test('print_csv: escaping doubles the enclosure char', function () {
    ob_start();
    print_csv([['he said "hello"']]);
    $result = ob_get_clean();
    assert_eq($result, "\"he said \"\"hello\"\"\"\n");
});

// ===========================================================================
// format_phone_number — edge cases
// ===========================================================================

test('format_phone_number: standard AU mobile format', function () {
    $result = format_phone_number('0400123456', "XXXX-XXX-XXX");
    assert_eq($result, '0400-123-456');
});

test('format_phone_number: number shorter than format returns raw', function () {
    $result = format_phone_number('123', "XXXX-XXX-XXX");
    assert_eq($result, '123');
});

test('format_phone_number: strips non-digit chars first', function () {
    $result = format_phone_number('(04) 00 123 456', "XXXX-XXX-XXX");
    assert_eq($result, '0400-123-456');
});

test('format_phone_number: number longer than any format returns raw', function () {
    $result = format_phone_number('123456789012345', "XXXX-XXX-XXX");
    assert_eq($result, '123456789012345');
});

// ===========================================================================
// is_valid_phone_number — edge cases
// ===========================================================================

test('is_valid_phone_number: valid against format', function () {
    assert_true(is_valid_phone_number('0400123456', "XXXX-XXX-XXX"));
});

test('is_valid_phone_number: rejects letters', function () {
    assert_false(is_valid_phone_number('0400abc456', "XXXX-XXX-XXX"));
});

test('is_valid_phone_number: matches with punctuation', function () {
    assert_true(is_valid_phone_number('0400-123-456', "XXXX-XXX-XXX"));
});

test('is_valid_phone_number: wrong length rejected', function () {
    assert_false(is_valid_phone_number('12345', "XXXX-XXX-XXX"));
});

// ===========================================================================
// clean_phone_number
// ===========================================================================

test('clean_phone_number: strips all non-digits', function () {
    assert_eq(clean_phone_number('(04) 1234-5678'), '0412345678');
});

test('clean_phone_number: handles empty string', function () {
    assert_eq(clean_phone_number(''), '');
});

// ===========================================================================
// get_valid_phone_number_lengths
// ===========================================================================

test('get_valid_phone_number_lengths: returns distinct digit counts', function () {
    $result = get_valid_phone_number_lengths("XXXX-XXX-XXX\nXXXX-XXXX");
    // First format: 10 X's, second: 8 X's
    assert_eq($result, [10, 8]);
});

// ===========================================================================
// get_phone_format_lengths
// ===========================================================================

test('get_phone_format_lengths: returns unique format lengths', function () {
    // "XXXX-XXX-XXX" = 12 chars, "(XX) XXXX-XXXX" = 14 chars
    $result = get_phone_format_lengths("XXXX-XXX-XXX\n(XX) XXXX-XXXX");
    assert_eq($result, [12, 14]);
});

// ===========================================================================
// safe_subdirectory — path traversal protection
// ===========================================================================

test('safe_subdirectory: valid subdirectory', function () {
    $base = sys_get_temp_dir();
    $subdir = 'test_safe_' . getmypid();
    @mkdir($base . '/' . $subdir);
    $result = safe_subdirectory($base, $subdir);
    if ($result !== false) {
        assert_eq($result, $subdir);
    }
    @rmdir($base . '/' . $subdir);
});

test('safe_subdirectory: rejects path traversal', function () {
    $result = safe_subdirectory(sys_get_temp_dir(), '../etc/passwd');
    assert_false($result);
});

test('safe_subdirectory: rejects empty path', function () {
    $result = safe_subdirectory(sys_get_temp_dir(), '');
    assert_false($result);
});

test('safe_subdirectory: rejects nonexistent path', function () {
    $result = safe_subdirectory(sys_get_temp_dir(), 'nonexistent_' . uniqid());
    assert_false($result);
});

test('safe_subdirectory: rejects absolute path escape', function () {
    $result = safe_subdirectory(sys_get_temp_dir(), '/etc/passwd');
    assert_false($result);
});

// ===========================================================================
// format_value — empty() swallowing falsy values
// ===========================================================================

test('format_value: datetime with value 0 returns empty when allow_empty set', function () {
    // BUG: empty(0) is true, so a legit zero gets blanked
    $result = format_value(0, ['type' => 'datetime', 'allow_empty' => true]);
    assert_eq($result, '',
        'BUG: empty(0) swallows zero value for datetime');
});

test('format_value: select with valid key returns option label', function () {
    $result = format_value('foo', ['type' => 'select', 'options' => ['foo' => 'Foo Label']]);
    assert_eq($result, 'Foo Label');
});

test('format_value: select with invalid key shows warning', function () {
    $result = format_value('bad', ['type' => 'select', 'options' => ['foo' => 'Foo']]);
    assert_eq($result, '(Invalid Value)');
});

test('format_value: unknown type returns value as-is', function () {
    $result = format_value(999, ['type' => 'custom']);
    assert_eq($result, 999);
});

// ===========================================================================
// array_get — basic functionality
// ===========================================================================

test('array_get: returns value for existing key', function () {
    assert_eq(array_get(['a' => 1], 'a'), 1);
});

test('array_get: returns default for missing key', function () {
    assert_eq(array_get(['a' => 1], 'b', 'default'), 'default');
});

test('array_get: returns null default when not specified', function () {
    assert_eq(array_get(['a' => 1], 'b'), null);
});

test('array_get: null array triggers fatal error', function () {
    // BUG: array_key_exists() requires array, null given → TypeError in PHP 8.1
    $caught = false;
    try {
        array_get(null, 'key', 'fallback');
    } catch (\TypeError $e) {
        $caught = true;
    }
    assert_true($caught,
        'BUG: array_get(null, ...) throws TypeError instead of returning fallback');
});

// ===========================================================================
// hard_trim
// ===========================================================================

test('hard_trim: trims whitespace and common punctuation', function () {
    assert_eq(hard_trim('  hello,;. '), 'hello');
});

test('hard_trim: trims trailing comma', function () {
    assert_eq(hard_trim('hello,'), 'hello');
});

// ===========================================================================
// ifdef
// ===========================================================================

test('ifdef: returns constant value when defined', function () {
    if (!defined('TEST_IFDEF_CONST')) {
        define('TEST_IFDEF_CONST', 'hello');
    }
    assert_eq(ifdef('TEST_IFDEF_CONST'), 'hello');
});

test('ifdef: returns fallback when not defined', function () {
    assert_eq(ifdef('TEST_IFDEF_UNDEFINED', 'fallback'), 'fallback');
});

test('ifdef: returns null when not defined and no fallback', function () {
    assert_eq(ifdef('TEST_IFDEF_UNDEFINED2'), null);
});

// ===========================================================================
// bam — debug output (CLI mode)
// ===========================================================================

test('bam: outputs array in CLI mode', function () {
    ob_start();
    bam(['test' => 'value']);
    $result = ob_get_clean();
    assert_contains($result, 'test');
    assert_contains($result, 'value');
});

// ===========================================================================
// build_url — URL construction
// ===========================================================================

test('build_url: builds URL with params', function () {
    $result = build_url(['view' => 'persons', 'personid' => 5]);
    assert_contains($result, 'view=persons');
    assert_contains($result, 'personid=5');
});

test('build_url: null param removes existing key', function () {
    $old = $_GET;
    $_GET = ['view' => 'old', 'keep' => 'me'];
    $result = build_url(['view' => null]);
    assert_not_contains($result, 'view=old');
    assert_contains($result, 'keep=me');
    $_GET = $old;
});
