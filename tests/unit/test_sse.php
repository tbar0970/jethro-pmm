<?php

/**
 * Unit tests for include/sse.php — the Datastar SSE frame formatter.
 *
 * Regression: ssePatchElements() used to split the HTML on "\n" only. SSE also
 * ends a line at a bare CR, so a CR in a person's name split the
 * `data: elements` line and let the rest of the name start a new field, e.g. an
 * injected `event: datastar-patch-signals` frame (stored XSS in the SMS
 * composer). See docs/docs/developer/sms/SMS_DATASTAR.md.
 */

require_once __DIR__ . '/../../include/sse.php';

use function Jethro\sseElementsFrame;
use function Test\assert_eq;
use function Test\assert_false;
use function Test\assert_throws;
use function Test\assert_true;
use function Test\test;

/** Number of frames in $s: each frame is one line starting with `event:`. */
function sseEventCount(string $s): int
{
    return count(array_filter(
        explode("\n", $s),
        static fn(string $line): bool => str_starts_with($line, 'event:')
    ));
}

test('sseElementsFrame: single-line HTML is one data line', function () {
    assert_eq(
        sseElementsFrame('<div id="a">Hello</div>'),
        "event: datastar-patch-elements\ndata: elements <div id=\"a\">Hello</div>\n\n"
    );
});

test('sseElementsFrame: LF and CRLF become separate data lines', function () {
    $frame = sseElementsFrame("<div id=\"a\">one\r\ntwo\nthree</div>");
    assert_eq(sseEventCount($frame), 1, 'still one frame');
    assert_eq(substr_count($frame, 'data: elements '), 3);
    assert_false(str_contains($frame, "\r"), 'no CR reaches the wire');
});

test('sseElementsFrame: a bare CR cannot start a new field', function () {
    // What a person's name containing CR would otherwise inject.
    $html = "<div id=\"a\">X\revent: datastar-patch-signals\rdata: signals {\"a\": 1}\rY</div>";
    $frame = sseElementsFrame($html);
    assert_eq(sseEventCount($frame), 1, 'only the frame we emitted');
    // Every line after the event line must be data for that one frame.
    foreach (array_slice(explode("\n", rtrim($frame)), 1) as $i => $line) {
        assert_true(str_starts_with($line, 'data: elements '), 'line ' . ($i + 1) . ' is not a data line: ' . $line);
    }
    assert_false(str_contains($frame, "\r"));
});

test('sseElementsFrame: every line carries the data prefix', function () {
    $frame = sseElementsFrame("a\nb\nc");
    foreach (explode("\n", trim($frame)) as $i => $line) {
        if ($i === 0) {
            assert_true(str_starts_with($line, 'event: datastar-patch-elements'));
            continue;
        }
        assert_true(str_starts_with($line, 'data: elements '), "line $i is a data line");
    }
});

test('sseElementsFrame: selector and mode precede the elements lines', function () {
    assert_eq(
        sseElementsFrame('<script>x()</script>', 'append', 'body'),
        "event: datastar-patch-elements\ndata: selector body\ndata: mode append\ndata: elements <script>x()</script>\n\n",
    );
});

test('sseElementsFrame: a selector with a line terminator is refused', function () {
    assert_throws(\InvalidArgumentException::class, fn() => sseElementsFrame('<p></p>', null, "body\nevent: x"));
});
