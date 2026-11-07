#!/bin/bash -eu
# =============================================================================
# Upgrade test: 2.39 SMS data -> 2.40 SMS schema and note migration.
#
# Loads tests/functional/testdata/demodata-2.39.0.sql.gz (the functional-test
# data), resets its SMS objects/settings to the 2.39 state (the dumps were
# generated from a dev instance that already had the 2.40 SMS schema, so the
# 2.40 upgrade would otherwise do nothing), seeds legacy 'SMS Sent' notes,
# applies every upgrade script released after 2.38 in order, and asserts what
# the migration produced. It then repeats the migration on a second database
# with a corrupted gateway response, asserting that the integrity check aborts
# the run and rolls back, and that a re-run after fixing the cause succeeds.
#
# Usage:
#   tests/upgrade/run-upgrade-test.sh                              # TCP, DB_CLI
#   DB_SOCKET=/path/to/mysql.sock tests/upgrade/run-upgrade-test.sh # unix socket
#
# Connection, either:
#   - DB_CLIENT: a full client command, e.g. for CI service containers
#       DB_CLIENT='docker exec -i 1a2b3c4d mysql -uroot -proot'
#     (the engine's own client is then used for every statement), or
#   - DB_CLI (default: mariadb), DB_HOST (127.0.0.1), DB_PORT (3306),
#     DB_USER (root), DB_PASSWORD, or DB_SOCKET instead of host/port.
# Other variables: DB_NAME (base database name, default jethro_upgrade_test),
# UPGRADE_TEST_DUMP (override the dump path).
#
# Needs an account that can create/drop the test databases and load a dump.
# Exits non-zero if any check fails.
# =============================================================================
set -o pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
dump="${UPGRADE_TEST_DUMP:-$repo_root/tests/functional/testdata/demodata-2.39.0.sql.gz}"
db_name="${DB_NAME:-jethro_upgrade_test}"
abort_db="${db_name}_abort"

: "${DB_CLIENT:=}"
: "${DB_CLI:=mariadb}"
: "${DB_SOCKET:=}"
: "${DB_HOST:=127.0.0.1}"
: "${DB_PORT:=3306}"
: "${DB_USER:=root}"
: "${DB_PASSWORD:=}"

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

failures=0

client() {
	if [[ -n "$DB_CLIENT" ]]; then
		local -a cmd
		read -r -a cmd <<< "$DB_CLIENT"
		"${cmd[@]}" "$@"
	else
		local -a conn=(--user="$DB_USER")
		if [[ -n "$DB_SOCKET" ]]; then
			conn+=(--socket="$DB_SOCKET")
		else
			conn+=(--host="$DB_HOST" --port="$DB_PORT" --protocol=TCP)
		fi
		MYSQL_PWD="$DB_PASSWORD" "$DB_CLI" "${conn[@]}" "$@"
	fi
}

# Service containers can accept connections a little after the port opens.
wait_for_server() {
	local i
	for i in $(seq 1 60); do
		if client --batch --skip-column-names --execute='SELECT 1' >/dev/null 2>&1; then
			return 0
		fi
		sleep 2
	done
	echo "The database server is not accepting connections." >&2
	exit 1
}

# sql <database> <query>: one value, no headers.
sql() {
	client --batch --skip-column-names "$1" --execute="$2"
}

ok() { printf 'ok   %s\n' "$1"; }

check() { # check <description> <expected> <actual>
	if [[ "$3" == "$2" ]]; then
		ok "$1"
	else
		printf 'FAIL %s: expected %s, got %s\n' "$1" "$2" "${3:-<empty>}" >&2
		failures=$((failures + 1))
	fi
}

check_sql() { # check_sql <description> <expected> <database> <query>
	check "$1" "$2" "$(sql "$3" "$4")"
}

create_database() {
	client --execute="DROP DATABASE IF EXISTS \`$1\`; CREATE DATABASE \`$1\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
}

load_dump() { # load_dump <database>
	local file="$tmpdir/$1.dump.sql"
	gzip -dc "$dump" > "$file"
	# The dumps name their source database and DEFINER; neutralise both so the
	# data loads into this database as this user. They also pin NO_AUTO_CREATE_USER
	# in sql_mode, which MariaDB has but MySQL 8 rejects.
	LC_ALL=C sed -E \
		-e 's/DEFINER=`[^`]*`@`[^`]*` //g' \
		-e '/^ALTER DATABASE /d' \
		-e 's/,?NO_AUTO_CREATE_USER//g' \
		"$file" \
	| awk '
		# The dump carries MariaDB-flavoured stored PROCEDUREs (IF/THEN in
		# expressions) that MySQL 8 refuses. Keep the FUNCTIONs: the person views
		# need getCurrentUserID(), and the 2.40 upgrade recreates the views.
		/^DELIMITER ;;$/ { inblock = 1; buf = $0 "\n"; next }
		inblock {
			buf = buf $0 "\n"
			if ($0 == "DELIMITER ;") {
				inblock = 0
				if (buf ~ /CREATE.*FUNCTION /) printf "%s", buf
			}
			next
		}
		{ print }
	' \
	| client "$1"
}

# prepare <database>: 2.39-era data, SMS schema and settings, legacy notes.
prepare() {
	create_database "$1"
	load_dump "$1"
	client "$1" < "$repo_root/tests/upgrade/rewind-to-2.39-sms.sql"
	client "$1" < "$repo_root/tests/upgrade/seed-legacy-sms-notes.sql"
}

# Upgrades released after 2.38, in the order the functional-test loader and the
# docs use (version sort): 2.39, 2.40, 2.40-sms (it needs 2.40's tables).
mapfile -t upgrade_files < <(
	ls "$repo_root"/upgrades/*.sql | sort --version-sort | sed '0,/2\.38\.sql/ d'
)

apply_upgrades() { # apply_upgrades <database>
	local sqlfile
	for sqlfile in "${upgrade_files[@]}"; do
		printf '  %s\n' "${sqlfile#"$repo_root"/}"
		client "$1" < "$sqlfile"
	done
}

assert_migrated() { # assert_migrated <database>
	local db="$1"
	echo "Asserting the migrated data..."
	# Legacy notes: bulk-send stubs deleted, interacted and single-recipient ones kept.
	check_sql "weekly reminder notes deleted" 0 "$db" \
		"SELECT COUNT(*) FROM _abstract_note WHERE details = 'Upgrade test: weekly roster reminder'"
	check_sql "bulk AGM notes deleted" 0 "$db" \
		"SELECT COUNT(*) FROM _abstract_note WHERE details = 'Upgrade test: bulk AGM notice'"
	check_sql "notes left from the seeded set" 4 "$db" \
		"SELECT COUNT(*) FROM _abstract_note WHERE details LIKE 'Upgrade test:%'"
	check_sql "which notes were kept" \
		'Upgrade test: commented bulk message|Upgrade test: custom subject message|Upgrade test: handled bulk message|Upgrade test: single recipient message' \
		"$db" \
		"SELECT GROUP_CONCAT(details ORDER BY details SEPARATOR '|') FROM _abstract_note WHERE details LIKE 'Upgrade test:%'"
	check_sql "seeded notes keep their person_note rows" 4 "$db" \
		"SELECT COUNT(*) FROM _abstract_note an JOIN person_note pn ON pn.id = an.id WHERE an.details LIKE 'Upgrade test:%'"
	check_sql "commented note kept its comment" 1 "$db" \
		"SELECT COUNT(*) FROM note_comment c JOIN _abstract_note an ON an.id = c.noteid WHERE an.details = 'Upgrade test: commented bulk message'"

	# Sends and deliveries: a repeated text is one send per weekly batch, not one
	# per note and not N x N deliveries.
	check_sql "sends created" 8 "$db" "SELECT COUNT(*) FROM sms"
	check_sql "deliveries created" 22 "$db" "SELECT COUNT(*) FROM _smsdelivery"
	check_sql "weekly reminder sends (3 weeks)" 3 "$db" \
		"SELECT COUNT(*) FROM sms WHERE body = 'Upgrade test: weekly roster reminder'"
	check_sql "weekly reminder send times differ" 3 "$db" \
		"SELECT COUNT(DISTINCT created) FROM sms WHERE body = 'Upgrade test: weekly roster reminder'"
	check_sql "bulk AGM sends (10 notes, one second)" 1 "$db" \
		"SELECT COUNT(*) FROM sms WHERE body = 'Upgrade test: bulk AGM notice'"
	check_sql "bulk AGM deliveries" 10 "$db" \
		"SELECT COUNT(*) FROM _smsdelivery sd JOIN sms s ON s.id = sd.sms_id WHERE s.body = 'Upgrade test: bulk AGM notice'"
	check_sql "no person appears twice in one send" 0 "$db" \
		"SELECT COUNT(*) FROM (SELECT 1 FROM _smsdelivery GROUP BY sms_id, personid HAVING COUNT(*) > 1) dupes"
	check_sql "deliveries carry the gateway's provider" '5centsmsv4' "$db" \
		"SELECT DISTINCT provider FROM _smsdelivery"
	check_sql "deliveries are marked sent" 'sent' "$db" "SELECT DISTINCT status FROM _smsdelivery"
	check_sql "sends keep their 2.39 sender" 8 "$db" \
		"SELECT COUNT(*) FROM sms s JOIN staff_member sm ON sm.id = s.sender"

	# Notes linked to their send.
	check_sql "sms_note links" 4 "$db" "SELECT COUNT(*) FROM sms_note"
	check_sql "kept notes linked to their delivery" 4 "$db" \
		"SELECT COUNT(*) FROM sms_note sn JOIN _abstract_note an ON an.id = sn.note_id WHERE an.details LIKE 'Upgrade test:%'"
	check_sql "kept note's link points at its own send" 4 "$db" \
		"SELECT COUNT(*) FROM sms_note sn JOIN _abstract_note an ON an.id = sn.note_id JOIN sms s ON s.id = (SELECT sd.sms_id FROM _smsdelivery sd WHERE sd.id = sn.smsdelivery_id) WHERE an.details LIKE 'Upgrade test:%' AND s.body = an.details"

	# Settings 2026-upgrade-to-2.40.sql rewrites.
	check_sql "custom note subject left alone" 'Text message log' "$db" \
		"SELECT value FROM setting WHERE symbol = 'SMS_SAVE_TO_NOTE_SUBJECT'"
	check_sql "'+61' international prefix kept" '+61' "$db" \
		"SELECT value FROM setting WHERE symbol = 'SMS_INTERNATIONAL_PREFIX'"
	check_sql "blank SMS_HTTP_ setting deleted" 0 "$db" \
		"SELECT COUNT(*) FROM setting WHERE symbol = 'SMS_HTTP_HEADER_TEMPLATE'"
	check_sql "legacy SMS_SEND_LOGFILE removed" 0 "$db" \
		"SELECT COUNT(*) FROM setting WHERE symbol = 'SMS_SEND_LOGFILE'"
	check_sql "unregistered 'Jethro' 2FA sender cleared" '' "$db" \
		"SELECT value FROM setting WHERE symbol = '2FA_SENDER_ID'"
	check_sql "2FA_SENDER_ID kept its row" 1 "$db" \
		"SELECT COUNT(*) FROM setting WHERE symbol = '2FA_SENDER_ID'"
	check_sql "2FA_SENDER_ID note updated" 1 "$db" \
		"SELECT COUNT(*) FROM setting WHERE symbol = '2FA_SENDER_ID' AND note LIKE 'ACMA-registered sender ID%'"
	check_sql "SMS_SENDER added" 1 "$db" \
		"SELECT COUNT(*) FROM setting WHERE symbol = 'SMS_SENDER'"
}

assert_schema_created() { # assert_schema_created <database>
	local db="$1"
	echo "Asserting the 2.40 SMS schema was really created by the upgrade..."
	local t
	for t in sms _smsdelivery sms_note sms_registered_sender; do
		check_sql "table $t" 1 "$db" \
			"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$t'"
	done
}

echo "Upgrade test with client: ${DB_CLIENT:-$DB_CLI} ${DB_SOCKET:-$DB_HOST:$DB_PORT}"
echo "Dump: $dump"
wait_for_server
client --batch --skip-column-names --execute="SELECT CONCAT(@@version, ' (', @@sql_mode, ')')" | sed 's/^/Server: /'

# The functional-test fixture must not carry the 2.40 SMS schema, or the
# functional tests' loader (devbox.d/bin/jethro_load_demodata) creates it from
# the dump instead of by running 2026-upgrade-to-2.40*.sql.
fixture="$repo_root/tests/functional/testdata/demodata-2.40-DEV.sql.gz"
if [[ -f "$fixture" ]]; then
	fixture_sms_objects="$(gzip -dc "$fixture" | LC_ALL=C grep -cE 'CREATE TABLE `(sms|_smsdelivery|sms_note|sms_registered_sender)`' || true)"
	check "functional-test fixture has no 2.40 SMS schema" 0 "$fixture_sms_objects"
fi

echo
echo "=== Main run into $db_name ==="
prepare "$db_name"

apply_upgrades "$db_name"

echo
assert_schema_created "$db_name"
assert_migrated "$db_name"

echo
echo "Re-running the note migration (must change nothing)..."
client "$db_name" < "$repo_root/upgrades/2026-upgrade-to-2.40-sms.sql"
assert_migrated "$db_name"

echo
echo "=== Integrity check: corrupted gateway response must abort ==="
prepare "$abort_db"
upgrade_2_40="${repo_root}/upgrades/2026-upgrade-to-2.40.sql"
client "$abort_db" < "${repo_root}/upgrades/2026-upgrade-to-2.39.sql"
client "$abort_db" < "$upgrade_2_40"
# Simulate a gateway/schema problem that changes the stored body: one text the
# migration has to reproduce comes out different, so the check must fire.
client "$abort_db" --execute="DROP TRIGGER IF EXISTS upgrade_test_corrupt;
CREATE TRIGGER upgrade_test_corrupt BEFORE INSERT ON sms FOR EACH ROW
SET NEW.body = IF(NEW.body = 'Upgrade test: weekly roster reminder', CONCAT(NEW.body, ' (corrupted)'), NEW.body);"

if client "$abort_db" < "$repo_root/upgrades/2026-upgrade-to-2.40-sms.sql" > "$tmpdir/abort.log" 2>&1; then
	printf 'FAIL the migration ran despite a changed body\n' >&2
	failures=$((failures + 1))
else
	if grep -q 'SMS note migration aborted' "$tmpdir/abort.log"; then
		ok "migration aborted with the integrity message"
	else
		printf 'FAIL migration failed without the integrity message:\n' >&2
		cat "$tmpdir/abort.log" >&2
		failures=$((failures + 1))
	fi
fi
check_sql "abort rolled back the sends" 0 "$abort_db" "SELECT COUNT(*) FROM sms"
check_sql "abort rolled back the deliveries" 0 "$abort_db" "SELECT COUNT(*) FROM _smsdelivery"
check_sql "abort rolled back the links" 0 "$abort_db" "SELECT COUNT(*) FROM sms_note"
check_sql "abort left every note in place" 22 "$abort_db" \
	"SELECT COUNT(*) FROM _abstract_note WHERE details LIKE 'Upgrade test:%'"

echo
echo "Re-running after the fix (must migrate, with no leftovers)..."
client "$abort_db" --execute="DROP TRIGGER IF EXISTS upgrade_test_corrupt;"
client "$abort_db" < "$repo_root/upgrades/2026-upgrade-to-2.40-sms.sql"
assert_migrated "$abort_db"
check_sql "no migration procedure left behind" 0 "$db_name" \
	"SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema = '$db_name' AND routine_name = '_upgrade_sms_assert'"

echo
if [[ $failures -gt 0 ]]; then
	echo "$failures check(s) FAILED" >&2
	exit 1
fi
echo "All checks passed."
