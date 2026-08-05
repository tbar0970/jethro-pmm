#!/bin/bash -eu

# Illustrate loading a latin1 database, then upgrading it to utf8mb4 via web

fail() { echo >&2 "$*"; exit 1; }

# Load utf8-in-latin1 table database
jethro_db_load --dump=demodata-latin1_containing_utf8-2.38.0.sql.gz --db=jethro
# Set Jethro's transfer encoding to latin1
perl -i -pe 's/utf8mb4/latin1/g' include/jethrodb.php
# Verify chars worked in the latin1 db with latin1 transfer
./bin/jethrocurl 'http://localhost:8081/?view=persons__list_all' | htmlq '#body > form > table > tbody > tr:nth-child(1) > td.nowrap' | grep -F 'Jõão&nbsp;Cãlvín' || fail "Wrong text in latin1 db with latin1 transfer encoding"

# Set Jethro's transfer encoding to utf8mb4
perl -i -pe 's/latin1/utf8mb4/g' include/jethrodb.php
./bin/jethrocurl 'http://localhost:8081/' | grep -F '49 table(s) converted to utf8mb4_unicode_ci' || fail "Expected to see upgrader output"

./bin/jethrocurl 'http://localhost:8081/?view=persons__list_all' | htmlq '#body > form > table > tbody > tr:nth-child(1) > td.nowrap' | grep -F 'Jõão&nbsp;Cãlvín' || fail "The upgrader ran but didn't work"
# Verify chars worked in the latin1 db with latin1 transfer
echo "The in-app upgrader worked!"
