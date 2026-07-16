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
regular dev webroot (`devbox.d/web`). Listens on `FUNCTEST_WEB_PORT` (default
8089). Depends on `functest_database_setup`, which loads the `jethro_functest`
database once with demo data — **all scenarios share this one database**, so
specs must scope assertions to data unique to their own run (e.g. a
timestamped message body — see `sms-send-single.spec.ts`) rather than
asserting "the most recent X", since other scenarios' tests may run
concurrently (`fullyParallel: true`, 4 workers).

A second service, `functest_database_setup`, also provisions
`jethro_functest_smsmockserver`, the database backing the mock SMS server (below).

### 2. `devbox.d/functest_jethro_server/` — the test webroot

A directory of symlinks back into the real source tree (`include`, `views`,
`db_objects`, `calls`, `index.php`, etc.) plus **its own standalone
`conf.php`** — this is a different `conf.php` from the multi-tenant
EasyJethro one at the repo root (which does per-account lookup under
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

### 7. Mock SMS server — `smsmockserver/`

A small standalone PHP app (its own `composer.json`, autoloaded under
`SmsMockServer\...` namespace) that fakes the real SMS provider HTTP
APIs (Cellcast, 5CentSMS) so tests never hit the real internet.
It's served through the **same** nginx/php-fpm as the rest of Jethro — see the
regex location in `nginx.template` that matches `/tests/functional/sms/{profile}/(api|meta)/...` — with its own
database, `jethro_functest_smsmockserver`.

- Scenario `.conf` files point `SMS_CELLCAST_URL` / `SMS_5CENTSMS_URL` at
  `/tests/functional/sms/{profile}` (Jethro-root-relative), e.g.
  `/tests/functional/sms/sms-bulk`.
- `tests/functional/sms/{profile}.smsmock.php` files define per-scenario behavior
  (forced failures, cooloff simulation, OTP requirements, sender-ID
  rejection, etc.) — see `src/Provider/*` and `src/Profile.php`.
- Tests introspect what was sent via `/meta/` endpoints
  (`lastPost`, `lastRequest`, `registrations`, ...), reached through the
  `mockMeta(profile, endpoint)` helper in `tests/functional/sms/smsmock-url.ts`.
  `request.delete(mockMeta(...))` in a `beforeEach` is the standard way to
  reset captured state between tests.

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

## Adding a new SMS scenario

1. Add the name to `SMS_SCENARIOS` in `playwright.config.ts`.
2. Create `tests/functional/sms/<name>.conf` (usually `require_once
   __DIR__.'/common.conf'` plus scenario-specific `define()`s).
3. Create `tests/functional/sms/<name>.spec.ts`.
4. If it needs custom mock-provider behavior, create
   `tests/functional/sms/<name>.smsmock.php`.
