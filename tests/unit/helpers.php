<?php

/**
 * Minimal test helpers — no library dependencies.
 *
 * Usage in test files:
 *   test('description', function() {
 *       assert_eq($got, $expected);
 *   });
 *
 * Run: php tests/unit/run.php
 */

namespace Test;

// ---------------------------------------------------------------------------
// Assertions
// ---------------------------------------------------------------------------

function assert_true(mixed $value, string $msg = ''): void
{
	if ($value !== true) {
		throw new AssertionFailed($msg ?: 'Expected true, got ' . get_debug_type($value));
	}
}

function assert_false(mixed $value, string $msg = ''): void
{
	if ($value !== false) {
		throw new AssertionFailed($msg ?: 'Expected false, got ' . get_debug_type($value));
	}
}

function assert_eq(mixed $got, mixed $expected, string $msg = ''): void
{
	if ($got !== $expected) {
		$msg = $msg ?: sprintf('Expected %s, got %s', var_export($expected, true), var_export($got, true));
		throw new AssertionFailed($msg);
	}
}

function assert_contains(string $haystack, string $needle, string $msg = ''): void
{
	if (!str_contains($haystack, $needle)) {
		$msg = $msg ?: sprintf('Expected string to contain "%s"', $needle);
		throw new AssertionFailed($msg);
	}
}

function assert_not_contains(string $haystack, string $needle, string $msg = ''): void
{
	if (str_contains($haystack, $needle)) {
		$msg = $msg ?: sprintf('Expected string NOT to contain "%s"', $needle);
		throw new AssertionFailed($msg);
	}
}

/**
 * Assert that a callable throws the given exception class.
 */
function assert_throws(string $exceptionClass, callable $fn, string $msg = ''): void
{
	try {
		$fn();
	} catch (\Throwable $e) {
		if ($e instanceof $exceptionClass) {
			return;
		}
		throw new AssertionFailed($msg ?: "Expected $exceptionClass, got " . get_class($e) . ': ' . $e->getMessage());
	}
	throw new AssertionFailed($msg ?: "Expected $exceptionClass, but nothing was thrown");
}

// ---------------------------------------------------------------------------
// Exceptions
// ---------------------------------------------------------------------------

final class AssertionFailed extends \RuntimeException {}

// ---------------------------------------------------------------------------
// Test registry
// ---------------------------------------------------------------------------

final class Registry
{
	/** @var array<array{string, callable}> */
	public static array $tests = [];
	/** @var array<string, string> */
	public static array $failures = [];
	public static int $passCount = 0;
	/**
	 * Files that could not be loaded at all: relative path => error.
	 *
	 * Kept apart from $failures because there is no test to name — the file
	 * blew up before registering any.
	 *
	 * @var array<string, string>
	 */
	public static array $loadFailures = [];
}

/**
 * Record a test file that could not be loaded.
 *
 * Counted as a failure so the suite still exits non-zero, but the remaining
 * files run instead of being taken down with it.
 */
function load_failure(string $file, \Throwable $e): void
{
	Registry::$loadFailures[$file] = get_class($e) . ': ' . $e->getMessage();
}

/**
 * Register a test.
 */
function test(string $description, callable $fn): void
{
	Registry::$tests[] = [$description, $fn];
}

/**
 * Run all registered tests.
 *
 * @return int Exit code (0 = all passed)
 */
function run_all(): int
{
	$total = count(Registry::$tests);

	echo "Running $total tests...\n\n";

	foreach (Registry::$tests as [$description, $fn]) {
		try {
			$fn();
			Registry::$passCount++;
			echo "  \033[32m✓\033[0m $description\n";
		} catch (\Throwable $e) {
			Registry::$failures[$description] = $e->getMessage();
			echo "  \033[31m✗\033[0m $description\n";
			echo "    \033[31m{$e->getMessage()}\033[0m\n";
		}
	}

	foreach (Registry::$loadFailures as $file => $err) {
		echo "  \033[31m✗\033[0m could not load $file\n";
		echo "    \033[31m$err\033[0m\n";
	}

	echo "\n---\n";
	$failed = count(Registry::$failures) + count(Registry::$loadFailures);
	// Total is derived, so it still adds up when a file failed to load and
	// therefore contributed no tests to count.
	echo Registry::$passCount . ' passed, ' . $failed . ' failed, '
		. (Registry::$passCount + $failed) . ' total' . "\n";

	return $failed > 0 ? 1 : 0;
}
