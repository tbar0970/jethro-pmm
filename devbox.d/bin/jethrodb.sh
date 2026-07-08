# Shared helpers for devbox MariaDB admin scripts.
# Source this file; do not execute directly.

REPO_ROOT="${REPO_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"

fail() { echo >&2 "$*"; exit 1; }

validate_working() {
	: "${MYSQL_UNIX_PORT:?run inside devbox shell (devbox info mariadb)}"
	devbox services ls |& grep -q mariadb.*Running || fail "mariadb is not running. Start with 'devbox services up -b'"
	[[ "root@localhost" == "$(mariadb_as_root -sNBe "select current_user();")" ]] || fail "Failed to connect to MariaDB as root (via socket)"
	[[ $(mariadb_as_root -sNBe "select @@datadir") =~ .*/\.devbox/virtenv/mariadb/data/ ]] || fail "Unexpectedly connected to the wrong MariaDB??"
}

# Extract and display help from #? comment lines at the top of the calling script.
help() { sed -n '/^#?/s/^#? \?//p' "$0"; exit; }
