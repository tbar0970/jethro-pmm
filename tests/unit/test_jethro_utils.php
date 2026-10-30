<?php

/**
 * Regression tests for devbox.d/bin/jethro_utils: the stable freeport
 * calculation, checkout-root identity, and the stdout/stderr/exit-status contract.
 *
 * Each test copies the script into a throwaway checkout (a directory with
 * devbox.json and devbox.d/bin/jethro_utils), because the helper derives its
 * identity from its own physical location.
 *
 * @see docs/docs/developer/reference/JETHRO_UTILS.md
 */

use function Test\assert_eq;
use function Test\assert_true;
use function Test\test;

/**
 * Create a checkout at $root containing a copy of jethro_utils.
 *
 * @return string path of the copied script
 */
function jethro_utils_checkout(string $root): string
{
	@mkdir($root . '/devbox.d/bin', 0700, true);
	file_put_contents($root . '/devbox.json', "{}\n");
	$script = $root . '/devbox.d/bin/jethro_utils';
	copy(__DIR__ . '/../../devbox.d/bin/jethro_utils', $script);
	chmod($script, 0700);
	return $script;
}

/**
 * Run a command without a shell.
 *
 * @param list<string> $cmd
 * @param array<string,string>|null $env  null inherits the environment
 * @return array{int, string, string} exit status, stdout, stderr
 */
function jethro_utils_run(array $cmd, ?string $cwd = null, ?array $env = null): array
{
	$p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $env);
	$out = stream_get_contents($pipes[1]);
	$err = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	return [proc_close($p), $out, $err];
}

function jethro_utils_rmtree(string $path): void
{
	if (is_link($path) || is_file($path)) {
		unlink($path);
		return;
	}
	if (!is_dir($path)) return;
	foreach (scandir($path) as $entry) {
		if ($entry !== '.' && $entry !== '..') jethro_utils_rmtree($path . '/' . $entry);
	}
	rmdir($path);
}

/**
 * Run $fn with a fresh private temp directory, removed afterwards.
 *
 * @param callable(string): void $fn
 */
function jethro_utils_in_tmp(callable $fn): void
{
	$dir = sys_get_temp_dir() . '/jethro_utils_test_' . bin2hex(random_bytes(6));
	mkdir($dir, 0700);
	try {
		$fn(realpath($dir));
	} finally {
		jethro_utils_rmtree($dir);
	}
}

test('jethro_utils: reference vectors (fixed /tmp roots, CRCs above 2^31)', function () {
	// The vectors name exact roots, so serialise against concurrent runs.
	$lock = fopen('/tmp/jethro_utils_test_vectors.lock', 'c');
	flock($lock, LOCK_EX);
	try {
		$example = jethro_utils_checkout('/tmp/freeport-example');
		$other = jethro_utils_checkout('/tmp/freeport-other');
		$cases = [
			[[$example, 'freeport', 'functests'], "10212\n"],
			[[$example, 'freeport', 'web'], "29711\n"],
			[[$other, 'freeport', 'functests'], "20849\n"],
			[[$example, 'freeport', 'default'], "11166\n"],
			[[$example, 'freeport'], "29711\n"],
		];
		foreach ($cases as [$cmd, $expected]) {
			assert_eq(jethro_utils_run($cmd), [0, $expected, ''], implode(' ', array_slice($cmd, 1)) . ' in ' . dirname($cmd[0], 3));
		}
	} finally {
		jethro_utils_rmtree('/tmp/freeport-example');
		jethro_utils_rmtree('/tmp/freeport-other');
		flock($lock, LOCK_UN);
		fclose($lock);
	}
});

test('jethro_utils: identity is the physical root, independent of cwd, symlinks, PATH and locale', function () {
	jethro_utils_in_tmp(function (string $tmp) {
		$root = "$tmp/a b/ü";
		$script = jethro_utils_checkout($root);
		symlink($root, "$tmp/alias");
		mkdir("$tmp/linkbin");
		symlink($script, "$tmp/linkbin/ju");
		$path = "$tmp/linkbin:/usr/bin:/bin";

		foreach (['freeport web', 'freeport functests'] as $args) {
			$args = explode(' ', $args);
			$expected = jethro_utils_run([$script, ...$args]);
			assert_eq($expected[0], 0);
			foreach ([
				jethro_utils_run(["$tmp/alias/devbox.d/bin/jethro_utils", ...$args]),
				jethro_utils_run(["$tmp/linkbin/ju", ...$args], '/'),
				jethro_utils_run(['/usr/bin/env', 'ju', ...$args], $tmp, ['PATH' => $path]),
				jethro_utils_run([$script, ...$args], "$root/devbox.d", ['PATH' => $path, 'LC_ALL' => 'C.UTF-8']),
			] as $got) {
				assert_eq($got, $expected, implode(' ', $args));
			}
		}
		$port = jethro_utils_run([$script, 'freeport', 'web'])[1];
		assert_true((bool) preg_match('/^[1-9][0-9]{4}\n$/D', $port) && (int) $port >= 10000 && (int) $port <= 29999, "port $port");
	});
});

test('jethro_utils: usage errors exit 2 with empty stdout', function () {
	jethro_utils_in_tmp(function (string $tmp) {
		$script = jethro_utils_checkout($tmp);
		foreach ([[], ['bogus'], ['freeport', ''], ['freeport', 'a', 'b'], ['env', 'x']] as $args) {
			[$status, $out, $err] = jethro_utils_run([$script, ...$args]);
			assert_eq([$status, $out], [2, ''], implode(' ', $args));
			assert_true($err !== '', 'diagnostic on stderr');
		}
		// Purposes are opaque, not options.
		[$status, $out] = jethro_utils_run([$script, 'freeport', '--help']);
		assert_eq($status, 0);
		assert_true($out !== jethro_utils_run([$script, 'freeport'])[1]);
	});
});

test('jethro_utils: root and calculation failures exit 1 with empty stdout', function () {
	jethro_utils_in_tmp(function (string $tmp) {
		$script = jethro_utils_checkout("$tmp/ok");
		[$status, $out] = jethro_utils_run(['/bin/bash', $script, 'freeport'], null, ['PATH' => '/nonexistent']);
		assert_eq([$status, $out], [1, ''], 'cksum unavailable');

		$noconf = jethro_utils_checkout("$tmp/noconf");
		unlink("$tmp/noconf/devbox.json");
		assert_eq(array_slice(jethro_utils_run([$noconf, 'freeport']), 0, 2), [1, ''], 'no devbox.json');

		$newline = jethro_utils_checkout("$tmp/nl\n");
		assert_eq(array_slice(jethro_utils_run([$newline, 'freeport']), 0, 2), [1, ''], 'LF in root');
	});
});

test('jethro_utils: configured ports accept TCP boundaries and reject invalid or colliding pairs', function () {
	$script = __DIR__.'/../../devbox.d/bin/jethro_utils';
	foreach ([['1', '65535'], ['080', '443']] as [$web, $functests]) {
		// Literal ports are accepted but not re-exported.
		assert_eq(jethro_utils_run([$script, 'env'], null, [
			'PATH' => '/usr/bin:/bin', 'NGINX_WEB_PORT' => $web, 'FUNCTEST_WEB_PORT' => $functests,
		]), [0, '', '']);
	}
	foreach ([['', '8089'], ['0', '8089'], ['65536', '8089'], ['80;exit 0', '8089'], ['080', '80']] as [$web, $functests]) {
		[$status, $out, $err] = jethro_utils_run([$script, 'env'], null, [
			'PATH' => '/usr/bin:/bin', 'NGINX_WEB_PORT' => $web, 'FUNCTEST_WEB_PORT' => $functests,
		]);
		assert_eq([$status, $out], [1, '']);
		assert_true($err !== '');
	}
});

test('jethro_utils: env exports only _default_ ports and leaves literals alone', function () {
	$script = __DIR__.'/../../devbox.d/bin/jethro_utils';
	$web = trim(jethro_utils_run([$script, 'freeport', 'web'])[1]);
	$functests = trim(jethro_utils_run([$script, 'freeport', 'functests'])[1]);
	foreach ([
		['_default_', '_default_', "export NGINX_WEB_PORT=$web\nexport FUNCTEST_WEB_PORT=$functests\n"],
		['_default_', '1', "export NGINX_WEB_PORT=$web\n"],
		['65535', '_default_', "export FUNCTEST_WEB_PORT=$functests\n"],
		['8089', '8090', ''],
	] as [$configuredWeb, $configuredFunctests, $expected]) {
		assert_eq(jethro_utils_run([$script, 'env'], null, [
			'PATH' => '/usr/bin:/bin',
			'NGINX_WEB_PORT' => $configuredWeb,
			'FUNCTEST_WEB_PORT' => $configuredFunctests,
		]), [0, $expected, ''], "$configuredWeb/$configuredFunctests");
	}
});

