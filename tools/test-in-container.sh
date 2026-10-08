#!/usr/bin/env bash
# Runs the tests and the examples on another PHP version, in the official php:<version>-cli-alpine image (podman or
# docker), on a copy of the working tree; this checkout and its vendor/ stay as they are. Composer resolves the dev
# dependencies for that version (PHPUnit 11 on PHP 8.2, for instance).
# Usage: tools/test-in-container.sh 8.2
set -euo pipefail
cd "$(dirname "$0")/.."

version="${1:?Usage: tools/test-in-container.sh <php version, such as 8.2>}"
engine=$(command -v podman || command -v docker || { echo "Needs podman or docker." >&2; exit 1; })

"$engine" run --rm --security-opt label=disable -v "$PWD":/src:ro "docker.io/library/php:${version}-cli-alpine" sh -euc '
    curl -fsSL https://getcomposer.org/installer | php -- --quiet --install-dir=/usr/local/bin --filename=composer
    mkdir /app
    tar -C /src --exclude=./vendor --exclude=./build --exclude=./.git -cf - . | tar -C /app -xf -
    cd /app
    composer update --no-interaction --no-progress --quiet
    php -v | head -n 1
    vendor/bin/phpunit --exclude-group contract
    for example in examples/*.php; do php "$example" > /dev/null; done
    echo "OK: PHP $(php -r "echo PHP_VERSION;")"
'
