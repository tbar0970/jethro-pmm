#!/bin/bash -eu

fail() { echo >&2 "$*"; exit 1; }

set -x
mariadb_recreate_db --charset=latin1 --collation=latin1_swedish_ci jethro
SQL="tests/functional/testdata/demodata-latin1_containing_utf8-2.38.0.sql.gz"
zcat "$SQL" | grep 'Cãlvín' || fail "Could not find word in SQL"
{ zcat "$SQL" | mariadb_rename -d jethro | mariadb --default-character-set=binary jethro; } || fail "Failed to load SQL dump"
out="$(mariadb --default-character-set=utf8mb4 -N jethro -e 'select HEX(family_name) from family where id=2;')"
[[ $out = 43C3A36C76C3AD6E ]] || fail "Got $out expected 43C3A36C76C3AD6E (corrupt: raw UTF-8 bytes in latin1 column)"
cd devbox.d/web
./upgrades/2026-upgrade-to-2.40-utf8mb4.php
