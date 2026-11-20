---
title: JavaScript unit tests
sidebar_position: 20
---

# JavaScript unit tests

Pure functions in `resources/js/*.js` are unit-tested with Bun's built-in
test runner (`bun:test`), alongside the PHP unit tests.

| What | Where |
|---|---|
| Test files | `tests/unit/js/*.test.js` |
| Script loader | `tests/unit/js/load-jethro-js.js` (`loadScript(name)`) |
| Run locally | `./devbox run unittests` (PHP tests, then `bun test --pass-with-no-tests ./tests/unit/js`) |
| CI | `js` job in `.github/workflows/unittests.yml` |

## How scripts are loaded

The scripts are plain browser scripts, not modules: they declare globals and
wire themselves up with jQuery at load time. `loadScript('jethro.js')` runs
the file in a fresh `node:vm` context where `$`, `jQuery`, `document` and
`window` are an inert stub (every property access or call returns the stub),
then returns the context, whose properties are the script's top-level
`function` and `var` declarations.

Consequences:

- Only functions that do not touch jQuery or the DOM when called give
  meaningful results. Keep logic worth testing in such functions and leave
  the DOM wiring thin.
- The function must be declared at the top level of the script (not inside a
  `$(document).ready(...)` callback), since callbacks never run.
- Inline `<script>` blocks in PHP views are not testable; move the logic into
  a `resources/js/` file first. `jethro.js` is loaded on every page via
  `templates/head.template.php` (bundled as `jethro-<version>.js` in releases).

## Example

```js
import { expect, test } from "bun:test";
import { loadScript } from "./load-jethro-js.js";

const { someFunction } = loadScript("jethro.js");

test("someFunction", () => {
	expect(someFunction(1)).toBe(2);
});
```
