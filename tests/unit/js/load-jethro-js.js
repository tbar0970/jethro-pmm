/**
 * Load a browser script from resources/js/ into an isolated V8 context so its
 * top-level functions can be unit-tested without a browser.
 *
 * The scripts expect jQuery and the DOM. `$`, `jQuery`, `document` and
 * `window` are replaced by an inert stub that returns itself for every
 * property access and call, so top-level wiring such as
 * `$(document).ready(...)` and `$(document).on(...)` runs as a no-op and the
 * callbacks are never invoked. Only pure functions (no DOM or jQuery use when
 * called) are meaningfully testable this way.
 *
 * See docs/docs/developer/reference/js-unit-tests.md
 */
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import vm from "node:vm";

const stub = new Proxy(function () {}, {
	get: (_target, prop) => (prop === Symbol.toPrimitive ? undefined : stub),
	apply: () => stub,
	construct: () => stub,
});

/**
 * @param {string} name file name under resources/js/, e.g. "jethro.js"
 * @returns {Record<string, any>} the context, holding the script's globals
 *   (top-level `function` and `var` declarations)
 */
export function loadScript(name) {
	const path = fileURLToPath(new URL(`../../../resources/js/${name}`, import.meta.url));
	const context = { $: stub, jQuery: stub, document: stub, window: stub, navigator: {} };
	vm.runInNewContext(readFileSync(path, "utf8"), context, { filename: path });
	return context;
}
