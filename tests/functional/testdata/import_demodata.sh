#!/bin/bash
# Refresh the demo data ('devbox run demodata') source file demodata-${JETHRO_VERSION}.sql.gz with data from online (https://github.com/tbar0970/jethro-pmm/pull/1398), with 'demo' password set to well-known password qfntt7eYuwHs123

set -euo pipefail

: ${JETHRO_VERSION:?Please run in a devbox shell (JETHRO_VERSION is unset)}

echo "Downloading demodata.."
curl -s 'https://easyjethro.com.au/demo/jethro_demodata.gz' | \
	gunzip | \
	newhash='$2y$12$PsksXCunAWy9nzhr5MwzY.LXrEhbf61vQr34kV3tTFuNFoOia7OT2' perl -pe 's|\$2y\$12\$X8XetbagijDXmPmXws7UWe/3o5u\.jJ8PIVIjFnuLscBDueBFrLeHS|$ENV{newhash}|' \
	| gzip > "demodata.new.gz"
gzip -t demodata.new.gz || { echo >&2 "Download failed (demodata.new.gz is not gzip)"; exit 1; }
mv demodata.new.gz "demodata-${JETHRO_VERSION}.sql.gz"
echo "demodata-${JETHRO_VERSION}.sql.gz has been updated"
echo "To load into the 'jethro' database, run: devbox run demodata"
