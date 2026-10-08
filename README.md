# Postilio for PHP

The official PHP client for the [Postilio](https://postilio.eu) API: European transactional email. Send email, read
messages and their events, manage domains, suppressions and webhooks, read your usage, and verify webhook signatures.

PHP 8.1 or later. It depends on nothing but the PSR HTTP interfaces: bring the PSR-18 client your framework already
has, or let it send with curl. No Guzzle, no global state.

> Not published on Packagist yet; the API is in alpha. See [CHANGELOG.md](CHANGELOG.md).

## Install

```sh
composer require postilio/postilio-php
```

## Quickstart

Create an API key in the portal under **Keys & SMTP**. A test key (`pk_test_…`) goes through every check but never
delivers, so start with one. The sender must be on a verified domain of the key's project.

```php
use Postilio\Enum\EmailStatus;
use Postilio\Model\SendEmailRequest;
use Postilio\PostilioClient;

$postilio = new PostilioClient(getenv('POSTILIO_API_KEY') ?: throw new RuntimeException('Set POSTILIO_API_KEY.'));

$sent = $postilio->sendEmail(new SendEmailRequest(
    from: 'Acme <no-reply@mail.example.com>',
    to: ['delivered@simulator.postilio.eu'],
    subject: 'Welcome to Acme',
    text: 'Hi Ada, your account is ready.',
    html: '<p>Hi Ada, your account is ready.</p>',
    tag: 'welcome',
));

$email = $postilio->getEmail($sent->ids[0]);
var_dump($email->status === EmailStatus::Delivered); // true: a test key simulates it at once
```

Create one `PostilioClient` and reuse it. More in the [quick start](docs/quickstart.md) and in [examples/](examples).

## The HTTP client

Without a client of your own, the SDK sends with curl (`ext-curl`), with a 30-second timeout per request and 10 seconds
to connect, no redirects, and HTTP(S) only:

```php
$postilio = new PostilioClient($key, timeout: 15.0, connectTimeout: 5.0);
```

Or pass any [PSR-18](https://www.php-fig.org/psr/psr-18/) client with its [PSR-17](https://www.php-fig.org/psr/psr-17/)
request and stream factories; its own timeouts and proxy settings then apply:

```php
$factory = new Nyholm\Psr7\Factory\Psr17Factory();
$postilio = new PostilioClient($key, $psr18Client, $factory, $factory);
```

With [php-http/discovery](https://packagist.org/packages/php-http/discovery) installed, you may leave out the factories.
Every setting is a constructor argument: nothing global, so two clients with different keys can live side by side.

## Sending

`SendEmailRequest` takes everything `POST /v1/emails` does:

```php
new SendEmailRequest(
    from: 'Acme Support <support@mail.example.com>',
    to: ['ada.lovelace@example.com'],          // each recipient gets a message of its own
    subject: 'Your ticket 4711',
    text: 'Solved: the export works again.',
    html: null,
    tag: 'support',
    replyTo: 'support@example.com',
    attachments: [new EmailAttachment('report.pdf', 'application/pdf', file_get_contents('report.pdf'))],
    cc: ['account-manager@example.com'],       // cc and bcc only with exactly one `to`
    bcc: ['archive@example.com'],
    headers: [
        'List-Unsubscribe' => '<https://example.com/unsubscribe/3f9a1c7e>',
        'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
    ],
    sendAt: new DateTimeImmutable('+1 hour'),  // scheduled; cancel it with cancelEmail()
);
```

Attachments take the file's bytes; the client encodes them. The API checks every field and answers with an exception
when it refuses one (see [Errors](#errors)); the SDK does not repeat those rules. A test key checks `sendAt` but
simulates the message at once, so with a test key nothing waits and `cancelEmail()` throws a `ConflictException`
(`email_not_scheduled`).

`sendTestEmail()` sends one email to an address of your own (a member's, or one confirmed for test mail), to check a
domain end to end.

## Sending safely twice: idempotency

A request can time out after Postilio accepted it. `sendEmail()` therefore always sends an `Idempotency-Key`: one it
makes per call, so its own retries never send twice. Pass your own key to be safe across restarts and queues too:

```php
$postilio->sendEmail($receipt, "order-{$order->id}-receipt");
```

Within 24 hours, the same key with the same request answers as the first time and sends nothing; the same key with
another request throws a `ConflictException` (`idempotency_key_reused_with_different_request`).

## Errors

An error answer throws a `Postilio\Exception\PostilioException`, or a subclass per status:

| Status | Exception |
|---|---|
| 400 | `ValidationException`, with `errors` per field |
| 401 | `AuthenticationException` |
| 403 | `PermissionException` |
| 404 | `NotFoundException` |
| 409 | `ConflictException` |
| 413 | `PayloadTooLargeException` |
| 422 | `UnprocessableException` |
| 429 | `RateLimitException`, with `retryAfter`; `PlanLimitException` for the plan's month, day or minute |
| 5xx | `ServerException`, with the `traceId` to quote to support |
| none | `TransportException`: the connection failed or timed out |

`errorCode` holds the API's stable code and `errorMessage` the sentence some codes come with; `error()` gives the code as
a `Postilio\Enum\ErrorCode`, or null for one this version does not know yet. Postilio may add codes, so handle an
unknown one by the exception's class:

```php
use Postilio\Enum\ErrorCode;
use Postilio\Exception\RateLimitException;
use Postilio\Exception\UnprocessableException;

try {
    $postilio->sendEmail($message);
} catch (UnprocessableException $e) {
    if ($e->error() === ErrorCode::UnverifiedSenderDomain) {
        // the sender's domain is not verified (yet)
    }
} catch (RateLimitException $e) {
    // e.g. the sandbox's daily limit: try again after $e->retryAfter seconds
}
```

Messages name the call and the status, never the API key.

## Retries

Off by default. With `maxRetries`, the client tries a call again after a 408, 429, 500, 502, 503, 504 or a network
failure, but only when sending it again cannot do anything twice: a `GET`, or a send, which carries an
`Idempotency-Key`. Creating, changing and deleting are never retried, and neither is a test email.

```php
$postilio = new PostilioClient($key, maxRetries: 2, maxRetryDelay: 30.0);
```

It waits as long as `Retry-After` says, or else 0.5, 1, 2 … seconds with some random spread, never longer than
`maxRetryDelay`. An answer that asks to wait longer (the sandbox's daily limit, say) throws at once with `retryAfter`
set, rather than blocking your request. The curl timeout applies to each attempt.

## Statuses and other values

Statuses, event types, reasons and modes are backed enums in `Postilio\Enum` (`EmailStatus`, `DomainStatus`,
`DmarcStatus`, `WebhookEventType`, `EmailEventReason`, …). Postilio may add values: a model keeps a value it does not
know as a string, so a field such as `EmailDetails::$status` is `EmailStatus|string`. Compare with the enum case:

```php
if ($email->status === EmailStatus::Bounced) { /* … */ }
```

Models are immutable: readonly properties, a public constructor (handy in your own tests) and `fromArray()`.

## Domains, suppressions, webhooks and usage

These need a live key with the matching scope (`domains:manage`, `suppressions:manage`, `webhooks:manage`, `usage:read`).

```php
$domain = $postilio->createDomain(new CreateDomainRequest('mail.example.com'));
foreach ($domain->records as $record) {
    echo "{$record->type} {$record->name} → {$record->value}\n";
}
$domain = $postilio->checkDomain($domain->id); // once DNS is in place; $domain->dmarc holds the DMARC check

$page = $postilio->listSuppressions(reason: SuppressionReason::HardBounce, limit: 100);
while ($page->next !== null) {
    $page = $postilio->listSuppressions(reason: SuppressionReason::HardBounce, before: $page->next, limit: 100);
}

$created = $postilio->createWebhookEndpoint(new CreateWebhookEndpointRequest(
    'https://api.example.com/hooks/postilio',
    [WebhookEventType::Delivered, WebhookEventType::Bounced, WebhookEventType::Complained],
));
// $created->secret (whsec_…) is shown this once: store it in your secret store now.

$usage = $postilio->getUsage('2026-10');
echo "{$usage->project->billable} billable\n";
```

## Receiving webhooks

Verify every delivery before you trust it. `WebhookVerifier` implements the
[Standard Webhooks](https://www.standardwebhooks.com/) scheme: a constant-time comparison, a timestamp within five
minutes either way (so a captured request cannot be replayed later), and both signatures during a secret rotation.
Verify the raw body, before parsing it.

```php
use Postilio\Webhook\WebhookEvent;
use Postilio\Webhook\WebhookVerifier;

$verifier = new WebhookVerifier(getenv('POSTILIO_WEBHOOK_SECRET') ?: throw new RuntimeException('Set POSTILIO_WEBHOOK_SECRET.'));

$body = file_get_contents('php://input');
if (!$verifier->verify($_SERVER['HTTP_WEBHOOK_ID'] ?? null, $_SERVER['HTTP_WEBHOOK_TIMESTAMP'] ?? null,
    $_SERVER['HTTP_WEBHOOK_SIGNATURE'] ?? null, $body)) {
    http_response_code(401);
    exit;
}
$event = WebhookEvent::parse($body);
// Deliveries are at least once and in no particular order: drop a webhook-id you handled before,
// order by $event->data->occurredAt, and queue the work so you answer within 10 seconds.
http_response_code(204);
```

## Testing your code

Use a test key in your own tests and CI: nothing is delivered, and the recipient decides the outcome
(`delivered@`, `bounced@`, `deferred@`, `complained@`, `suppressed@simulator.postilio.eu`). Switching to live is
swapping the key. To test without the network, pass a PSR-18 client that answers what you need.

## Versioning

[Semantic versioning](https://semver.org/). Until 1.0 a minor version may change the public API; every change is in
[CHANGELOG.md](CHANGELOG.md). Classes and members marked `@internal` (`Postilio\Internal\*`) are not part of it.

## Contributing and security

See [CONTRIBUTING.md](CONTRIBUTING.md) and [SECURITY.md](SECURITY.md).

## License

[MIT](LICENSE).
