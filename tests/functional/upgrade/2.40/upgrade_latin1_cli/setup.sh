#!/bin/bash -eu
# Verify latin1-to-utf8mb4 CLI upgrade path.
# Sets up a corrupt-latin1 database, then the spec runs the CLI
# upgrader (2026-upgrade-to-2.40-utf8mb4.php) against it.
set -o pipefail

DB=jethro_functest_upgrade_latin1_cli
DUMP="$DEVBOX_PROJECT_ROOT/tests/functional/testdata/demodata-latin1_containing_utf8-2.38.0.sql.gz"

jethro_db_init --charset=latin1 --collation=latin1_swedish_ci --db="$DB"
zcat "$DUMP" | mariadb_rename --database "$DB" | mariadb_as_root --default-character-set=binary "$DB"

# Pre-upgrade validation: tables should be latin1.
mariadb "$DB" -sNBe "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB' AND TABLE_COLLATION LIKE 'latin1_swedish_ci';" | grep -q . \
	|| { echo "FAIL: Expected latin1 tables pre-upgrade"; exit 1; }
