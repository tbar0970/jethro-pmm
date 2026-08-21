<?php
/**
 * Home-page check for the NEEDS_UTF8MB4_UPGRADE flag.
 *
 * The flag is set by upgrades/2026-upgrade-to-2.40.sql when the database still
 * needs converting to utf8mb4. It can be stale, though — e.g. the CLI upgrader
 * (upgrades/2026-upgrade-to-2.40-utf8mb4.php) has already run — so verify the
 * live database before acting:
 *
 *   - healthy      → clear the flag (nothing left to fix).
 *   - needs fixing → show sysadmins a notice linking to Admin → Upgrade.
 *
 * Included inline from views/view_1_home.class.php.
 */

if (ifdef('NEEDS_UTF8MB4_UPGRADE')) {
	require_once __DIR__.'/dbcharsetutils.php';
	$health = DB_Charset_Utils::checkHealth();
	if ($health['healthy']) {
		// Database is already on utf8mb4 (e.g. fixed via the CLI) — clear the stale flag.
		Config_Manager::deleteSetting('NEEDS_UTF8MB4_UPGRADE');
	} elseif ($GLOBALS['user_system']->havePerm(PERM_SYSADMIN)) {
		print_message('This database needs a character-set upgrade to <code>utf8mb4</code> (required for emoji and full Unicode support, and to fix mixed-collation query errors). <a href="'.build_url(Array('view' => 'admin__upgrade')).'">Go to Admin &rarr; Upgrade</a> to review and apply the fix.', 'warning', TRUE);
	}
}
