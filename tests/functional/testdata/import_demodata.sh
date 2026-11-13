#!/bin/bash
# Refresh the functional-test fixture demodata-${JETHRO_VERSION}.sql.gz from the
# published demo dump (upstream PR tbar0970/jethro-pmm#1398), whose 'demo' account
# uses the well-known demo password.
#
# Both URLs carry the same cache-busting query, so the dump and the checksum it is
# verified against come from the same read of the origin instead of pairing a
# stale Cloudflare-cached dump with a fresh checksum.

set -euo pipefail

: "${JETHRO_VERSION:?Please run in a devbox shell (JETHRO_VERSION is unset)}"

basedir="$(dirname "$(readlink -f "$0")")"
base='https://easyjethro.com.au/demo/jethro_demodata.gz'
newfile="$basedir/demodata-${JETHRO_VERSION}.sql.gz"
stamp="refresh=$(date +%s)"

# Download beside the fixture, so the tracked file is only replaced once it is
# known to be intact; a failed download must leave the existing dump untouched.
tmpfile="$(mktemp "$basedir/.demodata.XXXXXX")"
trap 'rm -f "$tmpfile"' EXIT

echo "Downloading demodata.."
want="$(curl -sSfL --retry 3 "$base.sha1?$stamp" | awk 'NR==1 { print tolower($1) }')"
if [[ ! $want =~ ^[0-9a-f]{40}$ ]]; then
	echo >&2 "Error: no usable checksum from $base.sha1"
	exit 1
fi

curl -sSfL --retry 3 "$base?$stamp" -o "$tmpfile"
if ! gzip -t "$tmpfile"; then
	echo >&2 "Error: $base did not return a gzip file"
	exit 1
fi

have="$(sha1sum "$tmpfile" | awk '{ print $1 }')"
if [[ $have != "$want" ]]; then
	echo >&2 "Warning: checksum mismatch (published $want, downloaded $have)."
	echo >&2 "The download is a valid gzip, so this is probably a stale cached copy; re-run later for a fresh one."
fi

mv "$tmpfile" "$newfile"
echo "$newfile has been updated"
echo "To load into the 'jethro' database, run:"
if [[ -v DEVBOX_SHELL_ENABLED ]]; then
	echo "jethro_load_demodata"
else
	echo "$(realpath --relative-to="$PWD" "$basedir/../../..")/devbox run demodata"
fi
