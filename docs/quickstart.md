# Quick start

From nothing to a sent and tracked email in five steps. You need PHP 8.2 or later and Composer.

## 1. Install

```sh
composer require postilio/postilio-php
```

Without a PSR-18 client of your own, the SDK sends with curl, so check that `ext-curl` is enabled (`php -m | grep curl`).

## 2. Get a key and a sender

In the [portal](https://portal.postilio.eu):

1. **Domains**: add the domain you send from, such as `mail.example.com`, and create the two DNS records it shows. Once
   Postilio found them, the domain is `verified`.
2. **Keys & SMTP**: create a **test** key (`pk_test_…`) with the scopes `emails:send` and `emails:read`. A test key goes
   through every check a live key does, but never delivers: each message gets simulated events at once.

Keep the key out of your code and your repository, in an environment variable or your secret store:

```sh
export POSTILIO_API_KEY=pk_test_…
export POSTILIO_FROM=no-reply@mail.example.com
```

## 3. Send

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Postilio\Model\SendEmailRequest;
use Postilio\PostilioClient;

$postilio = new PostilioClient(getenv('POSTILIO_API_KEY') ?: throw new RuntimeException('Set POSTILIO_API_KEY.'));

$sent = $postilio->sendEmail(new SendEmailRequest(
    from: 'Acme <' . getenv('POSTILIO_FROM') . '>',
    to: ['delivered@simulator.postilio.eu'],
    subject: 'Welcome to Acme',
    text: 'Hi Ada, your account is ready.',
));
echo "Accepted: {$sent->ids[0]}\n";
```

With a test key, the recipient decides the outcome: `delivered@`, `bounced@`, `deferred@`, `complained@` or
`suppressed@simulator.postilio.eu`.

## 4. Follow it

```php
$email = $postilio->getEmail($sent->ids[0]);
foreach ($email->events as $event) {
    $type = $event->type instanceof BackedEnum ? $event->type->value : $event->type; // a type added later is a string
    echo $event->occurredAt->format(DATE_ATOM), " {$type}\n";
}
```

To hear about events as they happen instead of asking, add a webhook endpoint and verify each delivery with
`Postilio\Webhook\WebhookVerifier` (see the README).

## 5. Handle what can go wrong

```php
use Postilio\Exception\PostilioException;
use Postilio\Exception\UnprocessableException;
use Postilio\Exception\ValidationException;

try {
    $postilio->sendEmail($request, "signup-{$user->id}-welcome");
} catch (ValidationException $e) {
    // 400: $e->errors lists the problems per field; fix the request
} catch (UnprocessableException $e) {
    // 422: such as unverified_sender_domain ($e->errorCode)
} catch (PostilioException $e) {
    // anything else: log $e->getMessage(), which never holds the key
}
```

The second argument of `sendEmail()` is an Idempotency-Key: sending the same request with it again within 24 hours,
after a timeout say, sends nothing twice.

## Going live

Create a live key (`pk_live_…`) and swap it in. A new organization starts in the sandbox: live mail only to your team and
your verified domains, 100 recipients a day. The portal's **Sandbox** page shows how to ask for full access.

More: the [API docs](https://docs.postilio.eu), the [README](../README.md) and the [examples](../examples).
