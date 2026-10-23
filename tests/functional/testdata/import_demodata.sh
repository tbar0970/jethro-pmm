#!/bin/bash
# Refresh the demo data ('devbox run demodata') source file demodata-${JETHRO_VERSION}.sql.gz with data from online (https://github.com/tbar0970/jethro-pmm/pull/1398), with 'demo' password set to well-known password qfntt7eYuwHs123

set -euo pipefail


: ${JETHRO_VERSION:?Please run in a devbox shell (JETHRO_VERSION is unset)}

basedir="$(dirname "$(readlink -f "$0")")"

echo "Downloading demodata.."
curl -L 'https://easyjethro.com.au/demo/jethro_demodata.gz' -o "$basedir/demodata-${JETHRO_VERSION}.sql.gz"
echo "$basedir/demodata-${JETHRO_VERSION}.sql.gz has been updated"
echo "To load into the 'jethro' database, run:"
if [[ -v DEVBOX_SHELL_ENABLED ]]; then
	echo "jethro_load_demodata"
else
	echo "$(realpath --relative-to="$PWD" "$basedir/../../..")/devbox run demodata"
fi
