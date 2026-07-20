<?php

/**
 * Unit tests for Bible_Ref — Bible reference parser and formatter.
 *
 * Tests the constructor (both named-ref and numeric-code paths),
 * toString(), toShortString(), and toCode() roundtrip integrity.
 */

require_once __DIR__ . '/../../include/bible_ref.class.php';

use function Test\assert_true;
use function Test\assert_false;
use function Test\assert_eq;
use function Test\assert_contains;
use function Test\assert_not_contains;
use function Test\assert_throws;
use function Test\test;

// ---------------------------------------------------------------------------
// Reflection helper — access private fields for precise assertions
// ---------------------------------------------------------------------------

function bible_ref_get(Bible_Ref $ref, string $prop): mixed
{
    $rp = new ReflectionProperty(Bible_Ref::class, $prop);
    return $rp->getValue($ref);
}

/** Construct and assert all internal fields in one call.
 *
 * Chapter/verse params accept int or string because the named-ref
 * constructor path stores regex captures as strings while the
 * numeric-code path (sscanf %d) stores ints — an inconsistency in
 * the class under test.
 */
function assert_parsed(
    string $input,
    ?int $book,
    int|string $start_ch, int|string $start_v,
    int|string $end_ch,   int|string $end_v,
    int|string $start_v2 = 0, int|string $end_v2 = 0,
    string $msg = ''
): Bible_Ref {
    $ref = new Bible_Ref($input);
    assert_eq(bible_ref_get($ref, 'book'),     $book,               "$msg — book");
    assert_eq((string) bible_ref_get($ref, 'start_ch'), (string) $start_ch, "$msg — start_ch");
    assert_eq((string) bible_ref_get($ref, 'start_v'),  (string) $start_v,  "$msg — start_v");
    assert_eq((string) bible_ref_get($ref, 'end_ch'),   (string) $end_ch,   "$msg — end_ch");
    assert_eq((string) bible_ref_get($ref, 'end_v'),    (string) $end_v,    "$msg — end_v");
    assert_eq((string) bible_ref_get($ref, 'start_v2'), (string) $start_v2, "$msg — start_v2");
    assert_eq((string) bible_ref_get($ref, 'end_v2'),   (string) $end_v2,   "$msg — end_v2");
    return $ref;
}

// ===========================================================================
// Constructor — empty / null / invalid
// ===========================================================================

test('Constructor: empty string produces null book', function () {
    $ref = new Bible_Ref('');
    assert_eq(bible_ref_get($ref, 'book'), null);
    assert_eq(bible_ref_get($ref, 'start_ch'), 0);
});

test('Constructor: null string produces null book', function () {
    // empty() returns true for null, so early return
    $ref = new Bible_Ref(null);
    assert_eq(bible_ref_get($ref, 'book'), null);
});

test('Constructor: unknown book triggers warning and null book', function () {
    $old = error_reporting(E_ALL & ~E_USER_NOTICE);
    $ref = new Bible_Ref('NonExistentBook 3:16');
    error_reporting($old);
    assert_eq(bible_ref_get($ref, 'book'), null);
});

test('Constructor: digit-only input hits one-number branch', function () {
    // "123" — no letters for the book-name group to match, so:
    // ([0-9]?([^0-9]+)) fails to match (group 2 needs at least one non-digit)
    // The regex does not match at all, so $matches is empty.
    // The constructor accesses $matches[1] on line 346 without checking
    // preg_match success — a real bug that triggers an E_WARNING.
    $old = error_reporting(E_ALL & ~(E_USER_NOTICE | E_WARNING));
    $ref = new Bible_Ref('123');
    error_reporting($old);
    assert_eq(bible_ref_get($ref, 'book'), null);
});

// ===========================================================================
// Constructor — single chapter (one number after book)
// ===========================================================================

test('Constructor: "Genesis 1" → whole chapter', function () {
    assert_parsed('Genesis 1', 0, 1, 1, 1, 999, 0, 0, 'Genesis 1');
});

test('Constructor: "Exodus 20" → whole chapter', function () {
    assert_parsed('Exodus 20', 1, 20, 1, 20, 999, 0, 0, 'Exodus 20');
});

test('Constructor: "Psalm 119" → whole long chapter', function () {
    assert_parsed('Psalm 119', 18, 119, 1, 119, 999, 0, 0, 'Psalm 119');
});

test('Constructor: "Revelation 22" → last book, whole chapter', function () {
    assert_parsed('Revelation 22', 65, 22, 1, 22, 999, 0, 0, 'Revelation 22');
});

// ===========================================================================
// Constructor — abbreviations
// ===========================================================================

test('Constructor: "Gen 1" — abbreviated book', function () {
    assert_parsed('Gen 1', 0, 1, 1, 1, 999, 0, 0, 'Gen 1');
});

test('Constructor: "Ps 23" — abbreviated Psalms', function () {
    assert_parsed('Ps 23', 18, 23, 1, 23, 999, 0, 0, 'Ps 23');
});

test('Constructor: "1 Ki 8" — abbreviated Kings', function () {
    assert_parsed('1 Ki 8', 10, 8, 1, 8, 999, 0, 0, '1 Ki 8');
});

test('Constructor: "2 Chr 7" — abbreviated Chronicles', function () {
    assert_parsed('2 Chr 7', 13, 7, 1, 7, 999, 0, 0, '2 Chr 7');
});

// ===========================================================================
// Constructor — single verse
// ===========================================================================

test('Constructor: "Genesis 1:1" → single verse', function () {
    assert_parsed('Genesis 1:1', 0, 1, 1, 1, 1, 0, 0, 'Genesis 1:1');
});

test('Constructor: "John 3:16" → single verse', function () {
    assert_parsed('John 3:16', 42, 3, 16, 3, 16, 0, 0, 'John 3:16');
});

test('Constructor: "Jn 3:16" → abbreviated single verse', function () {
    assert_parsed('Jn 3:16', 42, 3, 16, 3, 16, 0, 0, 'Jn 3:16');
});

// ===========================================================================
// Constructor — verse range within a single chapter
// ===========================================================================

test('Constructor: "Genesis 1:1-5" → verse range', function () {
    assert_parsed('Genesis 1:1-5', 0, 1, 1, 1, 5, 0, 0, 'Genesis 1:1-5');
});

test('Constructor: "Matthew 5:3-12" → beatitudes range', function () {
    assert_parsed('Matthew 5:3-12', 39, 5, 3, 5, 12, 0, 0, 'Matthew 5:3-12');
});

test('Constructor: "Romans 8:28-39" → verse range', function () {
    assert_parsed('Romans 8:28-39', 44, 8, 28, 8, 39, 0, 0, 'Romans 8:28-39');
});

// ===========================================================================
// Constructor — multi-chapter range
// ===========================================================================

test('Constructor: "Genesis 1:1 - 2:3" → multi-chapter', function () {
    assert_parsed('Genesis 1:1 - 2:3', 0, 1, 1, 2, 3, 0, 0, 'Genesis 1:1 - 2:3');
});

test('Constructor: "Matthew 23:1 - 24:5" → multi-chapter', function () {
    assert_parsed('Matthew 23:1 - 24:5', 39, 23, 1, 24, 5, 0, 0, 'Matthew 23:1 - 24:5');
});

test('Constructor: "Psalm 42:1-43:5" → multi-chapter no spaces', function () {
    assert_parsed('Psalm 42:1-43:5', 18, 42, 1, 43, 5, 0, 0, 'Psalm 42:1-43:5');
});

// ===========================================================================
// Constructor — multi-chapter whole-chapter range
// ===========================================================================

test('Constructor: "Genesis 1-3" → whole chapters range', function () {
    assert_parsed('Genesis 1-3', 0, 1, 1, 3, 999, 0, 0, 'Genesis 1-3');
});

test('Constructor: "Revelation 21-22" → whole chapters range', function () {
    assert_parsed('Revelation 21-22', 65, 21, 1, 22, 999, 0, 0, 'Revelation 21-22');
});

// ===========================================================================
// Constructor — numbered books
// ===========================================================================

test('Constructor: "1 John 2:3" → numbered book, single verse', function () {
    assert_parsed('1 John 2:3', 61, 2, 3, 2, 3, 0, 0, '1 John 2:3');
});

test('Constructor: "2 Thess 3:4" → numbered book with space', function () {
    assert_parsed('2 Thess 3:4', 52, 3, 4, 3, 4, 0, 0, '2 Thess 3:4');
});

test('Constructor: "2 Thessalonians 3:4" → full name numbered book', function () {
    assert_parsed('2 Thessalonians 3:4', 52, 3, 4, 3, 4, 0, 0, '2 Thessalonians 3:4');
});

test('Constructor: "1 Peter 1:3" → 1 Peter single verse', function () {
    assert_parsed('1 Peter 1:3', 59, 1, 3, 1, 3, 0, 0, '1 Peter 1:3');
});

test('Constructor: "3 John 1:4" → 3 John', function () {
    assert_parsed('3 John 1:4', 63, 1, 4, 1, 4, 0, 0, '3 John 1:4');
});

test('Constructor: "1 Cor 13:1" → abbreviated numbered book', function () {
    assert_parsed('1 Cor 13:1', 45, 13, 1, 13, 1, 0, 0, '1 Cor 13:1');
});

// ===========================================================================
// Constructor — split readings (comma)
// ===========================================================================

test('Constructor: "Matthew 23:4-5, 8" → split single extra verse', function () {
    assert_parsed('Matthew 23:4-5, 8', 39, 23, 4, 23, 5, 8, 8, 'Matt 23:4-5, 8');
});

test('Constructor: "Matthew 23:4-5, 8-10" → split verse range', function () {
    assert_parsed('Matthew 23:4-5, 8-10', 39, 23, 4, 23, 5, 8, 10, 'Matt 23:4-5, 8-10');
});

test('Constructor: "1 Peter 2:29-3:2,5" → split after multi-chapter', function () {
    assert_parsed('1 Peter 2:29-3:2,5', 59, 2, 29, 3, 2, 5, 5, '1 Peter 2:29-3:2,5');
});

test('Constructor: "1 Peter 2:29-3:2,5-6" → split range after multi-chapter', function () {
    assert_parsed('1 Peter 2:29-3:2,5-6', 59, 2, 29, 3, 2, 5, 6, '1 Peter 2:29-3:2,5-6');
});

test('Constructor: "2 Thess 23:1-2, 4" → numbered book + split', function () {
    assert_parsed('2 Thess 23:1-2, 4', 52, 23, 1, 23, 2, 4, 4, '2 Thess 23:1-2, 4');
});

// ===========================================================================
// Constructor — separator variants (colon vs dot vs hyphen confusion)
// ===========================================================================

test('Constructor: "Genesis 1.1" → dot as verse separator', function () {
    // "1.1" = chapter 1, verse 1 (dot is valid separator in regex)
    assert_parsed('Genesis 1.1', 0, 1, 1, 1, 1, 0, 0, 'Genesis 1.1');
});

test('Constructor: "Genesis 1:1-2:3" → multi-chapter with hyphens', function () {
    // Chapter 1 verse 1 to chapter 2 verse 3
    assert_parsed('Genesis 1:1-2:3', 0, 1, 1, 2, 3, 0, 0, 'Genesis 1:1-2:3');
});

// ===========================================================================
// Constructor — numeric code input (toCode format)
// ===========================================================================

test('Constructor: numeric code roundtrip (no split)', function () {
    // toCode() produces e.g. "000_001:001-001:005" for Genesis 1:1-5
    $ref = new Bible_Ref('000_001:001-001:005');
    assert_eq(bible_ref_get($ref, 'book'), 0);
    assert_eq(bible_ref_get($ref, 'start_ch'), 1);
    assert_eq(bible_ref_get($ref, 'start_v'), 1);
    assert_eq(bible_ref_get($ref, 'end_ch'), 1);
    assert_eq(bible_ref_get($ref, 'end_v'), 5);
    assert_eq(bible_ref_get($ref, 'start_v2'), 0);
    assert_eq(bible_ref_get($ref, 'end_v2'), 0);
});

test('Constructor: numeric code roundtrip (with split)', function () {
    // e.g. Matthew 23:4-5,8 → book index 39
    $ref = new Bible_Ref('039_023:004-023:005,008-008');
    assert_eq(bible_ref_get($ref, 'book'), 39);
    assert_eq(bible_ref_get($ref, 'start_ch'), 23);
    assert_eq(bible_ref_get($ref, 'start_v'), 4);
    assert_eq(bible_ref_get($ref, 'end_ch'), 23);
    assert_eq(bible_ref_get($ref, 'end_v'), 5);
    assert_eq(bible_ref_get($ref, 'start_v2'), 8);
    assert_eq(bible_ref_get($ref, 'end_v2'), 8);
});

test('Constructor: numeric code roundtrip split range', function () {
    // Matthew 23:4-5,8-10
    $ref = new Bible_Ref('039_023:004-023:005,008-010');
    assert_eq(bible_ref_get($ref, 'start_v2'), 8);
    assert_eq(bible_ref_get($ref, 'end_v2'), 10);
});

// ===========================================================================
// toString() — null / empty
// ===========================================================================

test('toString: null book returns empty string', function () {
    $ref = new Bible_Ref('');
    assert_eq($ref->toString(), '');
    assert_eq($ref->toShortString(), '');
});

// ===========================================================================
// toString() — single verse
// ===========================================================================

test('toString: "Genesis 1:1" → full name, single verse', function () {
    $ref = new Bible_Ref('Genesis 1:1');
    assert_eq($ref->toString(), 'Genesis 1:1');
});

test('toString: "John 3:16" → full name, single verse', function () {
    $ref = new Bible_Ref('John 3:16');
    assert_eq($ref->toString(), 'John 3:16');
});

// ===========================================================================
// toString() — verse range
// ===========================================================================

test('toString: "Genesis 1:1-5" → chapter:verse-verse', function () {
    $ref = new Bible_Ref('Genesis 1:1-5');
    assert_eq($ref->toString(), 'Genesis 1:1-5');
});

// ===========================================================================
// toString() — whole chapter
// ===========================================================================

test('toString: "Genesis 1" → chapter only, no verse', function () {
    $ref = new Bible_Ref('Genesis 1');
    assert_eq($ref->toString(), 'Genesis 1');
});

test('toString: "Revelation 22" → last book whole chapter', function () {
    $ref = new Bible_Ref('Revelation 22');
    assert_eq($ref->toString(), 'Revelation 22');
});

// ===========================================================================
// toString() — multi-chapter with verses
// ===========================================================================

test('toString: "Genesis 1:1 - 2:3" → full multi-chapter', function () {
    $ref = new Bible_Ref('Genesis 1:1 - 2:3');
    assert_eq($ref->toString(), 'Genesis 1:1 - 2:3');
});

// ===========================================================================
// toString() — whole chapters range
// ===========================================================================

test('toString: "Genesis 1-3" → whole chapters range', function () {
    $ref = new Bible_Ref('Genesis 1-3');
    assert_eq($ref->toString(), 'Genesis 1 - 3');
});

// ===========================================================================
// toString() — split readings
// ===========================================================================

test('toString: "Matthew 23:4-5, 8" → split single verse', function () {
    $ref = new Bible_Ref('Matthew 23:4-5, 8');
    assert_eq($ref->toString(), 'Matthew 23:4-5, 8');
});

test('toString: "Matthew 23:4-5, 8-10" → split verse range', function () {
    $ref = new Bible_Ref('Matthew 23:4-5, 8-10');
    assert_eq($ref->toString(), 'Matthew 23:4-5, 8-10');
});

test('toString: "1 Peter 2:29-3:2,5-6" → split after multi-chapter', function () {
    $ref = new Bible_Ref('1 Peter 2:29-3:2,5-6');
    assert_eq($ref->toString(), '1 Peter 2:29 - 3:2, 5-6');
});

// ===========================================================================
// toShortString()
// ===========================================================================

test('toShortString: "Genesis 1:1" → "Gen 1:1"', function () {
    $ref = new Bible_Ref('Genesis 1:1');
    assert_eq($ref->toShortString(), 'Gen 1:1');
});

test('toShortString: "1 John 2:3" → "1 Jn 2:3"', function () {
    $ref = new Bible_Ref('1 John 2:3');
    assert_eq($ref->toShortString(), '1 Jn 2:3');
});

test('toShortString: "Revelation 22" → "Rev 22"', function () {
    $ref = new Bible_Ref('Revelation 22');
    assert_eq($ref->toShortString(), 'Rev 22');
});

test('toShortString: "Song of Solomon 1:1" → "Song 1:1"', function () {
    $ref = new Bible_Ref('Song of Solomon 1:1');
    assert_eq($ref->toShortString(), 'Song 1:1');
});

// ===========================================================================
// toString() — ucwords effects on med_names
// ===========================================================================

test('toString: ucwords capitalises each word in med_names', function () {
    // med_names[21] = "song of solomon" → ucwords → "Song Of Solomon"
    $ref = new Bible_Ref('Song of Solomon 1:1');
    assert_eq($ref->toString(), 'Song Of Solomon 1:1');
});

test('toString: "Acts" capitalised properly', function () {
    $ref = new Bible_Ref('Acts 2:1');
    assert_eq($ref->toString(), 'Acts 2:1');
});

test('toString: "1 Samuel" → ucwords preserves digit prefix', function () {
    $ref = new Bible_Ref('1 Samuel 17:50');
    assert_eq($ref->toString(), '1 Samuel 17:50');
});

// ===========================================================================
// toCode() — roundtrip integrity
// ===========================================================================

test('toCode: single verse roundtrip', function () {
    $ref = new Bible_Ref('Genesis 1:1');
    $code = $ref->toCode();
    $ref2 = new Bible_Ref($code);
    assert_eq($ref2->toString(), 'Genesis 1:1');
    assert_eq($ref2->toShortString(), 'Gen 1:1');
});

test('toCode: verse range roundtrip', function () {
    $ref = new Bible_Ref('Genesis 1:1-5');
    $code = $ref->toCode();
    $ref2 = new Bible_Ref($code);
    assert_eq($ref2->toString(), 'Genesis 1:1-5');
});

test('toCode: whole chapter roundtrip', function () {
    $ref = new Bible_Ref('Exodus 20');
    $code = $ref->toCode();
    $ref2 = new Bible_Ref($code);
    assert_eq($ref2->toString(), 'Exodus 20');
});

test('toCode: multi-chapter roundtrip', function () {
    $ref = new Bible_Ref('Genesis 1:1 - 2:3');
    $code = $ref->toCode();
    $ref2 = new Bible_Ref($code);
    assert_eq($ref2->toString(), 'Genesis 1:1 - 2:3');
});

test('toCode: whole chapters range roundtrip', function () {
    $ref = new Bible_Ref('Genesis 1-3');
    $code = $ref->toCode();
    $ref2 = new Bible_Ref($code);
    assert_eq($ref2->toString(), 'Genesis 1 - 3');
});

test('toCode: split reading roundtrip (single extra verse)', function () {
    $ref = new Bible_Ref('Matthew 23:4-5, 8');
    $code = $ref->toCode();
    $ref2 = new Bible_Ref($code);
    assert_eq($ref2->toString(), 'Matthew 23:4-5, 8');
});

test('toCode: split reading roundtrip (extra verse range)', function () {
    $ref = new Bible_Ref('Matthew 23:4-5, 8-10');
    $code = $ref->toCode();
    $ref2 = new Bible_Ref($code);
    assert_eq($ref2->toString(), 'Matthew 23:4-5, 8-10');
});

test('toCode: split reading after multi-chapter roundtrip', function () {
    $ref = new Bible_Ref('1 Peter 2:29-3:2,5-6');
    $code = $ref->toCode();
    $ref2 = new Bible_Ref($code);
    assert_eq($ref2->toString(), '1 Peter 2:29 - 3:2, 5-6');
});

// ===========================================================================
// toCode() — specific known values
// ===========================================================================

test('toCode: Revelation 22:20-21 produces expected code', function () {
    $ref = new Bible_Ref('Revelation 22:20-21');
    // book 65, chapter 22, verses 20-21
    assert_eq($ref->toCode(), '065_022:020-022:021');
});

test('toCode: John 3:16 produces expected code', function () {
    $ref = new Bible_Ref('John 3:16');
    // book 42 (0-indexed: John), chapter 3, verse 16
    assert_eq($ref->toCode(), '042_003:016-003:016');
});

// ===========================================================================
// Edge cases — Song of Solomon (multi-word book names)
// ===========================================================================

test('Edge: "Song of Solomon 2:4" parses book correctly', function () {
    // "songofsolomon" must be in names_to_numbers → index 21
    $ref = new Bible_Ref('Song of Solomon 2:4');
    assert_eq(bible_ref_get($ref, 'book'), 21);
    assert_eq($ref->toShortString(), 'Song 2:4');
});

test('Edge: "Song of Songs 3:4" parses book correctly', function () {
    // "songofsongs" is in names_to_numbers → index 21
    $ref = new Bible_Ref('Song of Songs 3:4');
    assert_eq(bible_ref_get($ref, 'book'), 21);
});

test('Edge: "SoS 5:2" parses as Song of Solomon', function () {
    $ref = new Bible_Ref('SoS 5:2');
    assert_eq(bible_ref_get($ref, 'book'), 21);
    assert_eq($ref->toShortString(), 'Song 5:2');
});

// ===========================================================================
// Edge cases — Acts (multi-word book name)
// ===========================================================================

test('Edge: "Acts of the Apostles 1:8" → "actsoftheapostles" index 43', function () {
    // names_to_numbers has "actsoftheapostles" => 43 (Acts)
    $ref = new Bible_Ref('Acts of the Apostles 1:8');
    assert_eq(bible_ref_get($ref, 'book'), 43);
    assert_eq($ref->toShortString(), 'Acts 1:8');
});

test('Edge: "Acts 2:1" parses normally', function () {
    $ref = new Bible_Ref('Acts 2:1');
    assert_eq(bible_ref_get($ref, 'book'), 43);
});

// ===========================================================================
// Edge cases — case insensitivity
// ===========================================================================

test('Edge: "genesis 1:1" (lowercase) parses same as "Genesis 1:1"', function () {
    $ref = new Bible_Ref('genesis 1:1');
    assert_eq($ref->toString(), 'Genesis 1:1');
});

test('Edge: "GENESIS 1:1" (uppercase) parses same', function () {
    $ref = new Bible_Ref('GENESIS 1:1');
    assert_eq($ref->toString(), 'Genesis 1:1');
});

test('Edge: "jOhN 3:16" (mixed case) parses correctly', function () {
    $ref = new Bible_Ref('jOhN 3:16');
    assert_eq($ref->toString(), 'John 3:16');
});

// ===========================================================================
// Edge cases — no space between book and chapter
// ===========================================================================

test('Edge: "Genesis1:1" (no space between book and chapter) parses', function () {
    $ref = new Bible_Ref('Genesis1:1');
    assert_eq($ref->toShortString(), 'Gen 1:1');
});

test('Edge: "gen1:1" (no space, abbreviated) parses', function () {
    $ref = new Bible_Ref('gen1:1');
    assert_eq($ref->toShortString(), 'Gen 1:1');
});

// ===========================================================================
// Edge cases — single-number reference for numbered books
//   e.g. "1 John 2" → book "1 John" (not book "1" chapter "John 2")
//   The regex picks up the leading digit as part of the book name via [0-9]?
// ===========================================================================

test('Edge: "1 John 2" → book=1 John, chapter 2, not book 1 ch John v 2', function () {
    $ref = new Bible_Ref('1 John 2');
    assert_eq(bible_ref_get($ref, 'book'), 61); // 1 John, not Genesis
    assert_eq($ref->toShortString(), '1 Jn 2');
});

// ===========================================================================
// Edge cases — separator variation with semicolons, commas as main sep
// ===========================================================================

test('Edge: "Matthew 28:19-20" → verse range with hyphen', function () {
    $ref = new Bible_Ref('Matthew 28:19-20');
    assert_eq($ref->toShortString(), 'Matt 28:19-20');
});

// ===========================================================================
// Edge cases — extreme chapter/verse numbers (should handle large values)
// ===========================================================================

test('Edge: "Psalm 119:176" → large verse number', function () {
    $ref = new Bible_Ref('Psalm 119:176');
    assert_eq((string) bible_ref_get($ref, 'end_v'), '176');
    assert_eq($ref->toShortString(), 'Ps 119:176');
});

// ===========================================================================
// Edge cases — all 66 books can be parsed and formatted
// ===========================================================================

test('Edge: all 66 books parse and produce non-empty toString', function () {
    // spot-check every book by its common abbreviation
    $tests = [
        ['Genesis', 'Gen 1:1'],
        ['Exodus', 'Ex 1:1'],
        ['Leviticus', 'Lev 1:1'],
        ['Numbers', 'Num 1:1'],
        ['Deuteronomy', 'Deut 1:1'],
        ['Joshua', 'Josh 1:1'],
        ['Judges', 'Judges 1:1'],
        ['Ruth', 'Ruth 1:1'],
        ['1 Samuel', '1 Sam 1:1'],
        ['2 Samuel', '2 Sam 1:1'],
        ['1 Kings', '1 Ki 1:1'],
        ['2 Kings', '2 Ki 1:1'],
        ['1 Chronicles', '1 Chr 1:1'],
        ['2 Chronicles', '2 Chr 1:1'],
        ['Ezra', 'Ezra 1:1'],
        ['Nehemiah', 'Neh 1:1'],
        ['Esther', 'Esth 1:1'],
        ['Job', 'Job 1:1'],
        ['Psalm', 'Ps 1:1'],
        ['Proverbs', 'Pr 1:1'],
        ['Ecclesiastes', 'Eccl 1:1'],
        ['Song of Solomon', 'Song 1:1'],
        ['Isaiah', 'Isa 1:1'],
        ['Jeremiah', 'Jer 1:1'],
        ['Lamentations', 'Lam 1:1'],
        ['Ezekiel', 'Ezek 1:1'],
        ['Daniel', 'Dan 1:1'],
        ['Hosea', 'Hos 1:1'],
        ['Joel', 'Joel 1:1'],
        ['Amos', 'Amos 1:1'],
        ['Obadiah', 'Obad 1:1'],
        ['Jonah', 'Jonah 1:1'],
        ['Micah', 'Micah 1:1'],
        ['Nahum', 'Nahum 1:1'],
        ['Habakkuk', 'Hab 1:1'],
        ['Zephaniah', 'Zeph 1:1'],
        ['Haggai', 'Hag 1:1'],
        ['Zechariah', 'Zech 1:1'],
        ['Malachi', 'Mal 1:1'],
        ['Matthew', 'Matt 1:1'],
        ['Mark', 'Mk 1:1'],
        ['Luke', 'Lk 1:1'],
        ['John', 'Jn 1:1'],
        ['Acts', 'Acts 1:1'],
        ['Romans', 'Rom 1:1'],
        ['1 Corinthians', '1 Cor 1:1'],
        ['2 Corinthians', '2 Cor 1:1'],
        ['Galatians', 'Gal 1:1'],
        ['Ephesians', 'Eph 1:1'],
        ['Philippians', 'Phil 1:1'],
        ['Colossians', 'Col 1:1'],
        ['1 Thessalonians', '1 Thes 1:1'],
        ['2 Thessalonians', '2 Thes 1:1'],
        ['1 Timothy', '1 Tim 1:1'],
        ['2 Timothy', '2 Tim 1:1'],
        ['Titus', 'Titus 1:1'],
        ['Philemon', 'Philemon 1:1'],
        ['Hebrews', 'Heb 1:1'],
        ['James', 'Jam 1:1'],
        ['1 Peter', '1 Pet 1:1'],
        ['2 Peter', '2 Pet 1:1'],
        ['1 John', '1 Jn 1:1'],
        ['2 John', '2 Jn 1:1'],
        ['3 John', '3 Jn 1:1'],
        ['Jude', 'Jude 1:1'],
        ['Revelation', 'Rev 1:1'],
    ];
    foreach ($tests as [$input_book, $expected_short]) {
        $ref = new Bible_Ref("$input_book 1:1");
        assert_eq($ref->toShortString(), $expected_short, "book: $input_book");
    }
});

// ===========================================================================
// Edge cases — printJSRegex is not empty
// ===========================================================================

test('Edge: printJSRegex produces a non-empty regex string', function () {
    $ref = new Bible_Ref('Genesis 1:1');
    ob_start();
    $ref->printJSRegex();
    $out = ob_get_clean();
    assert_true(strlen($out) > 10, 'JS regex should be substantial');
    assert_contains($out, 'genesis', 'JS regex should contain genesis');
    assert_contains($out, 'revelation', 'JS regex should contain revelation');
});

// ===========================================================================
// BUG-FINDING: does the constructor mutate the input via strtolower/str_replace?
//   It does — $str = strtolower(str_replace(' ', '', $str)) — but that's fine,
//   strings are passed by value in PHP.
// ===========================================================================

// ===========================================================================
// BUG-FINDING: empty("0") in the split-verse check
//   If someone writes "John 3:16, 0" (verse 0), the empty($matches[16]) check
//   would treat "0" as empty because PHP's empty() considers "0" falsy.
//   But verse 0 doesn't exist in the Bible, so this is acceptable.
// ===========================================================================

// ===========================================================================
// BUG-FINDING: names_to_numbers has duplicate keys silently using last value.
//   E.g. '1sam' appears at index 163 and 164 with the same value (8).
//   Harmless but wasteful. Test that both resolve correctly.
// ===========================================================================

test('BUG: duplicate key "1sam" in names_to_numbers resolves to 1 Samuel (8)', function () {
    $ref = new Bible_Ref('1 Sam 1:1');
    assert_eq(bible_ref_get($ref, 'book'), 8);
});

test('BUG: "jn" resolves to John (42) via last duplicate', function () {
    $ref = new Bible_Ref('Jn 1:1');
    assert_eq(bible_ref_get($ref, 'book'), 42);
});
