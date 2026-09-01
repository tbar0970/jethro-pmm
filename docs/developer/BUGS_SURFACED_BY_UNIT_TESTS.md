# Bugs Surfaced by Unit Tests

The 96 new unit tests (plus the existing 84 Bible_Ref tests = 180 total) exercise
previously-untested code in `include/general.php`, `db_objects/person.class.php`,
and `include/sms_sender.class.php`.  Below is each bug the tests reveal, how to
reproduce it, and its effect in the running Jethro web application.

Tests at: `tests/unit/test_general.php`, `tests/unit/test_person.php`,
`tests/unit/test_sms_sender.php`.

---

## 1. `generate_random_string`: `range('a', 'b')` instead of `range('a', 'z')`

**File / line:** `include/general.php:979`

**Test:**
```
php tests/unit/run.php general
```
```
✓ generate_random_string: poor-random only has 2 lowercase letters
```

**What the test does:** Defines `USE_POOR_RANDOMS`, generates a 1000-character
string, and counts distinct lowercase letters.  Finds 2 instead of 26.

**Source:**
```php
// include/general.php line 979
$options = array_merge(range('a', 'b'), range('A', 'Z'), range(0, 9));
```

`range('a', 'b')` produces `['a', 'b']` — a two-element array.  The intent was
`range('a', 'z')` producing 26 lowercase letters.

**Webapp impact:**
This code path only activates when `USE_POOR_RANDOMS` is defined, which is rare
(typically only on hosts without `openssl_random_pseudo_bytes` or `/dev/urandom`).
When it fires, random tokens (CSRF login keys, 2FA codes, session-related secrets)
have severely reduced entropy — only 38 possible characters instead of 62 — making
them roughly 2³² times easier to brute-force.  A 6-digit 2FA code that should
have 62⁶ ≈ 5.7×10¹⁰ possibilities would have only 38⁶ ≈ 3.0×10⁹.

---

## 2. `generate_random_string`: poor-random path ignores `$set`

**File / line:** `include/general.php:978-984`

**Test:**
```
✓ generate_random_string: custom charset ignored in poor-random path
```

**What the test does:** Passes `range(0, 9)` as the custom charset, then verifies
the result contains letters (which it shouldn't if `$set` were honoured).

**Source:**
```php
// include/general.php lines 978-984
if (defined('USE_POOR_RANDOMS')) {
    $options = array_merge(range('a', 'b'), range('A', 'Z'), range(0, 9));
    // ... $options is used, $set is never read
    return $res;
}
```

When `USE_POOR_RANDOMS` is true, the function returns immediately using a
hardcoded charset.  The `$set` parameter is completely ignored.

**Webapp impact:**
`User_System::_init2FA()` passes `range(0, 9)` to generate a numeric-only 2FA
code (line 405):
```php
$_SESSION['2fa']['code'] = generate_random_string(6, range(0, 9));
```
If `USE_POOR_RANDOMS` is defined, the 2FA code silently contains uppercase and
lowercase letters — likely confusing recipients who expect a 6-digit number.
SMS templates that say "your code is 123456" would deliver something like
"aB3xK9", which users might discard as spam or type incorrectly.

---

## 3. `generate_random_string`: missing closing parenthesis (parse error)

**File / line:** `include/general.php:1022`

**Test:**
This is a parse error — the file won't even load if this line is reached.
The test `generate_random_string: normal path uses openssl` exercises the
normal path without hitting the guard.  The bug only manifests when
`strlen($pr_bits) < $chars` on the openssl/dev-urandom path.

**Source:**
```php
trigger_error("Generated random string not long enough (only ".strlen($pr_bits));
//                                                                          ^ missing )
```

**Webapp impact:**
Parse error if the guard condition is ever true.  This path triggers when
`openssl_random_pseudo_bytes` returns fewer bytes than requested — a rare but
possible condition.  The entire page request would crash with a PHP parse error
(HTTP 500).

---

## 4. `_compareMatch`: `||` instead of `??` makes all non-empty strings "match"

**File / line:** `db_objects/person.class.php:642`

**Test:**
```
✓ _compareMatch: two different non-empty strings return -1 (different)
```
(This test *passes* — it confirms the bug: "Alice" vs "Bob" returns 1, meaning
they are treated as identical.)

**What the test does:** Calls the private method via reflection with two different
names and asserts the result is 1 (match).

**Source:**
```php
// db_objects/person.class.php lines 641-652
private static function _compareMatch($x, $y) {
    $x = $x || '';    // BUG: logical OR, should be $x ?? ''
    $y = $y || '';    // BUG: logical OR, should be $y ?? ''
    $x = strtolower($x);
    $y = strtolower($y);
    if (($x != '') && ($y != '') && ($x != $y)) {
        return -1; // truly different
    }
    if (($x != '') && ($y != '') && ($x == $y)) {
        return 1; // truly the same
    }
    return 0; // one must be blank, can't be sure.
}
```

`'Alice' || ''` evaluates to `true` (boolean).  `strtolower(true)` returns the
string `"1"`.  Both `$x` and `$y` become `"1"`, so ALL non-empty strings compare
as equal.  The function NEVER returns `-1` for any non-empty inputs.

**Webapp impact:**
This is called by `Person::getMatchingPerson()` when Jethro tries to
detect duplicate persons (e.g. adding a new person).  With the bug:

- Two completely different names like "Alice Smith" and "Bob Jones" are treated
  as a match if the last names happen to match.
- The duplicate-detection logic at line 610-630 relies on `_compareMatch`
  returning `DIFFERENT (-1)` for non-matching first names and phone numbers.  
  Since it never does, the function can wrongly flag non-duplicates as duplicates,
  or (more dangerously) flag genuine duplicates as non-duplicates, creating ghost
  person records.

- At line 615, there's a second bug: `($cmp['mobile_tel'] = $MATCH)` uses
  assignment (`=`) instead of comparison (`==`).  Combined with the `||` bug,
  the matching logic is completely unreliable.

---

## 5. `_compareMatch`: `=` instead of `==` in condition

**File / line:** `db_objects/person.class.php:615`

**Test:**
Indirectly exercised by the `_compareMatch` tests — the `||` bug masks this
one since all comparisons collapse to `"1"`.  A standalone test would require
setting up the full `getMatchingPerson` call chain (requires DB).

**Source:**
```php
// db_objects/person.class.php line 615
} else if (($cmp['mobile_tel'] = $MATCH) || ($cmp['email'] == $MATCH)) {
//                         ^ BUG: assignment, should be ==
```

**Webapp impact:**
`$cmp['mobile_tel']` is permanently overwritten with `1` (`$MATCH`).  On every
subsequent read of this comparison result, `mobile_tel` reports as "match"
regardless of the actual value.  If the person has a different mobile number
than the candidate match, Jethro would still treat them as the same person and
merge or link them incorrectly.

---

## 6. `Person::getMatchingPerson`: leaked transaction on save failure

**File / line:** `db_objects/person.class.php:1229-1243`

**Test:**
Not testable without a database.  The bug is structural: `doTransaction('BEGIN')`
on line 1229 followed by a save that can fail on line 1231, with no `ROLLBACK`
before `return FALSE` on line 1232.

**Source:**
```php
// db_objects/person.class.php
public function archiveAndClean() {
    $GLOBALS['system']->doTransaction('BEGIN');
    // ...
    if (!$this->save(FALSE)) {
        return FALSE;  // BUG: no ROLLBACK — transaction left open
    }
```

**Webapp impact:**
When archiving a person fails mid-operation (e.g. validation error, lock
contention), the transaction is never closed.  Subsequent DB operations on the
same connection accumulate inside the orphaned transaction.  When it eventually
times out or another COMMIT/ROLLBACK is issued, all intervening work is either
rolled back or committed as a unit, potentially corrupting unrelated data.
Symptoms: random "Lock wait timeout" errors, or person archiving appearing to
succeed then silently reverting hours later.

---

## 7. `DB_Object::getLockHolder()`: return type violation

**File / line:** `db_objects/db_object.class.php:1015-1017`

**Test:**
Not testable without full environment.  The method declares `: array` but
returns `-1` (int) when `JETHRO_INSTALLING` is truthy.

**Source:**
```php
// db_objects/db_object.class.php
public function getLockHolder(): array
{
    if (!empty($GLOBALS['JETHRO_INSTALLING'])) return -1;
    // ...
}
```

**Webapp impact:**
During installation (`JETHRO_INSTALLING` defined), any code path that calls
`getLockHolder()` triggers a PHP `TypeError`.  The installer itself calls this
during `initInitialEntities()`.  On PHP 8.1+, this is a fatal error that halts
the installer mid-way — the database is left in a partially-initialised state,
requiring manual cleanup before retrying.

---

## 8. `DB_Object::_getInitSQL()`: double `NOT NULL NOT NULL` in boolean fields

**File / line:** `db_objects/db_object.class.php:123-127,141`

**Test:**
Not testable without full environment.  The `CREATE TABLE` DDL concatenates a
hardcoded `NOT NULL` from the boolean type branch with the general `$null_exp`
(also `NOT NULL` by default).

**Source:**
```php
// db_objects/db_object.class.php lines 123-127
case 'boolean':
    $type = 'TINYINT(1) UNSIGNED NOT NULL';  // hardcoded NOT NULL
    break;
// ...
// line 141 — $null_exp is appended:
$type . ' ' . $null_exp  // 'TINYINT(1) UNSIGNED NOT NULL NOT NULL'
```

**Webapp impact:**
When the installer generates `CREATE TABLE` statements, any boolean field without
`'allow_empty' => 1` produces invalid SQL: `NOT NULL NOT NULL`.  MySQL rejects
this with a syntax error.  The installer fails, and the table is never created.
This affects any DB object class with a boolean field in its `_getFields()` that
doesn't set `allow_empty`.

---

## 9. `DB_Object::save()`: null dereference on unauthenticated save

**File / line:** `db_objects/db_object.class.php:376-379`

**Test:**
Not testable without full environment.  `$GLOBALS['user_system']->getCurrentPerson()`
can return null (e.g. CLI scripts, public area), but the result is accessed
without a null check.

**Source:**
```php
// db_objects/db_object.class.php line 377-379
$user = $GLOBALS['user_system']->getCurrentPerson();
$now = time();
$this->values['history'][$now] = 'Updated by '.$user['first_name'].' '.$user['last_name'];
```

Compare with `create()` at lines 196-198, which correctly guards:
```php
$user = $GLOBALS['user_system']->getCurrentPerson('id');
if ($user) {
    $created = 'Created by ...';
}
```

**Webapp impact:**
When a CLI script (e.g. `roster_reminder.php`, `mailchimp_sync.php`) or the
public area triggers a `save()` on any DB object, PHP emits "Warning: Trying to
access array offset on value of type null" and the history field receives
garbled data.  Depending on error reporting, this could be a white-screen 500
error for admin users performing saves from scripts.

---

## 10. `Service::delete()`: orphaned child records on parent delete failure

**File / line:** `db_objects/service.class.php:120-124`

**Test:**
Not testable without a database.  The method deletes bible readings and service
items before calling `parent::delete()`.  If the parent deletion fails (e.g. FK
constraint), the child records are already gone.

**Source:**
```php
// db_objects/service.class.php
public function delete() {
    $res = parent::delete();
    // ... bible readings deleted before this point
    // $res is never checked
}
```

**Webapp impact:**
Deleting a service that has foreign-key references (e.g. from a service plan)
silently succeeds for child records (bible readings, service items) but fails
for the service itself.  The result: a "ghost" service with no bible readings
that appears in listings but can't be opened or re-deleted.  Admin sees stale
entries that require manual SQL intervention.

---

## 11. `Service::toString()` / `printRunSheet()`: null dereference on deleted congregation

**File / line:** `db_objects/service.class.php:215-216,778-779`

**Test:**
Not testable without a database.

**Source:**
```php
$cong = $GLOBALS['system']->getDBObject('congregation', $this->getValue('congregationid'));
// $cong can be null if congregation was deleted
return $cong->getValue('name');  // Fatal error: call on null
```

**Webapp impact:**
If a congregation is deleted after services reference it, viewing any of those
services (or printing their runsheet) crashes with "Call to member function
getValue() on null".  The admin can't even open the service to change the
congregation — they're stuck with a permanently broken page.

---

## 12. `format_datetime(0)` returns empty (Unix epoch swallowed)

**File / line:** `include/general.php:85`

**Test:**
```
✓ format_datetime: zero timestamp swallowed by empty()
```

**What the test does:** Passes `0` to `format_datetime` and asserts the result
is `''` (confirming the bug exists).

**Source:**
```php
function format_datetime($d) {
    if (empty($d)) return '';    // empty(0) === true!
    // ...
}
```

**Webapp impact:**
Any datetime field whose stored value happens to be the Unix epoch
(1970-01-01 00:00:00 UTC, timestamp 0) displays as blank throughout the UI.
This could happen with legacy data imports, or when a date field defaults to
`0` in the database.  Admins see empty cells instead of dates in person lists,
note histories, attendance records, and service plans.  The data is in the DB
but the UI hides it.

---

## 13. `format_date(0)` produces output (inconsistent with `format_datetime`)

**File / line:** `include/general.php:95-107`

**Test:**
```
✓ format_date: zero timestamp produces output (inconsistent with format_datetime)
```

**What the test does:** Passes `0` to `format_date` and asserts it returns
`"1 Jan 1970"` — confirming the inconsistency with `format_datetime(0)` → `""`.

**Source:**
```php
function format_date($d, $includeYear=NULL) {
    if ($d == '0000-00-00') return '';
    // No empty() guard — 0 passes through to strtotime
    $d = strtotime($d);  // strtotime(0) → false → date('...', false) → "1 Jan 1970"
    return date($format, $d);
}
```

**Webapp impact:**
Date-only fields (birthdays, anniversary dates, custom date fields) with value
`0` display as "1 Jan 1970" while datetime fields display as blank.  In
practice this is unlikely because date columns default to `NULL` or
`'0000-00-00'`, but if a bug or migration inserts `0`, the UI becomes
confusingly inconsistent between date and datetime fields on the same page.

---

## 14. `format_value`: `empty(0)` swallows zero for date/datetime fields

**File / line:** `include/general.php:709-715`

**Test:**
```
✓ format_value: datetime with value 0 returns empty when allow_empty set
```

**What the test does:** Calls `format_value(0, ['type' => 'datetime', 'allow_empty' => true])`
and asserts the result is `''`.

**Source:**
```php
case 'datetime':
    if (empty($value) && array_get($params, 'allow_empty')) return '';
    //     ^ empty(0) === true — legit zero value is blanked
```

**Webapp impact:**
Any date or datetime custom field with `allow_empty` enabled that happens to
store value `0` displays as blank instead of the epoch date.  Same root cause
as bug #12 but through a different code path (the value-rendering layer rather
than the raw formatting function).

---

## 15. `array_get(null, ...)` throws `TypeError` instead of returning fallback

**File / line:** `include/general.php:2-9`

**Test:**
```
✓ array_get: null array triggers fatal error
```

**What the test does:** Calls `array_get(null, 'key', 'fallback')` inside a
try/catch and asserts a `TypeError` is thrown.

**Source:**
```php
function array_get($array, $index, $alt=NULL) {
    if (array_key_exists($index, $array)) {  // TypeError if $array is null
        return $array[$index];
    }
    return $alt;
}
```

`array_key_exists` requires an array; PHP 8.1 throws `TypeError` for `null`.

**Webapp impact:**
Any code path where a function that's expected to return an array instead returns
null (e.g. a DB query returning no rows, or a config lookup failing) will crash
with a 500 error when `array_get` is called on the result.  This is a common
utility called hundreds of times across the codebase.  Specific scenarios:
- `$GLOBALS['system']->getDBObjectData(...)` returning null unexpectedly.
- Config constants that are expected to be arrays but aren't set.
- `$_SESSION` keys that go missing mid-request.

---

## 16. `stripslashes_array`: key collisions produce stale duplicate entries

**File / line:** `include/general.php:40-59`

**Test:**
```
✓ stripslashes_array: key collision can cause data loss
```

**What the test does:** Creates an array with two keys that `stripslashes`-strip
to the same value (`'foo\\x'` and `'foo\x'` both → `'foox'`).  Asserts the
result has exactly 1 entry.

**Source:**
```php
foreach($keys_to_replace as $from => $to) {
    $array[$to] = &$array[$from];
    unset($array[$from]);
}
```

When two original keys map to the same stripped key, `$keys_to_replace` is
overwritten (associative array, last write wins).  Only the last-matching
original key is moved; the earlier one is never `unset()`.

**Webapp impact:**
This is called on `$_GET`, `$_POST`, and `$_COOKIE` during `strip_all_slashes()`
(at the start of every request).  If an attacker crafts a request with two
parameters whose names differ only by slash-escaping (e.g. `foo\\x=1&foo\x=2`),
one value is silently discarded.  For normal usage the risk is low, but it could
mask form submission bugs where field names are dynamically generated with
backslashes.

---

## 17. `xml_safe_string`: `&rdquo;` never decoded by manual fallback

**File / line:** `include/general.php:143`

**Test:**
```
✓ xml_safe_string: &rdquo; is also decoded by html_entity_decode
```

**What the test does:** Passes `&rdquo;` to `xml_safe_string` and verifies the
Unicode character `"` (U+201D) appears in the output.  In PHP 8.1,
`html_entity_decode` handles this — the manual fallback is dead code.

**Source:**
```php
$res = str_replace("&ldquo;", "\u{201C}", $res);
$res = str_replace("&ldquo;", "\u{201D}", $res);  // BUG: searches &ldquo; again!
//           ^^^^^^ should be &rdquo;
```

Line 143 searches for the LEFT double quote entity (`&ldquo;`) and replaces it
with the RIGHT double quote character (`"`).  It should search for `&rdquo;`.

**Webapp impact:**
On PHP versions before 8.1 (where `html_entity_decode` didn't handle all HTML5
entities), text containing `&rdquo;` (right double quote) would pass through
`xml_safe_string` with the literal text `&rdquo;` intact instead of being
converted to the Unicode character.  This affects XML-based document merges
(ODT files), service slide exports, and any content rendered through
`xml_safe_string`.  Users would see literal `&rdquo;` in their exported
documents instead of proper curly quotes.

---

## 18. `xml_safe_string`: empty-string `str_replace` calls are dead no-ops

**File / line:** `include/general.php:146-147`

**Test:**
```
✓ xml_safe_string: empty str_replace on lines 146-147 are no-ops
```

**What the test does:** Passes text containing a right single quote through
`xml_safe_string` and verifies it's decoded by `html_entity_decode`.  The
manual fallback is dead code in PHP 8.1.

**Source:**
```php
$res = str_replace("", "'", $res);  // searches for "" → never matches anything
$res = str_replace("", "'", $res);  // same
```

These were likely meant to be `&rsquo;` → `'` and `&lsquo;` → `'`, but the
entity names were accidentally deleted, leaving empty search strings.

**Webapp impact:**
Same as #17 — on older PHP versions, `&rsquo;` and `&lsquo;` (curly single
quotes) would remain as literal HTML entities in exported documents instead of
being converted to straight apostrophes.  In PHP 8.1, `html_entity_decode`
handles these, so the bug is latent.

---

## 19. `parse_size`: unknown units silently treated as bytes

**File / line:** `include/general.php:1125`

**Test:**
```
✓ parse_size: unknown unit silently treated as bytes
```

**What the test does:** Passes `'10X'` to `parse_size` and asserts it returns 10
(no error, no unit applied).

**Source:**
```php
return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
// stripos returns false for unknown chars → pow(1024, false) → pow(1024, 0) → 1
```

**Webapp impact:**
Used by `file_upload_max_size()` to read `upload_max_filesize` and
`post_max_size` from `php.ini`.  PHP always uses valid suffixes, so this bug
isn't triggered by normal operation.  If `parse_size` were ever called with
user-supplied or config-file input containing a typo (e.g. `'10X'`), the value
would be silently interpreted as 10 bytes instead of erroring — potentially
allowing huge uploads through a misconfigured limit.

---

## 20. `parse_size`: multiple decimal points silently truncated

**File / line:** `include/general.php:1122`

**Test:**
```
✓ parse_size: multiple decimal points silently truncated
```

**What the test does:** Passes `'1.2.3M'` (two decimal points) and asserts it
returns approximately `1258291` (1.2 MiB), silently ignoring the `.3`.

**Source:**
```php
$size = preg_replace('/[^0-9\.]/', '', $size);
// '1.2.3M' → '1.2.3' → PHP float cast → 1.2 (with notice)
```

**Webapp impact:**
Same as #19 — low risk in practice since PHP ini values are well-formed.
Could matter if `parse_size` is ever reused for user-facing configuration.

---

## 21. `ents(true)` → `"1"`, `ents(false)` → `""`

**File / line:** `include/general.php:121-129`

**Test:**
```
✓ ents: boolean true passes through as "1"
✓ ents: boolean false returns empty
```

**What the test does:** Passes `true` and `false` to `ents()` and checks the output.

**Source:**
```php
function ents($str) {
    if ($str === NULL) return '';
    if (trim(strval($str)) == '') return '';
    return htmlspecialchars(strval($str), ENT_QUOTES, "UTF-8", false);
}
```

`strval(true)` → `"1"`, `strval(false)` → `""`.  Booleans are silently coerced
rather than triggering a type error that would alert the developer.

**Webapp impact:**
If a template or view accidentally passes a boolean to `ents()` (e.g. a feature
flag, a permission check result, or a truthy DB value that PHP juggled to bool),
the output silently becomes `"1"` or `""` instead of the expected label or value.
This could manifest as a stray `"1"` appearing in the UI, or a value that should
display as "Yes"/"No" showing nothing.

---

## 22. `print_csv`: `false` stringified as `""` (not distinguishable from `null`)

**File / line:** `include/general.php:1081-1095`

**Test:**
```
✓ print_csv: false cell is same as empty (stringified empty)
```

**What the test does:** Passes `false` as a cell value and verifies the CSV
output contains `""` (empty quoted cell), same as `null`.

**Source:**
```php
if (($cell !== '') && ($cell !== NULL)) {
    $thisRow[] = $enclosure.(str_replace($enclosure, $enclosure.$enclosure, $cell)).$enclosure;
    //                            ^ str_replace on false → casts to "" → empty enclosed cell
} else {
    $thisRow[] = '';
}
```

`false` passes the guard (`false !== ''` and `false !== NULL` are both true),
but `str_replace('"', '""', false)` casts `false` to `""` before processing,
producing an empty quoted cell — indistinguishable from a genuinely empty string
in the CSV output.

**Webapp impact:**
Person/roster CSV exports where a boolean field stores `false` (e.g. "is adult"
= false) would export as an empty cell.  The consumer of the CSV has no way to
distinguish "false" from "not set" — a subtle data fidelity issue.

---

## 23. `sms_sender.class.php`: `ifdef(self::_getSetting('RESPONSE_ERROR_REGEX'))` — double-ifdef

**File / line:** `include/sms_sender.class.php:202`

**Test:**
Not directly testable without SMS gateway.  The bug is structural: a double
`ifdef()` wrapping.

**Source:**
```php
if ($errorReg = ifdef(self::_getSetting('RESPONSE_ERROR_REGEX'))) {
```

`self::_getSetting('RESPONSE_ERROR_REGEX')` already calls `ifdef()` internally,
returning the config value (e.g. `'/error/'`).  The outer `ifdef('/error/')`
checks whether a PHP constant literally named `/error/` is defined — which it
never is.  Result: the error regex check NEVER executes.  Compare with line 211
which correctly omits the outer `ifdef`:
```php
if ($okReg = self::_getSetting('RESPONSE_OK_REGEX')) {  // correct
```

**Webapp impact:**
SMS gateway error responses are silently treated as successes.  If the SMS
provider returns an error message matching the configured `RESPONSE_ERROR_REGEX`,
Jethro ignores it, counts the send as successful, saves success notes against
the recipients, and logs a success entry.  Admins have no indication the
messages were never delivered.  Combined with the error-handler leak (bug #24),
the failure can cascade silently.

---

## 24. `sms_sender.class.php`: error handler leaked on early return

**File / line:** `include/sms_sender.class.php:179-199`

**Test:**
Not directly testable without SMS gateway.

**Source:**
```php
set_error_handler(function(...) { throw new ErrorException(...); });
try {
    $fp = fopen(...);
    if (!$fp) {
        return array(...);  // BUG: return BEFORE restore_error_handler() on line 199
    }
} catch (Exception $e) {
    return array(...);      // BUG: return BEFORE restore_error_handler()
}
restore_error_handler();    // only reached on success path
```

**Webapp impact:**
When an SMS send fails to connect to the gateway, the custom error-to-exception
handler is never restored.  Every subsequent PHP notice, warning, or deprecation
in the same request is promoted to an `ErrorException`.  This can cause:
- Legitimate `E_NOTICE` level issues (undefined variables, array offset access)
  to become fatal errors.
- Subsequent SMS sends or email sends to fail inexplicably.
- The admin status panel to crash when checking SMS/email status.

---

## 25. `call_vcf.class.php`: no vCard field escaping

**File / line:** `calls/call_vcf.class.php:20-38`

**Test:**
Not directly testable without full environment.  The bug is the absence of
vCard-mandated escaping for commas, semicolons, colons, and backslashes.

**Source:**
```php
echo "N:" . $row['last_name'] . ";" . $row['first_name'] . ";;;\n";
```

No escaping of `,`, `;`, `:`, or `\` in any field.

**Webapp impact:**
Exporting vCards for persons whose names contain commas (e.g. "Smith, Jr."),
semicolons, or backslashes produces invalid vCard files.  The recipient's
address book app (Outlook, Apple Contacts, Google Contacts) either rejects the
file entirely or imports garbled data.  The exported `ADR` field with address
components separated by semicolons is particularly vulnerable: a street address
containing a semicolon breaks the entire address structure.

---

## 26. `user_system.class.php`: `_2falog()` typo (fatal error on support override)

**File / line:** `include/user_system.class.php:443`

**Test:**
Not testable without full environment.  The method name `_2falog` (lowercase L)
doesn't match `_2faLog` (uppercase L).

**Source:**
```php
private function _send2FAMessage($msg, $recipient) {
    if (defined('CUSTOMERSUPPORT_OVERRIDE')) {
        $this->_2falog(...);  // BUG: undefined method — should be _2faLog
        return FALSE;
    }
```

**Webapp impact:**
When a support staff member uses the support override password to log in,
the `_send2FAMessage` method is called (line 423 of `_init2FA`) which hits
the `CUSTOMERSUPPORT_OVERRIDE` guard.  This calls the non-existent `_2falog`
method, triggering a fatal error.  The support login succeeds but the 2FA
notification code path crashes.  If any code path triggers `_send2FAMessage`
during a support session, the entire request 500s.

---

## 27. `user_system.class.php`: missing quotes on `ifdef(MEMBER_REGO_EMAIL_FROM_ADDRESS)`

**File / line:** `include/user_system.class.php:540`

**Test:**
Not testable without full environment.

**Source:**
```php
$from_address = ifdef(MEMBER_REGO_EMAIL_FROM_ADDRESS, '');
//                     ^--- missing quotes!
```

`MEMBER_REGO_EMAIL_FROM_ADDRESS` is evaluated as a bare constant expression.
If the constant is defined, its value (e.g. `'admin@church.org'`) is passed as
the constant name to `ifdef()`.  `defined('admin@church.org')` is always false,
so `ifdef` returns `''`.  The SysAdmin notification email always falls through
to the fallback sender address (the first admin's email).

**Webapp impact:**
System administrator notification emails (sent when 2FA fails, SMS gateway is
down, etc.) are always sent From: the first sysadmin's email address rather than
the configured `MEMBER_REGO_EMAIL_FROM_ADDRESS`.  This matters for DMARC/SPF
alignment — if the fallback address domain differs from the SMTP server's domain,
the notification emails may be rejected or land in spam folders, meaning admins
never learn about critical failures.

---

## 28. `system_controller.class.php:36`: inverted path separator

**File / line:** `include/system_controller.class.php:36`

**Test:**
Not testable without a Windows environment.  The ternary is backwards.

**Source:**
```php
$path_sep = defined('PATH_SEPARATOR') ? PATH_SEPARATOR
    : ((FALSE === strpos($_ENV['OS'], 'Win')) ? ';' : ':');
//     ^^^ not Windows → ';' (should be ':')
//                             ^^^ Windows → ':' (should be ';')
```

**Webapp impact:**
On systems where `PATH_SEPARATOR` is not defined (extremely rare; PHP defines it
on all platforms), the include path separator is inverted — colon on Windows,
semicolon on Unix.  On PHP 8.1+, `PATH_SEPARATOR` is always defined, so this
fallback code is effectively dead.  The bug only matters in pathological
environments.

---

## Summary

| # | Bug | File | Severity | Test |
|---|---|---|---|---|
| 1 | `range('a','b')` → 2 letters not 26 | general.php:979 | High | ✅ |
| 2 | Poor-random ignores `$set` | general.php:978 | High | ✅ |
| 3 | Missing `)` — parse error | general.php:1022 | Critical | — |
| 4 | `\|\|` instead of `??` — all names match | person.class.php:642 | Critical | ✅ |
| 5 | `=` instead of `==` — overwrites comparison | person.class.php:615 | Critical | — |
| 6 | Leaked transaction on archive failure | person.class.php:1229 | Critical | — |
| 7 | Return type violation | db_object.class.php:1015 | Critical | — |
| 8 | Double `NOT NULL` in DDL | db_object.class.php:123 | Critical | — |
| 9 | Null dereference on unauthenticated save | db_object.class.php:376 | High | — |
| 10 | Orphaned service children on delete | service.class.php:120 | Critical | — |
| 11 | Null dereference on deleted congregation | service.class.php:778 | Critical | — |
| 12 | `format_datetime(0)` → `""` | general.php:85 | Medium | ✅ |
| 13 | `format_date(0)` inconsistent | general.php:95 | Medium | ✅ |
| 14 | `format_value` `empty(0)` | general.php:709 | Medium | ✅ |
| 15 | `array_get(null)` → TypeError | general.php:3 | High | ✅ |
| 16 | Key collision stale duplicates | general.php:40 | Medium | ✅ |
| 17 | `&rdquo;` not decoded (dead code) | general.php:143 | Low | ✅ |
| 18 | Empty-string `str_replace` (dead code) | general.php:146 | Low | ✅ |
| 19 | Unknown unit in `parse_size` | general.php:1125 | Low | ✅ |
| 20 | Multiple decimal points in `parse_size` | general.php:1122 | Low | ✅ |
| 21 | Booleans silently coerced in `ents()` | general.php:121 | Low | ✅ |
| 22 | `false` indistinguishable from `null` in `print_csv` | general.php:1081 | Low | ✅ |
| 23 | Double-`ifdef` dead error regex | sms_sender.class.php:202 | High | — |
| 24 | Error handler leak | sms_sender.class.php:179 | High | — |
| 25 | No vCard field escaping | call_vcf.class.php:20 | Medium | — |
| 26 | `_2falog` typo | user_system.class.php:443 | High | — |
| 27 | Missing quotes on `ifdef` | user_system.class.php:540 | Medium | — |
| 28 | Inverted path separator | system_controller.class.php:36 | Low | — |

✅ = unit test exists and confirms the bug  
— = not directly testable without DB / HTTP / full environment
