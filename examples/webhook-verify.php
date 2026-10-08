<?php

// Verifies a webhook delivery before trusting it: php examples/webhook-verify.php
// Needs no API key: it checks the Standard Webhooks test vector. In your endpoint, read the headers and the raw body
// of the request instead, as in receive() below.
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Postilio\Webhook\WebhookEvent;
use Postilio\Webhook\WebhookVerifier;

$verifier = new WebhookVerifier('whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw');
$valid = $verifier->verify(
    'msg_p5jXN8AQM9LWM0D4loKWxJek',
    '1614265330',
    'v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE=',
    '{"test": 2432232314}',
    now: 1614265330, // the vector's own time; leave it out to use the clock
);
echo $valid ? 'valid' : 'invalid', \PHP_EOL;
exit($valid ? 0 : 1);

/** A plain PHP endpoint: answer within 10 seconds, and do the work afterwards. */
function receive(WebhookVerifier $verifier): void
{
    $body = file_get_contents('php://input');
    $header = static fn(string $name): ?string => isset($_SERVER[$name]) && is_string($_SERVER[$name]) ? $_SERVER[$name] : null;
    if (!is_string($body) || !$verifier->verify($header('HTTP_WEBHOOK_ID'), $header('HTTP_WEBHOOK_TIMESTAMP'), $header('HTTP_WEBHOOK_SIGNATURE'), $body)) {
        http_response_code(401);

        return;
    }
    $event = WebhookEvent::parse($body);
    // At least once and in no particular order: drop a webhook-id you handled before, order by $event->data->occurredAt.
    http_response_code(204);
}
