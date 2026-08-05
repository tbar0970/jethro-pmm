#!/bin/bash -eu

fail() { echo >&2 "$*"; exit 1; }
set -o pipefail
set -x
mariadb_as_root -e "drop database jethro;"
jethro_db_load --dump=demodata-utf8mb3-2.38.0.sql.gz --db=jethro
mariadb jethro -e "delete from setting where symbol='NEEDS_UTF8MB4_UPGRADE';"
cd /home/jethro/code/current
mariadb jethro -e '\. /tmp/showcollations.sql' | grep -q utf8mb3 || fail "Expected utf8mb3 initially"  # Expect utf8mb3
exit
cd /home/jethro/code/current/devbox.d/web
./upgrades/2026-upgrade-to-2.40-utf8mb4.php
if mariadb jethro -e '\. /tmp/showcollations.sql' | grep utf8mb3; then
	echo >&1 "utf8mb3 tables not fixed"
	exit 1
else
	echo "jethro db is correctly utf4mb4"
fi
