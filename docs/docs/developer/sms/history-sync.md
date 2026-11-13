---
sidebar_position: 11
---

# SMS History Synchronisation

`\Jethro\Sms\synchronizeHistory()` (`include/jethro_sms.php`) imports SMS
history from the upstream provider into the local `sms` + `smsdelivery`
tables.  It is invoked from the admin status panel ("Synchronize History"
operation) and `scripts/sms.php sync-history`.  `checkSynchronized()` is the read-only diff companion.

## Design: stage, refine, compare, insert

Upstream deliveries (`$provider->listRecentDeliveries($since)`) are staged
verbatim into two scratch tables, then progressively refined with SQL
until they can be compared against the real tables to identify duplicates.
Nothing touches `sms`/`smsdelivery` until the final step.

Staging tables:

| Table | Mirrors | Extra working columns |
|-------|---------|-----------------------|
| `smsdelivery_new` | `smsdelivery` | `phone_key` (last 9 digits), `grp` (session number), `batch_id` (→ `sms_new.id`), `sms_id` (resolved target) |
| `sms_new` | `sms` | `grp`, `sms_id` (matching existing `sms.id`, if any) |

Pipeline:

1. **Stage** the feed verbatim (multi-row inserts; `phone_key` = the
   recipient's last 9 digits, as compared by `PhoneNumber::sameNumberAs()`).
2. **Group into batches**: gap-based sessions per message body — a >1-hour
   silence in same-body deliveries starts a new batch (window functions:
   `LAG` + running `SUM`).  One `sms_new` row per (body, session); batch
   `created` = earliest send time.
3. **Collapse the feed** to one record per delivery: `ROW_NUMBER()` per
   `remote_id`, and per (batch, recipient phone).  Handles pagination
   overlap and multiple report entries per recipient (multipart segments,
   'sent' followed by 'delivered').  The survivor is the most final status,
   preferring records with a delivery time.
4. **Resolve `personid`** from `phone_key` against `_person.mobile_tel`
   (both sides reduced to their last 9 digits in SQL, so '0401…' and
   '61401…' match without any prefix settings).
5. **Match batches** to existing `sms` rows: same body, `created` within
   ±10 minutes (closest wins).  Existing rows may predate `remote_id`
   logging, so body+time is the only batch identity available.
6. **Delete staged duplicates** of existing `smsdelivery` rows under the
   matched batch: by `remote_id` when both sides have one, else by
   recipient phone (via `personid` → `_person.mobile_tel`, or the
   `raw_response` JSON `$.destination`).
7. **Insert**: new `sms` rows for unmatched batches that still have
   deliveries, then one bulk `INSERT … SELECT` of the surviving staged
   deliveries.  New deliveries for a partially-imported batch attach to
   the existing `sms` row.

Returns `{deleted, imported, batches, skipped}`: staged rows discarded as
local duplicates; `smsdelivery` rows inserted; `sms` rows created; upstream
batches already fully present.

## History depth

Providers only retain a rolling window of history. The status panel's
"Synchronize History" form defaults "From" to now − 24 hours (blank also
means the last 24 hours). 5CentSMS v5
(`GET /api/v5/sms`, cursor-paged ~100/page) returned nothing older than ~356
days when checked against a live account in 2026-10, even though that account
had sent messages since 2016. An earlier `since` doesn't error; the feed just
ends. Neither the form nor `scripts/sms.php sync-history` limits how far
back `since` may go.

## Portability notes

The SQL is deliberately portable to stock MySQL 8+ as well as MariaDB
(window functions, `REGEXP_REPLACE`, `FIELD`, `GET_LOCK`, `JSON_VALID`).
Consequences of that choice:

- **The scratch tables are real tables, not `TEMPORARY`** — MySQL cannot
  reference a temporary table more than once in a query, and the
  refinement steps self-join and `UPDATE … JOIN (SELECT … FROM
  same_table)` the staging tables.  They are dropped in a `finally`.
- **Concurrent syncs are serialised** with
  `GET_LOCK(CONCAT(DATABASE(), '.sms_sync'), 5)` since the scratch tables
  are shared; a second concurrent run throws.
- **`CREATE`/`DROP TABLE` implicitly commits**, so all DDL runs before
  the function opens its own transaction (only when none is active,
  `PDO::inTransaction()`), and callers must not invoke it inside a
  transaction whose atomicity they need.
- **`JSON_EXTRACT` on the `raw_response` destination is guarded by
  `JSON_VALID`** — MySQL errors on invalid JSON where MariaDB returns
  NULL, and legacy `raw_response` values may not be JSON.
- **Collations vary across instances** (`sms.body` is
  `utf8mb4_unicode_ci`; other columns commonly `utf8mb4_uca1400_ai_ci` or
  `utf8mb4_general_ci`).  Staging tables are created
  `utf8mb4_unicode_ci`, and every cross-table string comparison carries an
  explicit `COLLATE utf8mb4_unicode_ci` (or `CONVERT … USING utf8mb4` for
  computed phone expressions) so it never depends on the instance's table
  collations.

## Tests

`tests/sms/bridge/test_synchronize_history_dedup.php` — runs against the
real DB with a fake provider injected into the `getSmsProvider()` memo.
Because the function's DDL implicitly commits, the tests cannot run in a
rolled-back transaction; rows use unique bodies and are deleted by a
`register_shutdown_function` (the `test()` helper only registers —
`run_all()` executes later, so file-end cleanup code would fire too
early).  The bootstrap sets `$_SERVER['HTTP_HOST']` from the instance
directory name so `conf.php` routes to the right account from the CLI.
