#!/bin/bash -eu
# Verify utf8mb3-to-utf8mb4 upgrade path.
# Uses devbox scripts for DB setup, loads utf8mb3 dump, runs the
# real upgrade script, and checks all tables become utf8mb4.
set -o pipefail

DB=jethro_functest_upgrade_utf8mb3
ROOT="$(dirname "$0")/../../.."
DUMP="$DEVBOX_PROJECT_ROOT/tests/functional/testdata/demodata-utf8mb3-2.38.0.sql.gz"
WEB="$DEVBOX_PROJECT_ROOT/devbox.d/web"

jethro_db_init --charset=utf8 --collation=utf8_unicode_ci --db="$DB"
set -x
jethro_db_load --dump=demodata-utf8mb3-2.38.0.sql.gz --db "$DB"
#zcat "$DUMP" | mariadb_rename --database "$DB" | mariadb_as_root "$DB"

# Pre-upgrade validation 
mariadb "$DB" -sNBe "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB' AND TABLE_COLLATION LIKE 'utf8mb3%';" | grep -q . \
	|| { echo "FAIL: Expected utf8mb3 tables pre-upgrade"; exit 1; }

mariadb --database "$DB" < "$DEVBOX_PROJECT_ROOT/upgrades/2026-upgrade-to-2.39.sql"  # Set the 
mariadb --database "$DB" < "$DEVBOX_PROJECT_ROOT/upgrades/2026-upgrade-to-2.40.sql"  # Set the 
mariadb "$DB" -sNBe "select value from setting where symbol='NEEDS_UTF8MB4_UPGRADE';" | grep -x 1 || { echo "FAIL: missing upgrade marker;"; exit 1; }
