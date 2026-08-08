---
title: SMS Datastar / HATEOAS statusline
sidebar_position: 10
---

# SMS Bulk Composer — Datastar / HATEOAS

The SMS composer's live cost/segment/preview logic is **server-owned**. The
client is "dumb": on debounced textarea input (and recipient / `sms_type`
changes) it POSTs the form to a Server-Sent-Events endpoint; the server
computes everything and returns rendered HTML for the status line and the
preview panel, plus the signals that gate the Send button. Only the trivial
live character count (`text.length`) stays client-side, driven by a Datastar
signal.

This replaced a large block of duplicated JavaScript that re-implemented GSM
03.38 / UCS-2 segment counting, URL-shorten estimation, the unicode policy,
per-segment cost, balance checks, and the entire status-line composition — all
of which is fundamentally the server's knowledge. There is now **one
authority**.

## Components

| File | Role |
|------|------|
| `jethro-sms/src/sms_statusline.php` | Pure PHP core + `renderStatusline()` / `renderPreviewPanel()` renderers. |
| `calls/call_sms_statusline.class.php` | SSE endpoint for the live cost/segment/preview pipeline. Also says which recipients the send would leave out (no mobile number, archived; `Jethro\Sms\leftOutNotice()`), and blocks Send when nobody is left to send to. |
| `include/sse.php` | SSE helper (`sseStart()`, `ssePatchElements()`, `ssePatchSignals()`). Used because the statusline response streams both HTML and signals in one request; a one-shot response could also patch signals from a plain `application/json` body. |
| `resources/js/datastar-1.0.4.min.js` | Vendored Datastar v1.0.4 ESM bundle. |
| `include/jethro_sms.php` | `printTextbox()` / `printBulkSmsForm()` / `printSmsModal()` — carries Datastar attributes, ids, initial server render. |
| `templates/head.template.php` | Loads Datastar `<script type="module">` only where a consumer can render: for SMS users (`PERM_SENDSMS` or `PERM_VIEWSMS`, with the SMS feature on — composer, statusline, Messages filters) and for `PERM_SYSADMIN` (the System Configuration status panels: SMTP, Mailchimp, Bible API, URL shortener, SMS). It is not loaded for other users. |
| `resources/js/jethro-sms.js` | Modal opener; bulk-send "send to everyone listed?" confirmation; `JethroSMS.applySendOutcome()` (row classes, closing the modal, reloading a Messages tab, as decided by `Call_SMS`); scheduled-send default; Persons → Messages cost total. Sending itself is Datastar: see [Send Pipeline](./send-pipeline). |
| `jethro-sms/tests/statusline/*.php` | Unit tests for the pure core + renderers. Replaced old browser-only `jethro-sms-test.js`. |
| `calls/call_sms_sendernum.class.php` | Sender-number registration / OTP validation — returns ID'd HTML for Datastar morph. |
| `calls/call_sms_cancel.class.php` | Cancel scheduled SMS — returns ID'd `<span>` for Datastar morph. |
| `calls/call_sms_info.class.php` | Delivery status lookup — returns HTML with Datastar `data-on:click` cancel links. |

The SSE event names are the **v1.x** names: `datastar-patch-elements` and
`datastar-patch-signals` (renamed from the v0.x `datastar-merge-fragments` /
`datastar-merge-signals`). When upgrading, re-confirm these names against the
new release and against `include/sse.php`.

## SSE protocol

`Call_SMS_Statusline` emits a one-shot response (frames, then close):

```
event: datastar-patch-elements
data: elements <div id="sms-statusline-bulk" class="smscharactercount soft">...</div>

event: datastar-patch-elements
data: elements <div id="sms-preview-panel" class="sms-preview-panel">...</div>

event: datastar-patch-signals
data: signals {"smsSendBlocked": false, "smsBlockReason": ""}
```

Default element patch mode is **morph**, matched by top-level element `id`.
The morph targets are `#sms-statusline-bulk` / `#sms-preview-panel-bulk` for the
bulk form and `#sms-statusline` / `#sms-preview-panel` for the modal; the client
sends the ids in hidden `statusline_id` / `preview_id` form fields.
`sseStart()` flushes output buffers and sets `X-Accel-Buffering: no` so
nginx/php-fpm don't buffer the stream (see `nginx/CLAUDE_NGINX.md`).

## Signals

### Statusline / preview pipeline (SSE)

| Signal | Source | Meaning |
|--------|--------|---------|
| `$smsmessage` / `$smsmessagebulk` | `data-bind` on textarea | Live char count (`data-text="$smsmessage.length + ' chars'"`) |
| `$smspreview` / `$smspreviewbulk` | `data-bind` on preview checkbox | Show/hide the preview panel wrapper |
| `$smsSendBlocked` | SSE `datastar-patch-signals` | `true` when blocked (unicode policy or over budget) |
| `$smsBlockReason` | SSE `datastar-patch-signals` | Human-readable block reason |
| `$editingRow` | Pencil button `data-on:click` | Key of the preview row in edit mode |
| `$smsoverride_<KEY>` | `data-bind` on override textarea | Override text for that row's message |

### UI toggles (client-only, no SSE)

| Signal | Source | Applies to |
|--------|--------|------------|
| `$saveasnote` / `$saveasnotebulk` | `data-bind` on "Create Note" checkbox | `data-show` on note subject/action-date fields |
| `$schedulesend` / `$schedulesendbulk` | `data-bind` on "Schedule Send" checkbox | `data-show` on the datetime picker |
| `$smssender` / `$smssenderbulk` | `data-bind` on sender `<select>` | `data-show="$smssender == '_USER_MOBILE_'"` on registration-wrapper |

### Message-list filters (client-only, no SSE)

| Signal | Source | Applies to |
|--------|--------|------------|
| `$showMultiSms` | `data-bind` on `#show-multi-sms` checkbox | `data-show` on `.sms-multi` message rows |
| `$showSentSms` / `$showFailedSms` / … | `data-bind` on status filter checkboxes | Per-row `data-show` derived from each message's delivery status |
| `$filterSender` / `$filterRecipient` / `$filterBody` / `$filterDateFrom` / `$filterDateTo` / `$filterSingleOnly` | `data-bind` on admin SMS history filters | Per-row `data-show` via `el.dataset.*` comparisons (no server round-trip, so no debounce) |

## Client wiring — SSE (statusline pipeline)

- The composer `<textarea>` carries `data-bind:<signal>` (live count) and
  `data-on:input__debounce.300ms="@post('?call=sms_statusline', {contentType: 'form'})"`.
- The `sms_type` radios carry `data-on:change` posting to the same endpoint.
- `{contentType: 'form'}` submits the enclosing `<form>` so `sender`,
  `sms_type`, `personid[]` and `message_overrides[...]` ride along.

## Client wiring — HTML morph (cancel, register, OTP)

Cancel links, sender-number registration, and OTP validation use a simpler
pattern: the element carries a `data-on:click="@post(...)"` attribute, the
server endpoint returns `text/html` with an ID'd element, and Datastar morphs it
into the existing DOM by `id`. No SSE frames, no JSON parsing, no jQuery
`.html()` injection.

| Flow | Trigger | Endpoint | Response |
|------|---------|----------|----------|
| Cancel | `data-on:click="@post('?call=sms_cancel&sms_id=N')"` | `call_sms_cancel` | `<span id="sms-cancel-N">…</span>` |
| Register | `data-on:click="@post('?call=sms_sendernum&action=register&number=…&label=…')"` | `call_sms_sendernum` | `<div id="sms-register-label">…</div>` |
| OTP verify | `data-on:click="@post('?call=sms_sendernum&action=validate&number=…&label=…&otp=' + $otp)"` | `call_sms_sendernum` | `<div id="sms-register-label">…</div>` |

The modal body is a `<form onsubmit="return false">` with a hidden
`personid` field that the modal-open handler in `jethro-sms.js` populates.

## Delivery-status polling (`?call=sms_info`)

Every delivery whose status may still change (`!SmsStatus::isFinal()` —
scheduled, queued, sending, in-progress, **sent**, unknown) renders a polling
span (`renderSmsDeliveryStatusIndicator()` / `renderSmsDeliveryStatusIcon()` in
`include/jethro_sms.php`) that `@get('?call=sms_info&id=N')` on a
`data-on-interval`, plus once on render (`data-init`) when past its send
time; the response morphs the whole batch's status spans by `id`, re-arming
each with a fresh interval.  'sent' is polled because delivery confirmation
(✓✓) or a late carrier failure arrives after the gateway accepts the message.
Spans stop polling once final, or `STATUS_POLL_WINDOW_SECS` (24h) after the
send time.  Throttles that keep this from storming:

1. **Interval backoff** — `smsDeliveryPollIntervalSecs()` counts from the send
   time (`scheduled_send_at`, else `sms.created`): it shrinks approaching it
   (tenth of remaining, floor 2s, cap 300s), grows again at the same rate once
   past, and stops polling (static span) 24h after it.  Without the stop,
   stale deliveries — and carriers that never send a delivery receipt — would
   poll forever (see [Delivery polling](./delivery-tracking.mdx)).
2. **Thundering-herd gate** (Messages page, `&batch=1`) — only the lowest-ID
   non-final delivery per `sms_id` performs the upstream lookup; sibling polls
   return empty.  A delivery already final in the DB is not gated and renders
   its stored status without a provider call (its span may be stale if the
   poll that resolved it lost its response).
3. **One list call per poll** — `Call_SMS_Info` asks the provider once
   (`listRecentDeliveries()` over the polling window) and resolves the polled
   delivery and its siblings from that; per-message `updateDelivery()` is only
   the fallback for providers without `BATCH_DELIVERY_QUERY`.  The result is
   not cached between polls.

## Per-recipient overrides

Overrides are server-rendered in `renderPreviewPanel()`. The preview has **one
row per phone number** (`Jethro\Sms\previewRows()`): people sharing a mobile get
one message, so they share a row — names one per line, the combined message
("Hello Alice & Bob"). Each row has a key: its person IDs joined by `_` (`140`,
or `140_142_143` for a shared mobile). The row's message `<span>` and edit
`<textarea>` are bound to `$smsoverride_<KEY>`, and the textarea is named
`message_overrides[KEY]`. The pencil button sets `$editingRow` to toggle the
swap (`data-show`); on blur the textarea posts back and the server recomputes
the cost line (no segment/cost maths runs client-side). Because the override
textareas are real form fields, the **send** path (`?call=sms`) picks them up
via `serialize()` (bulk) or a DOM sweep (modal); `Call_SMS` applies a row's
override to every person in its key, in one entry, so a shared mobile still
gets one message. Both calls parse the field with
`Jethro\Sms\messageOverridesFromRequest()`.

A person whose number is on the provider's opt-out list gets a greyed
`tr.sms-preview-optedout` row ("Opted out of SMS — will not be sent") with no
pencil or textarea; `renderStatusline()` leaves such rows out of the recipient
count and cost. They come from `OptOutSmsProvider` placeholders
(`SmsStatus::OPTED_OUT`) via the same preview path.

## Encoding decision — UTF-16 counting vs code-point display

> This was a genuine ambiguity, resolved as follows and pinned by
> `jethro-sms/tests/statusline/test_nongsm_detection.php` and `test_gsm_length.php`.

The old JS counted **UTF-16 code units** (`String.length`, `charAt()`), so an
astral character (e.g. an emoji 😊, a surrogate pair) counts as **2**. The PHP
port replicates this exactly for all *length/segment/policy maths*:
`utf16Length()` converts to UTF-16BE and divides the byte length by 2, and
`getNonGsmChars()` iterates UTF-16 code units (surrogate halves are always
non-GSM). This keeps the billed segment count identical to the old client.

For *human-readable display* of the offending characters (the "Remove special
characters (…)" / "Unicode characters are not allowed: …" messages), a separate
`getNonGsmDisplayChars()` iterates by **code point** (`mb_str_split`), so an
emoji renders as itself rather than as two U+FFFD replacement characters. This
is a deliberate, minor divergence from the old JS display (which showed two
lone surrogates). Counting parity is preserved; only the cosmetic display
improved.

## Current implementation state

### Converted to Datastar

| Area | Mechanism | JS removed |
|------|-----------|------------|
| Cost/segment/preview pipeline | `@post` → SSE → morph + signals | ~400 lines (all maths, render) |
| Save-as-note toggle | `data-bind` + `data-show` | 9 lines |
| Schedule-send toggle | `data-bind` + `data-show` (visibility); JS keeps datetime default | 6 lines |
| Cancel link | `data-on:click="@post(...)"` → server HTML morph | 14 lines |
| Sender registration | `data-on:click="@post(...)"` → server HTML morph | 26 lines |
| OTP validation | `data-on:click` + signal binding → server HTML morph | 27 lines |
| Sender select toggle | `data-bind` on select + `data-show` comparison | 24 lines |
| Show-multi-sms filter | `data-bind` on checkbox + `data-show` on rows | 9 lines |
| Status filters | Per-status `data-bind` on checkboxes + per-row `data-show` | ~30 lines (entire function removed) |
| `initMessageFilters` / `initSmsHistoryFilters` duplicates in `jethro.js` | Removed | 82 lines |
| Admin SMS history filters (`initSmsHistoryFilters` in `jethro-sms.js`) | Per-row `data-show` reading `el.dataset.*` against filter signals | 35 lines |

### Still jQuery (by design)

| Area | Reason |
|------|--------|
| Send / note-creation flow (`?call=sms`) | Preserved for stability — the risky path was not disturbed |
| Schedule datetime default | Needs `new Date()` — inherently client-side |

## What stayed unchanged

The **send / note-creation flow is untouched** — the Send button still POSTs
`?call=sms` and renders the JSON result. Only the live preview/statusline and
supporting UI (toggles, registration, filters) moved to Datastar.

## See also

- `docs/docs/developer/sms/smsarchitecture.md` — the wider SMS subsystem.
- `docs/docs/developer/sms/engine/character-counting.mdx` — GSM/UCS-2 segment maths.
