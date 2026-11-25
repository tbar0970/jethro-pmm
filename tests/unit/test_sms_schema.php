<?php

/**
 * Fresh installs build the SMS tables from DB_Object::getInitSQL(); existing
 * installs get them from upgrades/2026-upgrade-to-2.40.sql. The two must
 * produce the same columns, and _smsdelivery.provider must accept every
 * provider key the code writes (SmsProvider::getKey()), or strict-mode MySQL
 * rejects the delivery insert after the gateway has already sent.
 */

namespace Test\SmsSchema;

use function Test\{test, assert_eq, assert_true};

require_once __DIR__ . '/../../include/general.php';
if (!class_exists('DB_Object')) {
	require_once __DIR__ . '/../../include/db_object.class.php';
}
require_once __DIR__ . '/../../db_objects/sms.class.php';
require_once __DIR__ . '/../../db_objects/smsdelivery.class.php';
require_once __DIR__ . '/../../vendor/jethro/jethro-sms/src/load.php';

const UPGRADE_SQL = __DIR__ . '/../../upgrades/2026-upgrade-to-2.40.sql';

/** The parenthesised body of `CREATE TABLE [IF NOT EXISTS] <table> (...)` in $sql. */
function createTableBody(string $sql, string $table): string
{
	$pattern = '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?' . preg_quote($table, '/') . '`?\s*\(/i';
	if (!preg_match($pattern, $sql, $m, PREG_OFFSET_CAPTURE)) {
		throw new \RuntimeException("No CREATE TABLE $table found");
	}
	$start = $m[0][1] + strlen($m[0][0]);
	$depth = 1;
	$inQuote = false;
	for ($i = $start, $n = strlen($sql); $i < $n; $i++) {
		$c = $sql[$i];
		if ($c === "'") {
			$inQuote = !$inQuote;
		} elseif (!$inQuote && $c === '(') {
			$depth++;
		} elseif (!$inQuote && $c === ')' && --$depth === 0) {
			return substr($sql, $start, $i - $start);
		}
	}
	throw new \RuntimeException("Unbalanced CREATE TABLE $table");
}

/**
 * Column name => normalised definition. Keys, indexes and constraints are
 * skipped: foreign keys come from getForeignKeys() on install, inline
 * CONSTRAINTs on upgrade.
 *
 * @return array<string,string>
 */
function columns(string $body): array
{
	$cols = [];
	foreach (preg_split("/,\s*\n/", $body) as $line) {
		$line = trim($line);
		if (!preg_match('/^`?(\w+)`?\s+(.*)$/s', $line, $m)) {
			continue;
		}
		if (in_array(strtoupper($m[1]), ['PRIMARY', 'INDEX', 'KEY', 'UNIQUE', 'CONSTRAINT', 'FOREIGN'], true)) {
			continue;
		}
		$cols[$m[1]] = preg_replace('/\s+/', ' ', $m[2]);
	}
	return $cols;
}

function initSql(string $class): string
{
	$obj = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
	return $obj->getInitSQL();
}

foreach (['Sms' => 'sms', 'Smsdelivery' => '_smsdelivery'] as $class => $table) {
	test("$table: install DDL (getInitSQL) has the same columns as the 2.40 upgrade", function () use ($class, $table) {
		$install = columns(createTableBody(initSql($class), $table));
		$upgrade = columns(createTableBody(file_get_contents(UPGRADE_SQL), $table));
		assert_true($install !== [], "parsed no columns from $class::getInitSQL()");
		assert_eq($install, $upgrade);
	});
}

test('_smsdelivery.provider ENUM accepts every key a provider writes', function () {
	$providerDir = realpath(__DIR__ . '/../../vendor/jethro/jethro-sms/src/Providers');
	foreach (glob($providerDir . '/*.php') as $file) {
		require_once $file;
	}
	$keys = [];
	foreach (get_declared_classes() as $class) {
		$ref = new \ReflectionClass($class);
		if ($ref->isAbstract() || !$ref->implementsInterface(\Sms\SmsProvider::class)
			|| !str_starts_with((string) $ref->getFileName(), $providerDir)) {
			continue;
		}
		$key = $ref->newInstanceWithoutConstructor()->getKey();
		if ($key !== null) {
			$keys[$class] = $key;
		}
	}
	assert_true(count($keys) >= 4, 'expected at least the 5CentSMS v4/v5, Cellcast and MobileMessage providers, found: ' . implode(', ', $keys));

	foreach (['install' => initSql('Smsdelivery'), 'upgrade' => file_get_contents(UPGRADE_SQL)] as $source => $sql) {
		$provider = columns(createTableBody($sql, '_smsdelivery'))['provider'];
		preg_match('/ENUM\s*\(([^)]*)\)/i', $provider, $m);
		$allowed = array_map(fn($v) => trim($v, " '"), explode(',', $m[1]));
		foreach ($keys as $class => $key) {
			assert_true(in_array($key, $allowed, true), "$source schema: $class::getKey() '$key' is not in provider ENUM(" . implode(',', $allowed) . ')');
		}
	}
});
