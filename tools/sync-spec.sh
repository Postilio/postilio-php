#!/usr/bin/env bash
# Copies the API's /v1 document from a checkout of the Postilio platform repository into spec/, writes its fingerprint,
# and checks that fingerprint against the one the platform keeps in docs/openapi.sha256.
# Usage: tools/sync-spec.sh [path to the platform repository, ../Postilio by default]
set -euo pipefail
cd "$(dirname "$0")/.."

platform="${1:-../Postilio}"
cp "$platform/openapi/Postilio.Api.json" spec/openapi-v1.json
sha256sum spec/openapi-v1.json | cut -d' ' -f1 > spec/openapi-v1.sha256

expected=$(tr -d '[:space:]' < "$platform/docs/openapi.sha256")
actual=$(cat spec/openapi-v1.sha256)
if [ "$expected" != "$actual" ]; then
    echo "The copied document ($actual) is not the one the platform's docs describe ($expected)." >&2
    exit 1
fi
echo "spec/openapi-v1.json is now $actual. Run composer test: the spec tests name every difference."
