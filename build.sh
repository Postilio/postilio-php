#!/usr/bin/env bash
# The local check before a push or a release: the package metadata, code style, static analysis at the highest level,
# the tests, the examples, and the contents of the package as composer would download it. The contract tests run too
# when POSTILIO_CONTRACT_* is set, and the examples call the API when POSTILIO_API_KEY is set (see CONTRIBUTING.md);
# otherwise both are skipped.
set -euo pipefail
cd "$(dirname "$0")"

composer validate --strict --no-check-lock
composer install --no-interaction --no-progress
composer cs
composer stan
composer test
for example in examples/*.php; do
    php "$example" > /dev/null
done

# The package of the last commit as composer downloads it (a git archive, so .gitattributes' export-ignore applies):
# only the code, the license and the docs for its users, and no API key or webhook secret.
work=$(mktemp -d)
trap 'rm -r -- "$work"' EXIT
git archive --format=tar --output="$work/package.tar" HEAD
tar -tf "$work/package.tar" | grep -v '/$' | sort > "$work/files"
unexpected=$(grep -v -E '^(src/.+\.php|composer\.json|LICENSE|README\.md|CHANGELOG\.md|SECURITY\.md)$' "$work/files" || true)
if [ -n "$unexpected" ]; then
    echo "The package holds files it should not:" >&2
    echo "$unexpected" >&2
    exit 1
fi
mkdir "$work/package"
tar -xf "$work/package.tar" -C "$work/package"
if grep -r -l -E 'pk_(live|test)_[A-Za-z0-9]{32}|whsec_[A-Za-z0-9+/=]{24,}' "$work/package"; then
    echo "The package holds something that looks like a key or a webhook secret." >&2
    exit 1
fi
echo "OK: $(wc -l < "$work/files") files in the package, version $(php -r 'require "vendor/autoload.php"; echo Postilio\PostilioClient::VERSION;')"
