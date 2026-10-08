# Security

## Reporting a vulnerability

Please do not open a public issue. Report it privately through GitHub's **Report a vulnerability** button on this
repository's **Security** tab. We answer within five working days and keep you informed until it is fixed. Include the
version, what an attacker can do, and the steps or code to reproduce it.

A vulnerability in the Postilio service itself (the API, the portal, SMTP) is reported the same way; we pass it on.

## Supported versions

Only the latest release gets security fixes while the SDK is below 1.0.

## How the SDK handles secrets

- The API key is sent only in the `Authorization` header, to the configured base URL. The SDK never logs it, and keeps it
  out of exception messages and out of `var_dump()` and `print_r()` of the client and of the requests in a trace.
  Keep the key in a secret store or an environment variable, not in your code or repository.
- Run production with `zend.exception_ignore_args = On` (the default of `php.ini-production`), so stack traces hold no
  argument values at all. On PHP 8.2 and later the SDK marks the request as a sensitive parameter, so a trace leaves it
  out either way.
- With your own PSR-18 client, its exceptions keep the request, `Authorization` header included, in `getRequest()`.
  The SDK therefore does not pass such an exception on: its `TransportException` takes over only the message and the
  class name. Your client's own logging is your client's.
- The built-in curl transport follows no redirects and speaks only HTTP(S), so the key is never sent to another host or
  over another protocol. Ids are put in the path escaped, and an id of `.`, `..` or nothing is refused, so an id from
  user input cannot point a call elsewhere; an Idempotency-Key must be printable ASCII, so it cannot add a header. Use the default `https://` base URL in production.
- Webhook secrets (`whsec_…`) are only read by `WebhookVerifier`, which compares signatures in constant time and refuses
  a timestamp more than five minutes off. Always verify a delivery before acting on it, and verify the raw body.
- The repository holds no keys. The contract tests and the examples read a test key from environment variables.
