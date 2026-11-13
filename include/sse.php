<?php

/**
 * ============================================================================
 * Minimal Server-Sent Events helper for Datastar
 * ============================================================================
 *
 * Namespace: Jethro
 *
 * Emits the SSE event frames the Datastar v1.x client consumes. Jethro has no
 * Composer autoloader, so we deliberately do NOT pull in the Datastar SDK —
 * the wire format is tiny and stable. See
 * docs/docs/developer/sms/SMS_DATASTAR.md for how this is used by
 * calls/call_sms_statusline.class.php.
 *
 * Datastar SSE wire format (v1.x):
 *
 *   event: datastar-patch-elements
 *   data: elements <div id="foo">Hello</div>
 *   <blank line>
 *
 *   event: datastar-patch-signals
 *   data: signals {"foo": 1, "bar": 2}
 *   <blank line>
 *
 * Default element patch mode is `morph`, matched by top-level element id —
 * which is exactly what the statusline/preview replacement wants. Multi-line
 * HTML is split across repeated `data: elements` lines. Each event is
 * terminated by a blank line (two newlines).
 *
 * This is a ONE-SHOT response: the Call emits its frames and returns; the
 * connection then closes. Datastar accepts a connection that closes
 * immediately after the frames.
 */

namespace Jethro;

/**
 * Begin an SSE response: flush any output buffers, set the streaming headers,
 * and disable proxy/php-fpm buffering so frames reach the client promptly.
 *
 * Safe to call once per request, before any ssePatch* call.
 */
function sseStart(): void
{
    // Drain any output buffering so our frames are not held back.
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    if (!headers_sent()) {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        // Disable nginx/php-fpm response buffering (see nginx/CLAUDE_NGINX.md).
        header('X-Accel-Buffering: no');
    }
}

/**
 * Format a `datastar-patch-elements` frame for $html, terminator included.
 *
 * SSE ends a line at CR, LF or CRLF, so a bare CR inside the HTML would end
 * the `data: elements` line early and let the rest of the HTML start a new
 * field (e.g. an injected `event: datastar-patch-signals`, whose payload
 * Datastar evaluates as JavaScript). Person names and message text reach here,
 * so split on every SSE line terminator, not just "\n".
 *
 * Without $mode/$selector, Datastar morphs each top-level element into the
 * element with the same id. With them, e.g. ('append', 'body'), the HTML is
 * inserted relative to the element $selector matches; Datastar runs any
 * `<script>` it inserts this way.
 *
 * Pure: used by ssePatchElements() and unit-tested in tests/unit/test_sse.php.
 *
 * @param string|null $mode     Datastar patch mode (`outer`, `inner`, `replace`,
 *                              `prepend`, `append`, `before`, `after`, `remove`);
 *                              NULL = Datastar's default (morph by id).
 *                              `replace` swaps in fresh nodes, so JS state bound
 *                              to the old ones (event handlers) does not survive.
 * @param string|null $selector CSS selector to patch; NULL = match by element id.
 *                              Code-supplied, never user input.
 * @throws \InvalidArgumentException if $mode or $selector contains a line terminator
 */
function sseElementsFrame(string $html, ?string $mode = null, ?string $selector = null): string
{
    $frame = "event: datastar-patch-elements\n";
    foreach (['selector' => $selector, 'mode' => $mode] as $field => $value) {
        if ($value === null) continue;
        if (preg_match('/[\r\n]/', $value)) {
            throw new \InvalidArgumentException("SSE $field must be a single line");
        }
        $frame .= "data: $field $value\n";
    }
    foreach (preg_split('/\r\n|\r|\n/', $html) as $line) {
        $frame .= 'data: elements ' . $line . "\n";
    }
    return $frame . "\n";
}

/**
 * Emit a `datastar-patch-elements` frame. By default the element is morphed
 * into the DOM by id; pass $mode/$selector to patch differently
 * ({@see sseElementsFrame()}).
 */
function ssePatchElements(string $html, ?string $mode = null, ?string $selector = null): void
{
    echo sseElementsFrame($html, $mode, $selector);
    flush();
}

/**
 * Emit a `datastar-patch-signals` frame, patching the named signals into the
 * client's signal store. Values are JSON-encoded.
 *
 * @param array<string, mixed> $signals
 */
function ssePatchSignals(array $signals): void
{
    echo "event: datastar-patch-signals\n";
    echo 'data: signals ' . json_encode($signals, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    echo "\n";
    flush();
}
