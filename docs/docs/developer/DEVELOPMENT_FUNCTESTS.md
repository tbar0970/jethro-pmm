---
sidebar_position: 5
---

# Functional Test Harness (Playwright)

Functional tests (`tests/functional/`) run against **one shared Jethro instance** —
one nginx, one PHP-FPM pool, one MariaDB — but each Playwright *project* (test
scenario) gets what looks like its own dedicated Jethro install, addressed by a
distinct URL prefix. There's no per-scenario container or process; the illusion
is created entirely by a URL-prefix convention plus a small piece of routing
logic in the test webroot's `conf.php`. This doc explains that mechanism so it
doesn't have to be re-derived from scratch.

## Why

SMS scenarios in particular need different `SMS_PROVIDER` / `SMS_*_URL` /
feature-flag constants per test (different providers, cooloff windows, sender
ID configs, forced failures, etc). Spinning up a separate PHP-FPM + nginx per
scenario would be slow and heavy; instead every scenario shares the same
backend and database, and gets its *own config* purely by virtue of the URL
it's addressed at.

## Moving parts

### 1. `process-compose.yml` — `functest_jethro_server` service

Starts one nginx (from `devbox.d/nginx/nginx.template`) + the normal shared
PHP-FPM pool, rooted at `devbox.d/functest_jethro_server` instead of the
regular dev webroot (`devbox.d/web`). `FUNCTEST_WEB_PORT` resolves `_default_`
through the devbox.json init hook, or uses an explicit literal port override.
See `reference/JETHRO_UTILS.md`. Depends on `functest_databases_setup`, which runs
`jethro_load_demodata jethro_functest` — recreating the `jethro_functest`
database and filling it with the demo data dump, the post-2.38 upgrade
scripts and the fake SMS history (`tests/functional/testdata/fakesmses.sql.gz`)
— **all scenarios share this one database**, so
specs must scope assertions to data unique to their own run (e.g. a
timestamped message body — see `sms-send-single.spec.ts`) rather than
asserting "the most recent X", since other scenarios' tests may run
concurrently (`fullyParallel: true`, 4 workers).

The same service recreates two more empty databases:
`jethro_functest_walkthrough` (the setup-wizard test starts from nothing) and
`jethro_functest_smsmockserver`, the database backing the mock SMS server
(below), whose schema is created on first request by `Store::migrate()`.

`functest_databases_setup` is the single definition of "fresh test data", and
it runs before **every suite run**, not just at `devbox services up`:
`tests/functional/global-setup.ts` calls
`./devbox services restart functest_databases_setup` (this checkout's
launcher) and waits for the job's new PID to report `Completed` with exit code
0. The restart returns before the job finishes, and the HEALTH column keeps the
previous run's `Ready`, so it polls `devbox services ls` (which prints to
stderr). Edits to the job's `command:` in `process-compose.yml` only take
effect after `devbox services stop && devbox services up -b`: a process
restart reuses the configuration process-compose loaded at startup.

### 2. `devbox.d/functest_jethro_server/` — the test webroot

A directory of symlinks back into the real source tree (`include`, `views`,
`db_objects`, `calls`, `index.php`, etc.) plus **its own standalone
`conf.php`** — this is a different `conf.php` from the multi-tenant
hosting one at the repo root (which does per-account lookup under
`/home/jethro/accounts/`). The functest one is single-tenant and contains the
scenario-routing logic described next.

### 3. Scenario detection — `devbox.d/functest_jethro_server/conf.php`

```php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// normalize .../index.php and missing trailing slash to the same form
if ($path !== '/') {
    $testConf = realpath(JETHRO_ROOT . '/../../' . rtrim($path, '/') . '.conf');
    if ($testConf) {
        require_once $testConf;
        if (!defined('BASE_URL')) define('BASE_URL', $path);
    }
}
```

A request to `/tests/functional/sms/sms-bulk/?view=...` resolves
`$path` to `/tests/functional/sms/sms-bulk/`, which maps to the sibling file
`tests/functional/sms/sms-bulk.conf` (note: a `.conf` **file**, not a
directory — `rtrim` + `.conf` turns the trailing-slash path straight into the
sibling filename). That file is `require_once`'d, so it can `define()`
whatever provider/feature constants the scenario needs. `BASE_URL` is set to
the scenario's path prefix, so every generated URL (`build_url()`, redirects,
resource links) stays under `/tests/functional/sms/sms-bulk/` — this is what
makes the scenario behave like its own isolated instance from the browser's
point of view. Requests to the bare root (`/`, used by the plain `login`
project) simply don't match any `.conf` file and fall through unmodified.

### 4. nginx routing — `devbox.d/nginx/nginx.template`

The `location ^~ /tests/functional/` block (see the file for full comments):
- Rewrites asset requests (`resources/*`, `favicon.ico`) to strip the
  scenario prefix, so static files resolve on disk under the real
  `/resources/`.
- Everything else gets an internal `rewrite ^ /index.php last;`. This
  changes `$uri`/`SCRIPT_NAME` to `/index.php`, but **`$_SERVER['REQUEST_URI']`
  is untouched** (nginx exposes the original request line via `$request_uri`
  regardless of internal rewrites) — that preserved value is what `conf.php`
  above parses to find the scenario.

The comment block in that file explicitly says it "mirrors the Caddyfile
logic" — the project used to run functional tests under FrankenPHP/Caddy;
that's been replaced by this nginx+php-fpm setup. If you find older docs or
memory referencing FrankenPHP, `router.php`, or a `jethro-test-debug`
process-compose service for functional tests, treat those as stale — none of
that exists in the current tree.

### 5. Per-scenario `.conf` files — `tests/functional/sms/*.conf`

One `<scenario-name>.conf` per Playwright project, `require_once`'d from
`common.conf` for the shared bits (SMS local/international prefix, verbose
logging), then defining whatever's scenario-specific — e.g.
`sms-bulk.conf` sets `SMS_PROVIDER=cellcast` and points `SMS_CELLCAST_URL`
at the mock proxy (below). `common.conf` deliberately avoids hardcoding
`ENABLED_FEATURES`: doing so would silently disable every other feature for
the whole test run instead of just narrowing SMS-testing scope — let it come
from the `setting` table like a real instance would.

### 6. `playwright.config.ts` — wiring scenarios to Playwright projects

`SMS_SCENARIOS` is a plain array of scenario names; each becomes a Playwright
project whose `baseURL` is `${HOST}/tests/functional/sms/{name}/` and whose
`testMatch` is `sms/{name}.spec.ts`. **The array, the `.conf` file, and the
`.spec.ts` file must all agree on the same name** — there's no dynamic
discovery. Adding a new SMS scenario means adding to all three.

**Provider matrix.** The scenarios in `PROVIDER_MATRIX` — the ones where
provider differences reach the UI (schedule/cancel, cool-off, status
lifecycle, send failure, opt-outs, sender registration, history) — run once
per provider: on Cellcast as `<name>`, and once per `MATRIX_PROVIDERS` entry
(`v5` for 5CentSMS v5, `mobilemessage` for MobileMessage) as a
`<name>-<suffix>` project that runs the **same** `.spec.ts` under
`/tests/functional/sms/<name>-<suffix>/`, so it loads `<name>-<suffix>.conf`
and the `<name>-<suffix>` mock profile. Every matrix project sets
`metadata.smsProvider`, and the specs stay provider-neutral through
`tests/functional/sms/scenario.ts`: `mockProfile(test.info())` for
`mockMeta()`, and `sentSms(test.info(), body)`, which parses the captured
send request (Cellcast `contacts`/`scheduleAt`, v5 `to`/`schedule`,
MobileMessage `messages[].to`/`scheduled_for`) into recipients in local form
and a send time in epoch ms. Branch on `scenarioProvider()` only where the
providers genuinely differ: e.g. Cellcast's OTP sender registration vs the
others' verification link, or MobileMessage stretching a short cool-off to
its 90-second scheduling minimum.

### 7. Mock SMS server — `smsmockserver/` (separate project)

A standalone PHP app (its own repo, https://github.com/jethro-chms/smsmockserver,
autoloaded under `SmsMockServer\...` namespace) that fakes the
real SMS provider HTTP APIs (Cellcast, 5CentSMS, MobileMessage) so tests never
hit the real internet. Composer installs it as a dev dependency
(`jethro/smsmockserver` in `require-dev`), and the `smsmockserver/` symlink
points at `vendor/jethro/smsmockserver`. It's served through the **same** nginx/php-fpm as the rest of
Jethro — see the regex location in `devbox.d/nginx/nginx.template` that matches
`/smsmockserver(/.*)?` — with its own database, `jethro_functest_smsmockserver`.
It refuses to run unless `SMSMOCK_ENABLED=1` is in php-fpm's environment (devbox
sets it), and its own bundled `profiles/` are always loaded; Jethro's per-scenario
`.smsmock.php` files are added via `SMSMOCK_PROFILES` (`devbox.d/php/php-fpm.conf`).
The normal dev webroot uses the bundled default accounts (`cellcast`, `5centsms`,
`mobilemessage`) with `StDemos` already registered; their provider URLs and
`2FA_SENDER_ID` are set in `devbox.d/web/conf.php`. Functional scenarios keep
their own profiles.

- Scenario `.conf` files point `SMS_CELLCAST_URL` / `SMS_5CENTSMS_URL` /
  `SMS_MOBILEMESSAGE_URL` at `/smsmockserver/tests/functional/sms/{profile}`
  (Jethro-root-relative), e.g. `/smsmockserver/tests/functional/sms/sms-bulk`.
- `tests/functional/sms/{profile}.smsmock.php` files define per-scenario behavior
  (forced failures, cooloff simulation, OTP requirements, sender-ID
  rejection, etc.) — see `src/Provider/*` and `src/Profile.php`.
- Tests introspect what was sent via `/meta/` endpoints
  (`lastPost`, `lastRequest`, `registrations`, ...), reached through the
  `mockMeta(profile, endpoint)` helper in `tests/functional/sms/smsmock-url.ts`.
  `request.delete(mockMeta(...))` in a `beforeEach` is the standard way to
  reset captured state between tests.
- The `smsmock-recordings` project checks the mock against recordings of
  the live APIs (`smsmockserver/recordings/<provider>/actual/`): it runs
  `smsmockserver/bin/check`, which records each provider against the mock
  and diffs the result. See `smsmockserver/README.md` for re-recording
  after a provider changes its API.

## Running

```bash
devbox services up -b                 # starts mariadb, nginx, php-fpm, functest_jethro_server, etc.
devbox run functests                  # cd tests/functional && bun install && playwright test
# or, once services are up:
cd tests/functional && npx playwright test --reporter=list
cd tests/functional && npx playwright test sms/sms-bulk.spec.ts   # single scenario
```

`auth.ts`'s `login()` navigates relative to the page's `baseURL`
(`./index.php`), which is how it stays under the correct scenario prefix
without knowing which one it's in. `timeouts.ts`'s `to(ms)` returns `0`
(wait forever) under `PWDEBUG=1` so a test paused at an Xdebug breakpoint
doesn't get torn down by an expiring assertion — use it for any
`{ timeout: ... }` option in new specs.

## Why the suite is sensitive to server load

8 Playwright workers share **one php-fpm pool**, and Jethro makes HTTP calls
*into that same pool*: a page render occupies one child while it waits for the
mock SMS server (`smsmockserver/`) to answer on another. If the pool runs out of
children, the self-calls can never be served, each blocks for the SMS HTTP
timeout (30s, `jethro-sms/src/HttpClient.php`), and the whole server stalls —
every test then fails on its own assertion timeout, which looks like 20 unrelated
flaky tests rather than one saturated pool. Things that keep it healthy:

- **`pm.max_children = 60`** in `devbox.d/php/php-fpm.conf` — headroom for
  `workers x (page + self-call + Datastar polls)`. It was 20, which deadlocked.
- **`?call=sms_info` releases the PHP session lock** (`session_write_close()`)
  before its upstream lookup. PHP's `files` session handler holds an exclusive
  flock for the whole request, so without that every poll for one browser session
  queues behind the previous one — a convoy that pins children for 30s each.
  Any new long-running or HTTP-calling handler must do the same.
- **`functest_databases_setup` cancels `scheduled` deliveries.** Each scheduled
  batch (like any delivery not yet final, 'sent' included) renders a polling
  span that fires `?call=sms_info` with a backoff interval from every 2 seconds,
  for up to 24h past its send time (`smsDeliveryPollIntervalSecs()`), so leftovers from specs that schedule
  sends would otherwise grow the polling load with every run. Since the job
  reloads the demo data (which has none) before every suite, this is a guard,
  not normally needed.
- **The fake SMS history is big on purpose, and that is the load test.**
  `tests/functional/testdata/fakesmses.sql.gz` holds 224 messages / 14016
  deliveries, and `View_persons__messages` plus `getPersonSmsHistory()` render
  every delivery of every message with **no `LIMIT`** — one status icon plus link
  per recipient. So the Messages page is a ~6.4 MB response costing ~0.3s
  (peak ~1.5s), loaded by several specs on every run. Measured: the suite passes
  with it and takes ~7% longer than with a thinned fixture (~145s vs ~135s), so
  it stays. It is a useful canary — if the pool or session-lock fixes above
  regress, these are the pages that will wedge first.
  (The unbounded rendering is still a real defect for instances with years of
  SMS history; bounding it would make this page cheap for everyone.)
- Genuinely slow tests are marked `test.slow()` (the setup-wizard install and the
  read-only nouns walk) rather than raising the global 30s timeout for all 66.

To check the pool while a run is in flight:

```bash
# children blocked on a session flock — a few is normal, most of the pool is not
for p in $(pgrep -f 'php-fpm: pool'); do cat /proc/$p/wchan; echo; done | sort | uniq -c
# request times: rt=30.000 en masse means the pool is starved, not the tests
grep -oE 'rt=[0-9.]+' .devbox/virtenv/nginx/access.log | sort -t= -k2 -rn | head
```

## Adding a new SMS scenario

1. Add the name to `SMS_SCENARIOS` in `playwright.config.ts`.
2. Create `tests/functional/sms/<name>.conf` (usually `require_once
   __DIR__.'/common.conf'` plus scenario-specific `define()`s).
3. Create `tests/functional/sms/<name>.spec.ts`.
4. If it needs custom mock-provider behavior, create
   `tests/functional/sms/<name>.smsmock.php`.
5. If provider differences can change what it observes, also add it to
   `PROVIDER_MATRIX` and create a `<name>-<suffix>.conf` and
   `<name>-<suffix>.smsmock.php` per `MATRIX_PROVIDERS` entry, registering
   the profile with the provider's wrapper (`FiveCentSmsProfile` /
   `MobileMessageProfile`). Point `SMS_5CENTSMS_URL` at
   `/smsmockserver/tests/functional/sms/<name>-v5` with no `/api/v1` suffix
   (which would hit the mock's v4 routes), and `SMS_MOBILEMESSAGE_URL` at
   `/smsmockserver/tests/functional/sms/<name>-mobilemessage`. Keep the spec
   provider-neutral via `scenario.ts`.

## Role-based permission checks

The `lookaround` project also logs in as each restricted `staff_member` persona
(`6pmonly`, `rostersetter`, `smallgroupleader`, `bandleader`, `kidscare`,
`noperms`) and asserts its permission/visibility restrictions — see
`docs/docs/developer/reference/functest-personas.mdx`.
