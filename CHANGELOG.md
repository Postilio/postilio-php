# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[semantic versioning](https://semver.org/).

## [Unreleased]

Follows the `/v1` document with fingerprint `b0793fcb17cf6bead36c6e703c4d3da3a3171ba5e4076495a55c62cc950ee881`.

### Added

- `EmailEvent::$async` and `WebhookEventData::$async`: true for a bounce the recipient's mail server reported after
  delivery, which follows the `delivered` event; `EmailEventReason::AsyncBounce` is its reason.
- `ErrorCode::AttachmentTypeNotAllowed` (422, an executable attachment), `ErrorCode::ProbationDailyLimitReached` and
  `ErrorCode::ProbationRateLimitReached` (429, the caps in the first weeks after the sandbox).

### Changed

- `listDomains()` and `getDomain()` need the new `domains:read` scope or `domains:manage`, and work with a test key.
- `sendAt` is a `date-time` in the document now, which `SendEmailRequest::$sendAt` already accepted; a time without
  an offset is refused with a 400. The client sends what it sent before.

## [0.1.0-alpha.1]

First version, against the alpha of the Postilio API (`/v1`, OpenAPI fingerprint
`6b72ff75628f6b8ee9b7cd3abc65cf5be7bec0037c5e94c7e7e8350684c73ed3`).

### Added

- `PostilioClient` with a method per operation of `/v1`: send email (cc, bcc, headers such as `List-Unsubscribe`,
  attachments, scheduled sending), cancel a scheduled email, send a test email, get an email with its events, domains
  with their DMARC check, suppressions, webhook endpoints and deliveries, and usage.
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
