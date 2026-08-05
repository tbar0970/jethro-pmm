#!/bin/bash -eu

# Illustrate loading a latin1 database, then upgrading it to utf8mb4 via CLI

fail() { echo >&2 "$*"; exit 1; }

# Load utf8-in-latin1 table database
mariadb_recreate_db --charset=latin1 --collation=latin1_swedish_ci jethro
zcat tests/functional/testdata/demodata-latin1_containing_utf8-2.38.0.sql.gz | mariadb_rename --database jethro | mariadb --default-character-set=binary jethro
# Set Jethro's transfer encoding to latin1
perl -i -pe 's/utf8mb4/latin1/g' include/jethrodb.php
# Verify chars worked in the latin1 db with latin1 transfer
out="$(./bin/jethrocurl 'http://localhost:8081/?view=persons__list_all' | htmlq '#body > form > table > tbody > tr:nth-child(1) > td.nowrap')"
echo "$out" | grep -F 'Jõão&nbsp;Cãlvín' || fail "Wrong text in latin1 db with latin1 transfer encoding. Expected Jõão&nbsp;Cãlvín got $out"

# Change directory so that the 'jethro' database gets affected
cd devbox.d/web
# Upgrade chars
./upgrades/2026-upgrade-to-2.40-utf8mb4.php
cd ../..
# Set Jethro's transfer encoding to utf8mb4
perl -i -pe 's/latin1/utf8mb4/g' include/jethrodb.php
out="$(./bin/jethrocurl 'http://localhost:8081/?view=persons__list_all' | htmlq '#body > form > table > tbody > tr:nth-child(1) > td.nowrap')"
echo "$out" | grep -F 'Jõão&nbsp;Cãlvín' || fail "The CLI upgrader ran but didn't work. Expected Jõão&nbsp;Cãlvín got $out"
# Verify chars worked in the latin1 db with latin1 transfer
echo "The CLI upgrader worked!"
