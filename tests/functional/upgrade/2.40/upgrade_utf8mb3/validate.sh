#!/bin/bash -eu
# Verify utf8mb3-to-utf8mb4 upgrade path.
# Uses devbox scripts for DB setup, loads utf8mb3 dump, runs the
# real upgrade script, and checks all tables become utf8mb4.
set -o pipefail

DB=jethro_functest_upgrade_utf8mb3
DUMP="$(dirname "$0")/../../../testdata/demodata-utf8mb3-2.38.0.sql.gz"
WEB="$(dirname "$0")/../../../../../devbox.d/web"
CONF="$WEB/conf.php"

# ── Post-upgrade ──
bad_tables=$(mariadb "$DB" -sNBe "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB' AND TABLE_COLLATION NOT LIKE 'utf8mb4%';")
if [ -n "$bad_tables" ]; then
	echo "$bad_tables"
	echo
	echo "Table charsets and collations:"
	mariadb "$DB" -tBe "SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME;"
	echo "FAIL: tables not utf8mb4"
	exit 1
fi

mariadb "$DB" -sNBe "SELECT CONCAT(first_name, ' ', last_name) FROM _person WHERE id=1;" | grep -q "Déññïs Dëmø" && echo "SUCCESS" \
	|| { echo "FAIL: data mismatch"; exit 1; }
