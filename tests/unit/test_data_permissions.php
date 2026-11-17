<?php

/**
 * The PHP and SQL data-permission symbol maps must stay in step.
 *
 * Both features that store a data mask (staff field visibility and members-area
 * share consent) depend on the same symbol-to-bit vocabulary. This test pins
 * the PHP helpers and both SQL definitions to the same mapping.
 */

namespace Test\DataPermissions;

use function Test\{test, assert_eq, assert_true, assert_false};

require_once __DIR__ . '/../../include/data_permissions.php';

function expected_bits(): array
{
	$bits = Array('VIEWNAME' => DATA_PERM_VIEWNAME);
	foreach (get_data_permission_levels() as $bit => $level) {
		$bits[$level[0]] = $bit;
	}
	return $bits;
}

function sql_bits(string $path): ?array
{
	$sql = (string) file_get_contents($path);
	if (!preg_match('/CREATE\s+FUNCTION\s+dataPermBit\b.*?\bEND\s*;/is', $sql, $function)) {
		return null;
	}
	preg_match_all("/WHEN\\s+'([A-Z]+)'\\s+THEN\\s+(\\d+)/i", $function[0], $matches, PREG_SET_ORDER);
	$bits = Array();
	foreach ($matches as $match) {
		$bits[$match[1]] = (int) $match[2];
	}
	return $bits;
}

test('data_perm_bit resolves the shared symbols', function () {
	assert_eq(data_perm_bit('VIEWNAME'), DATA_PERM_VIEWNAME);
	assert_eq(data_perm_bit('VIEWEMAIL'), DATA_PERM_VIEWEMAIL);
	assert_eq(data_perm_bit('VIEWMOBILE'), DATA_PERM_VIEWMOBILE);
	assert_eq(data_perm_bit('unknown-symbol'), 0);
});

test('data_perm_mask_has tests only the requested bit', function () {
	$mask = DATA_PERM_VIEWNAME | DATA_PERM_VIEWEMAIL;
	assert_true(data_perm_mask_has($mask, 'VIEWNAME'));
	assert_true(data_perm_mask_has($mask, 'VIEWEMAIL'));
	assert_false(data_perm_mask_has($mask, 'VIEWMOBILE'));
	assert_false(data_perm_mask_has(0, 'VIEWNAME'));
});

test('installer and upgrade SQL define the same symbol map as PHP', function () {
	$expected = expected_bits();
	foreach (Array(
		'installer' => __DIR__ . '/../../include/installer.class.php',
		'upgrade' => __DIR__ . '/../../upgrades/2026-upgrade-to-2.40.sql',
	) as $name => $path) {
		$bits = sql_bits($path);
		assert_true($bits !== null, "$name has no dataPermBit() SQL definition");
		assert_eq($bits, $expected, "$name dataPermBit() drifted from include/data_permissions.php");
	}
});
