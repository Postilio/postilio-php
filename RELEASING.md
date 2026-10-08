# Releasing

The package is published on [Packagist](https://packagist.org) as `postilio/postilio-php`. Packagist reads the version
from the git tag, so `composer.json` holds no `version` field; `PostilioClient::VERSION` (sent in the `User-Agent`) must
match the tag. Nothing is published yet.

## Once, before the first release

1. **The repository** `Postilio/postilio-php` on GitHub, public, with this history pushed and `main` protected (pull
   requests only, signed commits).
2. **A Packagist account** for the owner, with two-factor authentication, and the vendor name `postilio` claimed by
   submitting the repository URL under *Submit*. Packagist only reads the public repository; it needs no secret of ours.
3. **Automatic updates**: Packagist's GitHub integration (log in to Packagist with GitHub) adds the webhook itself. Without
   it, add a GitHub webhook to `https://packagist.org/api/github?username=<packagist user>` with the account's API token,
   content type JSON, push events only.

## Every release

1. On `main`, move the *Unreleased* entries in `CHANGELOG.md` to a new version heading with the date, and set
   `PostilioClient::VERSION`. Semantic versioning: a removed or changed public member is a major version (a minor one
   while below 1.0); a new member a minor; a fix a patch. Pre-releases: `0.2.0-alpha.1`. Classes in `Postilio\Internal`
   and members marked `@internal` are not public API.
2. Run `./build.sh` locally, `tools/test-in-container.sh` for every supported PHP version (8.1 to 8.5), and the
   contract tests against a test environment (see `CONTRIBUTING.md`).
3. Commit (`chore: release 0.2.0`) through a pull request, then tag the merged commit signed and push the tag:

   ```sh
   git tag -s v0.2.0 -m "v0.2.0"
   git push origin v0.2.0
   ```

4. Packagist picks the tag up within a minute (or press *Update* on the package page). Create the GitHub release for the
   tag with the changelog's section, marked as a pre-release for a version with a suffix.
5. Check the package page: the version, the PHP requirement and the license.

A tag is never moved or reused: a broken release gets a new patch version.

## Continuous integration

There is no workflow in this repository yet; whether to add one is the owner's decision, since Actions minutes cost
money for private repositories. A workflow for this repository would, on every pull request and push to `main`:

| Job | Steps |
|---|---|
| `build` (PHP 8.4, ubuntu-latest) | checkout, `shivammathur/setup-php` with `curl` and `pcov`, `./build.sh` |
| `test` (matrix PHP 8.1, 8.2, 8.3, 8.4, 8.5) | checkout, setup-php, `composer update --no-interaction`, `vendor/bin/phpunit --exclude-group contract` |
| `lowest` (PHP 8.1) | as `test`, with `composer update --prefer-lowest` |
| `mutation` (PHP 8.4, optional) | `composer mutation` with a minimum covered MSI of 95 % |

Actions pinned to a commit SHA, with the version as a comment. The contract tests stay out of CI: they need a running
Postilio and a test key. A release workflow is not needed: Packagist reads the tag. Never attach a self-hosted runner
to a public repository: a pull request from a fork would run its code on that machine.
