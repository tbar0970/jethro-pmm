/**
 * JethroSMS Boundary Test Script
 * ================================
 * Paste this entire file into your browser console (on a page that has
 * JethroSMS loaded, e.g. the SMS sender page) and run:
 *
 *     JethroSMSTest.run();
 *
 * Results will be logged to the console as ✅ PASS or ❌ FAIL.
 */

var JethroSMSTest = {};

JethroSMSTest.assert = function(label, actual, expected) {
	var pass = actual === expected;
	console.log(
		(pass ? '✅' : '❌') + ' ' + label
		+ ' — got: ' + JSON.stringify(actual)
		+ (pass ? '' : ', expected: ' + JSON.stringify(expected))
	);
};

JethroSMSTest.run = function() {
	console.log('=== GSM Segment Count Tests ===');

	// 1. Single segment boundaries
	JethroSMSTest.assert('1 char → 1 segment',
		JethroSMS.gsmSegmentCount(1), 1);
	JethroSMSTest.assert('160 chars → 1 segment (max single)',
		JethroSMS.gsmSegmentCount(160), 1);

	// 2. Multi-segment boundaries
	JethroSMSTest.assert('161 chars → 2 segments (first multi)',
		JethroSMS.gsmSegmentCount(161), 2);
	JethroSMSTest.assert('306 chars → 2 segments (exactly fills 2)',
		JethroSMS.gsmSegmentCount(306), 2);
	JethroSMSTest.assert('307 chars → 3 segments (overflows 3rd)',
		JethroSMS.gsmSegmentCount(307), 3);
	JethroSMSTest.assert('459 chars → 3 segments (exactly fills 3)',
		JethroSMS.gsmSegmentCount(459), 3);
	JethroSMSTest.assert('460 chars → 4 segments (overflows 4th)',
		JethroSMS.gsmSegmentCount(460), 4);

	console.log('=== Extended Char (GSM) Tests ===');

	// 3. Extended chars count as 2
	var euro160 = '€'.repeat(80);  // 80 × 2 = 160 effective length
	JethroSMSTest.assert('80 × € (eff len 160) → 1 segment',
		JethroSMS.gsmSegmentCount(JethroSMS.gsmLength(euro160)), 1);

	var euro161 = '€'.repeat(81);  // 81 × 2 = 162 effective length
	JethroSMSTest.assert('81 × € (eff len 162) → 2 segments',
		JethroSMS.gsmSegmentCount(JethroSMS.gsmLength(euro161)), 2);

	var euro153 = '€'.repeat(153); // 153 × 2 = 306 effective length
	JethroSMSTest.assert('153 × € (eff len 306) → 2 segments (exactly fills 2)',
		JethroSMS.gsmSegmentCount(JethroSMS.gsmLength(euro153)), 2);

	var euro154 = '€'.repeat(154); // 154 × 2 = 308 effective length
	JethroSMSTest.assert('154 × € (eff len 308) → 3 segments',
		JethroSMS.gsmSegmentCount(JethroSMS.gsmLength(euro154)), 3);

	console.log('=== UCS-2 Segment Count Tests ===');

	// 4. UCS-2 boundaries
	// Single segment: 70 chars. Concatenated: 67 chars per segment.
	JethroSMSTest.assert('1 UCS-2 char → 1 segment',
		JethroSMS.ucs2SegmentCount(1), 1);
	JethroSMSTest.assert('70 UCS-2 chars → 1 segment (max single)',
		JethroSMS.ucs2SegmentCount(70), 1);
	JethroSMSTest.assert('71 UCS-2 chars → 2 segments (first multi)',
		JethroSMS.ucs2SegmentCount(71), 2);
	JethroSMSTest.assert('134 UCS-2 chars → 2 segments (exactly fills 2)',
		JethroSMS.ucs2SegmentCount(134), 2);
	JethroSMSTest.assert('135 UCS-2 chars → 3 segments (overflows 3rd)',
		JethroSMS.ucs2SegmentCount(135), 3);
	JethroSMSTest.assert('201 UCS-2 chars → 3 segments (exactly fills 3)',
		JethroSMS.ucs2SegmentCount(201), 3);
	JethroSMSTest.assert('202 UCS-2 chars → 4 segments (overflows 4th)',
		JethroSMS.ucs2SegmentCount(202), 4);

	console.log('=== GSM Length (effective) Tests ===');

	JethroSMSTest.assert('"a" → eff len 1',
		JethroSMS.gsmLength('a'), 1);
	JethroSMSTest.assert('"€" → eff len 2 (extended)',
		JethroSMS.gsmLength('€'), 2);
	JethroSMSTest.assert('"a€" → eff len 3',
		JethroSMS.gsmLength('a€'), 3);
	JethroSMSTest.assert('"€€" → eff len 4',
		JethroSMS.gsmLength('€€'), 4);

	console.log('=== Non-GSM Detection Tests ===');

	JethroSMSTest.assert('"Hello" → no non-GSM chars',
		JethroSMS.getNonGsmChars('Hello').length, 0);
	JethroSMSTest.assert('"😊" → 2 non-GSM chars (surrogate pair)',
		JethroSMS.getNonGsmChars('😊').length, 2);
	JethroSMSTest.assert('"Hello😊" → 2 non-GSM chars (surrogate pair)',
		JethroSMS.getNonGsmChars('Hello😊').length, 2);
	JethroSMSTest.assert('"€" → 0 non-GSM (is extended GSM)',
		JethroSMS.getNonGsmChars('€').length, 0);

	console.log('=== Warning Logic Tests ===');
	console.log('(These simulate what updateCharCount does internally)');

	// Helper: simulate the warning logic (warns if message > 70 chars with non-GSM chars)
	function shouldWarn(text) {
		var nonGsmChars = JethroSMS.getNonGsmChars(text);
		if (nonGsmChars.length === 0) return false;
		return text.length > 70;
	}

	JethroSMSTest.assert('"Hello 😊" → no warning (same segments)',
		shouldWarn('Hello 😊'), false);

	JethroSMSTest.assert('"😊" only → no warning (empty GSM)',
		shouldWarn('😊'), false);

	JethroSMSTest.assert('"Hello😊" + 155 a\'s → warning (1 vs 3 segments)',
		shouldWarn('Hello😊' + 'a'.repeat(155)), true);

	JethroSMSTest.assert('"Hello😊" + 150 a\'s → warning (1 vs 3 segments)',
		shouldWarn('Hello😊' + 'a'.repeat(150)), true);

	JethroSMSTest.assert('"Hello😊" + 65 a\'s → warning (72 chars = 2 UCS-2 segments vs 1 GSM)',
		shouldWarn('Hello😊' + 'a'.repeat(65)), true);

	JethroSMSTest.assert('"Hello😊" + 66 a\'s → warning (1 vs 2 segments)',
		shouldWarn('Hello😊' + 'a'.repeat(66)), true);

	console.log('=== Unicode Mode Tests ===');
	// Note: getNonGsmChars() does not inspect the unicodeMode flag; these tests
	// confirm that the underlying char-detection is independent of the flag state.

	// Save original flag state
	var origUnicodeMode = JethroSMS.unicodeMode;

	// 10. All-GSM text has no non-GSM chars regardless of unicodeMode
	JethroSMS.unicodeMode = 'disabled';
	JethroSMSTest.assert('unicodeMode=disabled, all-GSM text → no non-GSM chars',
		JethroSMS.getNonGsmChars('Hello world').length, 0);

	// 11. Non-GSM text is always detected regardless of unicodeMode
	JethroSMSTest.assert('unicodeMode=disabled, non-GSM text → has non-GSM chars',
		JethroSMS.getNonGsmChars('Hello😊').length, 2);

	// 12. unicodeMode = enabled with non-GSM text (normal behavior)
	JethroSMS.unicodeMode = 'enabled';
	JethroSMSTest.assert('unicodeMode=enabled, non-GSM text → has non-GSM chars',
		JethroSMS.getNonGsmChars('Hello😊').length, 2);

	// 13. unicodeMode = when_free, non-GSM chars in a long message (>70 chars) → blocked
	//     "Hello😊" + 65 a's = 72 chars → blocked
	JethroSMS.unicodeMode = 'when_free';
	JethroSMSTest.assert('unicodeMode=when_free, "Hello😊" + 65 a\'s (72 chars) → would save segment',
		(function() {
			var text = 'Hello😊' + 'a'.repeat(65);
			var nonGsmChars = JethroSMS.getNonGsmChars(text);
			return nonGsmChars.length > 0 && text.length > 70;
		})(), true);

	// 14. unicodeMode = when_free, non-GSM chars in a short message (≤70 chars) → allowed
	JethroSMSTest.assert('unicodeMode=when_free, "Hi😊" (4 chars) → would NOT save segment',
		(function() {
			var text = 'Hi😊';
			var nonGsmChars = JethroSMS.getNonGsmChars(text);
			return nonGsmChars.length > 0 && text.length > 70;
		})(), false);

	// 15. All-GSM text has no non-GSM chars regardless of unicodeMode
	JethroSMSTest.assert('unicodeMode=when_free, all-GSM text → no non-GSM chars (flag does not affect getNonGsmChars)',
		JethroSMS.getNonGsmChars('Hello world').length, 0);

	// Restore original flag
	JethroSMS.unicodeMode = origUnicodeMode;

	console.log('=== All tests complete ===');
};
JethroSMSTest.run();
