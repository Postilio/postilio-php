# Contributing

Thank you for helping. Open an issue before a large change, so we can agree on the approach first.

## Build and test

You need PHP 8.1 or later with `ext-curl`, and Composer.

```sh
./build.sh
```

It validates `composer.json`, installs, and runs everything a pull request must pass:

| Command | What |
|---|---|
| `composer cs` | code style ([PER Coding Style](https://www.php-fig.org/per/coding-style/), php-cs-fixer); `composer cs:fix` fixes it |
| `composer stan` | PHPStan at the highest level, over `src`, `tests` and `examples`, for PHP 8.1 to 8.5 |
| `composer test` | PHPUnit: unit tests with a fake PSR-18 client, the curl transport against a local `php -S`, the models against the payloads in the docs, the spec tests |

It then runs the examples (they skip the API without a key) and checks the package as composer downloads it (a
`git archive` of the last commit): only `src/`, `composer.json`, the license and the docs, and nothing that looks like
a key. Run it before you open a pull request.

To run the tests on another PHP version, in the official PHP image with podman or docker:

```sh
tools/test-in-container.sh 8.1
```

`composer mutation` runs [Infection](https://infection.github.io/) over `src` (it needs pcov or Xdebug). Look at every
mutant that escapes: either a test is missing, or the code it changed is not needed.

## Conventions

- Code, comments, docs, commits and pull requests in English.
- Commits: `<type>(<scope>): <subject>`, imperative and lowercase (`feat`, `fix`, `docs`, `test`, `refactor`, `chore`).
  Signed commits are welcome.
- One class per file. Models are named exactly as the schemas in the OpenAPI document, with the same fields; they are
  immutable (readonly properties) and read answers in `fromArray()`.
- A model reads its fields into an array before `new self(...$fields)`, and code in general evaluates anything that may
  throw before `new`: on PHP 8.1, an exception thrown while the arguments of `new` are evaluated crashes the process when
  the class has readonly properties.
- A status, type or reason is a backed enum in `Postilio\Enum`, and a model field holding one is `Enum|string`, so a
  value the API adds later does not break anyone.
- Every behaviour has one test; vary the input with a data provider rather than writing another test.
- No new runtime dependencies: the SDK needs nothing but the PSR interfaces, so that it fits any framework and plug-in.
- Add a line to `CHANGELOG.md` under *Unreleased*.

## The OpenAPI document

The client is written by hand, and held to the API by tests rather than generated from it:

- `spec/openapi-v1.json` is a copy of the API's `/v1` document (`openapi/Postilio.Api.json` in the Postilio platform
  repository, also served at `/openapi/v1.json`). `spec/openapi-v1.sha256` is its fingerprint, the same value the
  platform keeps in `docs/openapi.sha256`.
- `SpecTest` fails when the client and the copy disagree: a method per `operationId`, and a model per schema with the
  same fields, types and nullability. With a checkout of the platform next to this one (`../Postilio`, or
  `POSTILIO_PLATFORM_DIR`), it also fails when the copy is not the one the platform's docs describe, and when
  `ErrorCode` differs from the codes in the platform's `docs/errors.md`.
- `ContractTest` runs against a running Postilio; one of its tests compares the server's document with the copy.

When the API changes:

1. `tools/sync-spec.sh [path to the platform repository]` copies the document, writes its fingerprint and checks it
   against the platform's `docs/openapi.sha256`.
2. Run the tests; `SpecTest` names every difference. Follow them in the models and `PostilioClient`.
3. Read the changed guides (errors, delivery status, idempotency, webhooks) for what the document does not show, such as
   a new error code (`ErrorCode`), a reason (`EmailEventReason`) or a retry rule. New payloads from the docs go in
   `tests/Fixtures` (see its README).
4. Add the change to `CHANGELOG.md`; a removed or renamed member is a breaking change.

## Contract tests and examples against the API

The contract tests are skipped unless these environment variables are set:

| Variable | Value |
|---|---|
| `POSTILIO_CONTRACT_BASE_URL` | The API, such as `http://localhost:26299` for a local environment |
| `POSTILIO_CONTRACT_API_KEY` | A **test** key (`pk_test_…`) with `emails:send` and `emails:read`; nothing is delivered |
| `POSTILIO_CONTRACT_FROM` | An address on a verified domain of the key's project |

```sh
vendor/bin/phpunit --group contract
```

The examples call the API when `POSTILIO_API_KEY` and `POSTILIO_FROM` are set (and `POSTILIO_BASE_URL` for another
environment; `POSTILIO_TEST_TO` for the test email); otherwise they print that they skipped. Create keys in the portal
under **Keys & SMTP** and keep them out of the repository, in your shell or a file outside it.
