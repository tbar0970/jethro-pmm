---
title: jethro_utils — per-checkout ports
---

# `devbox.d/bin/jethro_utils`

Running several Jethro checkouts on one machine makes fixed dev/functest ports
collide. `jethro_utils` instead derives ports from a checkout's physical
location: each checkout gets its own stable values with no per-checkout
configuration, and the values never change between runs unless the checkout
moves. Caller cwd and version-control storage are irrelevant.

```text
jethro_utils freeport [purpose]   # stable TCP port, 10000-29999, for (checkout, purpose)
jethro_utils env                  # POSIX exports resolving _default_ ports
```

- `freeport [purpose]` — the port for a purpose, defaulting to `web` so the bare
  form names the same port `env` resolves `NGINX_WEB_PORT` to; the devbox setup
  uses the purposes `web` and `functests`. Stable per checkout and
  purpose, so a purpose string also acts as a discriminator for additional
  services.
- `env` — resolves `_default_` in `NGINX_WEB_PORT`/`FUNCTEST_WEB_PORT` to the
  `web`/`functests` ports and emits each resolved port as a POSIX export.
  Variables holding a literal port are left untouched: nothing is printed for
  them, so the configured value stands. It fails rather than exporting two
  identical ports.

## Use in devbox

`devbox.json` sets both port variables to `_default_`; its init hook evaluates
`jethro_utils env` on every `devbox` shell, run and service, so the servers and
everything talking to them see the same resolved ports, and the URLs are
printed when `devbox services up` starts. Replace either `_default_` entry with
a literal port to pin it for that checkout. Read the configured value with
`./devbox run -- printenv FUNCTEST_WEB_PORT` — the CI readiness poll takes this
route, and Playwright receives the same environment, so nothing falls back to a
fixed port.

## Guarantees and limits

Values are deterministic and stateless: the helper neither probes listeners nor
creates files, so results are candidates, not reserved. Ports can collide
between checkouts. Services must therefore surface bind failures, and
functional tests verify which checkout is actually serving. When two checkouts
do collide, move one of them or override its port explicitly — silently picking
a different port would defeat the stability every other checkout relies on.

The derivation is versioned and is a stability contract: changing it reshuffles
ports and hashes for every existing checkout at once, so treat any change as a
deliberate migration. The algorithm lives in `devbox.d/bin/jethro_utils`; a
failed invocation prints an error to stderr, writes nothing to stdout, and
`env` exports nothing.

Regression coverage: `tests/unit/test_jethro_utils.php`.
