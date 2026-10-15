# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[semantic versioning](https://semver.org/).

## [Unreleased]

## [0.1.0-alpha.1] - 2026-10-15

First version, against the alpha of the Postilio API (`/v1`, OpenAPI fingerprint
`cdec48841de28808b550091e583aa44c61f8e4334d72bd2066f919a87c5cf1fa`).

### Added

- `PostilioClient` with a method per operation of `/v1`: send email (cc, bcc, headers such as `List-Unsubscribe`,
  attachments, scheduled sending), cancel a scheduled email, send a test email, get an email with its events, domains
  with their DMARC check, suppressions, webhook endpoints and deliveries, and usage.
- Domains with their DMARC check and `platformKey`, the DKIM key record Postilio publishes itself (`PlatformKeyRecord`
  with a `PlatformKeyStatus`). `listDomains()` and `getDomain()` need only the `domains:read` scope (which
  `domains:manage` includes) and work with a test key.
- `EmailEvent::$async` and `WebhookEventData::$async`: true for a bounce the recipient's mail server reported after
  delivery, which follows the `delivered` event; `EmailEventReason::AsyncBounce` is its reason.
- `sendAt` takes a `DateTimeInterface` (sent with its offset) or an ISO 8601 string; a time without an offset is refused
  by the API with a 400.
- An `Idempotency-Key` on every send, made per call unless you pass your own.
- Any PSR-18 client with PSR-17 factories (found by php-http/discovery when installed and left out), or a built-in curl
  transport with timeouts, no redirects and HTTP(S) only. No other dependencies, no global state.
- A `User-Agent` of `postilio-php/<version> php/<version>` on every request.
- Retries, off by default: after a 408, 429, 500, 502, 503, 504 or a network failure, for GETs and sends only, honouring
  `Retry-After` up to `maxRetryDelay`.
- An exception per status with the API's stable error code and message (`ErrorCode` lists every code), the validation
  problems per field, `retryAfter`, the trace id of a server error, and `PlanLimitException` for the plan's limits.
- Immutable models with readonly properties, and backed enums for statuses, event types, reasons and modes; a value the
  SDK does not know yet stays a string.
- `WebhookVerifier` (Standard Webhooks: constant-time comparison, timestamp tolerance, secret rotation) and
  `WebhookEvent::parse()`.
- The API key is never in an exception message or a dump of the client, and the SDK's own requests are redacted in a
  trace that keeps arguments (see SECURITY.md).
- PHP 8.2 to 8.5. MIT license.
