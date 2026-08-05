#!/bin/bash -eu
# Run the CLI upgrader against the test database.
# Backs up devbox.d/web/conf.php, writes a temporary one pointing
# to the test DB, runs the upgrader, and restores the original.
set -o pipefail

DB=jethro_functest_upgrade_latin1_cli
WEB_DIR="$DEVBOX_PROJECT_ROOT/devbox.d/web"
CONF="$WEB_DIR/conf.php"
CONF_BAK="$WEB_DIR/conf.php.functest_bak"

# Back up the live conf.php (only if a backup doesn't already exist).
if [ ! -f "$CONF_BAK" ]; then
	cp "$CONF" "$CONF_BAK"
fi

# Write a test conf.php pointing at the test database.
cat > "$CONF" <<-PHP
<?php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3307');
define('DB_DATABASE', '$DB');
define('DB_USERNAME', 'jethro');
define('DB_PASSWORD', 'jethro');
PHP

# Run the upgrader from the web directory (JETHRO_ROOT resolves there).
cd "$WEB_DIR"
php upgrades/2026-upgrade-to-2.40-utf8mb4.php
cd "$DEVBOX_PROJECT_ROOT"

# Restore the original conf.php.
mv "$CONF_BAK" "$CONF"
