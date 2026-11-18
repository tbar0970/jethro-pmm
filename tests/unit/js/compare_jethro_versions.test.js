/**
 * compareJethroVersions() in resources/js/jethro.js, which decides what the
 * Admin > Upgrade page says about the running version vs the latest release.
 */
import { describe, expect, test } from "bun:test";
import { loadScript } from "./load-jethro-js.js";

const { compareJethroVersions } = loadScript("jethro.js");

/** @param {?number} n */
const sign = (n) => (n === null ? null : Math.sign(n));

describe("compareJethroVersions", () => {
	test.each([
		["2.39.0", "2.39.0", 0],
		["2.39.0", "v2.39.0", 0],
		["2.40.0-DEV", "2.40.0-DEV", 0],
		["2.39.0", "2.40.0", -1],
		["3.0.0", "2.99.99", 1],
		["2.39.1", "2.39.0", 1],
		// numeric, not string, ordering
		["2.10.0", "2.9.0", 1],
		["2.39.10", "2.39.9", 1],
		// a dated build of the next release is newer than the current release
		["2.40.0-20261118", "v2.39.0", 1],
		// a suffixed version is a pre-release of the plain one
		["2.40.0-DEV", "v2.40.0", -1],
		["2.40.0-20261118", "2.40.0", -1],
		["2.40.0-20261118", "2.40.0-20261119", -1],
	])("%s vs %s → %d", (a, b, expected) => {
		expect(sign(compareJethroVersions(a, b))).toBe(expected);
		expect(sign(compareJethroVersions(b, a))).toBe(-expected || 0);
	});

	test.each([
		["DEV", "v2.39.0"],
		["2.39.0", ""],
		["2.39", "2.39.0"],
		["2.39.0.1", "2.39.0"],
		["x2.39.0", "2.39.0"],
	])("%s vs %s is not comparable", (a, b) => {
		expect(compareJethroVersions(a, b)).toBeNull();
		expect(compareJethroVersions(b, a)).toBeNull();
	});
});
