# Shared helpers for devbox MariaDB admin scripts.
# Source this file; do not execute directly.

REPO_ROOT="${REPO_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"

fail() { echo >&2 "$*"; exit 1; }

validate_working() {
	: "${MYSQL_UNIX_PORT:?run inside devbox shell (devbox info mariadb)}"
	devbox services ls |& grep -qP '(mariadb|mysql).*Running' || fail "mariadb is not running. Start with 'devbox services up -b'"
	[[ "root@localhost" == "$(mariadb_as_root -sNBe "select current_user();")" ]] || fail "Failed to connect to database as root (via socket)"
	[[ $(mariadb_as_root -sNBe "select @@datadir") =~ .*/\.devbox/virtenv/.+/data/ ]] || fail "Unexpectedly connected to the wrong (non-devbox) database??"
}

# Extract and display help from #? comment lines at the top of the calling script.
help() { sed -n '/^#?/s/^#? \?//p' "$0"; exit; }

# Parse --option=value and --option value arguments against a spec.
# Usage: parse_opts "VAR=default VAR FLAG:--opt-name" -- "$@"
#
# Each space-separated entry in SPEC:
#   VAR=default           → optional value option, auto-derived --var-name
#   VAR=default:--opt     → optional value option, explicit flag name
#   VAR                   → required value option, auto-derived --var-name
#   FLAG:--opt-name       → boolean flag, sets FLAG=1 (use :-- syntax without =)
#
# Sets each VAR to the parsed value (or its default).
# Boolean flags are set to 1 when present, empty/unset otherwise.
# --help triggers the script's help function and exits.
# After --, remaining positional args go into ARGV array.
parse_opts() {
	local spec="$1"; shift; [[ "$1" == "--" ]] && shift

	local -A opt_map=() flags=()
	for entry in $spec; do
		local req_var="$entry" opt is_flag=false
		if [[ "$entry" == *:--* ]]; then
			opt="${entry##*:}"
			req_var="${entry%:*}"
			# Flag if no = in the part before :-- (e.g. "autoconfirm:--autoconfirm")
			if [[ "$req_var" != *=* ]]; then
				is_flag=true
			fi
		else
			local var_name="${req_var%%=*}"
			opt="--$(echo "$var_name" | tr '[:upper:]_' '[:lower:]-')"
		fi
		local var="${req_var%%=*}"
		local def="${req_var#*=}"
		[[ "$def" == "$req_var" ]] && def=""
		[[ -n "$def" ]] && eval "$var=\"$def\""
		opt_map["${opt#--}"]="$var"
		flags["$var"]="$is_flag"
	done

	ARGV=()
	while [[ $# -gt 0 ]]; do
		case "$1" in
			--help) help; exit 0 ;;
			--) shift; ARGV+=("$@"); break ;;
			--*=*)
				local k="${1%%=*}"; k="${k#--}"; local v="${1#*=}"
				local var="${opt_map[$k]:-}"
				[[ -z "$var" ]] && { echo >&2 "Unknown option: $1"; help; }
				eval "$var=\"$v\""; shift ;;
			--*)
				local k="${1#--}"
				local var="${opt_map[$k]:-}"
				[[ -z "$var" ]] && { echo >&2 "Unknown option: $1"; help; }
				if ${flags[$var]}; then
					eval "$var=1"; shift
				else
					shift
					[[ $# -gt 0 && "$1" != -* ]] || { echo >&2 "Option --$k requires a value"; help; }
					eval "$var=\"$1\""; shift
				fi
				;;
			-*) echo >&2 "Unknown option: $1"; help ;;
			*) ARGV+=("$1"); shift ;;
		esac
	done
}

# Ensure the shared 'jethro' MySQL user exists and has full privileges on a database.
# Safe to call multiple times — uses IF NOT EXISTS.
ensure_jethro_user() {
	local db="$1"
	mariadb_as_root -v <<-EOF
	CREATE USER IF NOT EXISTS 'jethro'@'localhost' IDENTIFIED BY 'jethro';
	GRANT ALL PRIVILEGES ON \`$db\`.* TO 'jethro'@'localhost';
	FLUSH PRIVILEGES;
	EOF
}

# Drop and recreate a database with the given charset and collation.
# Creates the specified user, grants it full privileges, and also ensures
# the shared 'jethro' user has access.
dropcreate_db() {
	local db="$1" db_user="$2" db_pass="$3" charset="$4" collation="$5"
	mariadb_as_root -v <<-EOF
	CREATE USER IF NOT EXISTS '$db_user'@'localhost' IDENTIFIED BY '$db_pass';
	DROP DATABASE IF EXISTS \`$db\`;
	CREATE DATABASE \`$db\` CHARACTER SET $charset COLLATE $collation;
	GRANT ALL PRIVILEGES ON \`$db\`.* TO '$db_user'@'localhost';
	EOF
	ensure_jethro_user "$db"
	echo "Database user created: $db_user"
	echo "Database created: $db"
}
