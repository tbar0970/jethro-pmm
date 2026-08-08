#!/usr/bin/env php
<?php

/**
 * Unit test runner.
 *
 * Usage:
 *   php tests/unit/run.php                      # run all tests
 *   php tests/unit/run.php sms/cellcast         # run a subset
 *
 * Test files go in tests/unit/<area>/ and use the Test\ namespace helpers.
 * They are discovered by scanning for test_*.php files.
 *
 * Process isolation: all test files normally share one PHP process, so
 * constants defined by one file are visible to all others.  A test file
 * that needs its own constant table (e.g. to define SMS_SENDER, which
 * would break other tests) can declare `@isolated-process` in its header
 * docblock; the runner executes it in a child `php tests/unit/run.php
 * --isolated <file>` process after the in-process tests, and folds the
 * child's pass/fail counts into the grand total.
 */

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

// If a vendor autoloader exists, load it.  Otherwise the jethro codebase
// uses require_once-style includes — tests just include what they need.
$autoloadPaths = [
	__DIR__ . '/../../vendor/autoload.php',
];
foreach ($autoloadPaths as $path) {
	if (file_exists($path)) {
		require_once $path;
		break;
	}
}

// Load helpers — must come before any test file
require_once __DIR__ . '/_entries.php';
require_once __DIR__ . '/_entries.php';
require_once __DIR__ . '/helpers.php';

// ---------------------------------------------------------------------------
// Child mode: run a single @isolated-process file in this (fresh) process
// ---------------------------------------------------------------------------

if (($argv[1] ?? null) === '--isolated') {
	$file = $argv[2] ?? '';
	if ($file === '' || !is_file($file)) {
		fwrite(STDERR, "--isolated requires a test file path\n");
		exit(2);
	}
	require_once $file;
	exit(\Test\run_all());
}

// ---------------------------------------------------------------------------
// Discover test files
// ---------------------------------------------------------------------------

$filter = $argv[1] ?? null;
$root = realpath(__DIR__ . '/../..');

$testFiles = [];
$isolatedFiles = [];
// Recursively scan tests/unit/ for test_*.php files, following symlinks
// (e.g. tests/unit/sms -> ../../jethro-sms/tests/).
$it = new \RecursiveIteratorIterator(
	new \RecursiveDirectoryIterator(__DIR__, \FilesystemIterator::FOLLOW_SYMLINKS),
);
foreach ($it as $file) {
	if ($file->getExtension() !== 'php') continue;
	$filename = $file->getBasename();
	if (!str_starts_with($filename, 'test_')) continue;

	$relativePath = str_replace($root . '/', '', $file->getPathname());

	if ($filter !== null && !str_contains($relativePath, $filter)) {
		continue;
	}

	// @isolated-process in the header docblock → run in a child process
	// with a clean constant table, after the in-process tests.
	$head = (string) file_get_contents($file->getPathname(), false, null, 0, 2048);
	if (str_contains($head, '@isolated-process')) {
		$isolatedFiles[] = $file->getPathname();
		continue;
	}

	$testFiles[] = $file->getPathname();
}

if ($testFiles === [] && $isolatedFiles === []) {
	echo $filter !== null
		? "No test files matching '$filter'\n"
		: "No test files found.\n";
	exit(1);
}

// ---------------------------------------------------------------------------
// Load test files
// ---------------------------------------------------------------------------

echo "Loading test files:\n";
$loadingFile = null;

// An uncatchable fatal (memory exhaustion, recursion, E_COMPILE_ERROR) still
// kills the process. Name the file responsible rather than leaving a bare
// stack trace pointing at this require_once.
register_shutdown_function(static function () use (&$loadingFile): void {
	if ($loadingFile === null) {
		return;
	}
	$err = error_get_last();
	if ($err === null || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
		return;
	}
	fwrite(STDERR, "\nFATAL while loading $loadingFile — the suite stopped here.\n"
		. "  {$err['message']}\n"
		. "  Add @isolated-process to that file's header to contain it.\n");
});

foreach ($testFiles as $path) {
	$rel = str_replace($root . '/', '', $path);
	echo '  ' . $rel . "\n";
	$loadingFile = $rel;
	// One broken file must not take down every file queued behind it: record
	// it as a failure and carry on. Most breakage (missing require, parse
	// error, undefined symbol) is a catchable Error in PHP 8.
	try {
		require_once $path;
	} catch (\Throwable $e) {
		\Test\load_failure($rel, $e);
		echo "    \033[31mfailed to load: " . $e->getMessage() . "\033[0m\n";
	}
	$loadingFile = null;
}
echo "\n";

// ---------------------------------------------------------------------------
// Run
// ---------------------------------------------------------------------------

$exitCode = $testFiles !== [] ? \Test\run_all() : 0;

// Run each @isolated-process file in its own child process and fold its
// pass/fail counts into a grand total.
if ($isolatedFiles !== []) {
	$isoPassed = $isoFailed = 0;
	foreach ($isolatedFiles as $path) {
		$rel = str_replace($root . '/', '', $path);
		echo "\nIsolated process: $rel\n";
		$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__)
			. ' --isolated ' . escapeshellarg($path) . ' 2>&1';
		$output = [];
		$status = 0;
		exec($cmd, $output, $status);
		echo '  ' . implode("\n  ", $output) . "\n";
		// Fold the child's "N passed, M failed, T total" tail into our counts.
		if (preg_match('/(\d+) passed, (\d+) failed, \d+ total/', implode("\n", $output), $m)) {
			$isoPassed += (int) $m[1];
			$isoFailed += (int) $m[2];
		} else {
			// Child crashed before printing a summary — count as a failure.
			$isoFailed++;
		}
		if ($status !== 0) {
			$exitCode = 1;
		}
	}
	echo "\n=== Grand total (including isolated processes) ===\n";
	$grandPassed = \Test\Registry::$passCount + $isoPassed;
	$grandFailed = count(\Test\Registry::$failures) + count(\Test\Registry::$loadFailures) + $isoFailed;
	echo $grandPassed . ' passed, ' . $grandFailed . ' failed, ' . ($grandPassed + $grandFailed) . ' total' . "\n";
	if ($isoFailed > 0) {
		$exitCode = 1;
	}
}

exit($exitCode);
