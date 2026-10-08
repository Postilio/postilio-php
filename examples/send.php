<?php

// Sends an email and reads it back with its events: php examples/send.php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Postilio\Model\SendEmailRequest;

$postilio = postilio();

$sent = $postilio->sendEmail(new SendEmailRequest(
    from: 'Acme <' . sender() . '>',
    to: ['delivered@simulator.postilio.eu'],
    subject: 'Welcome to Acme',
    text: 'Hi Ada, your account is ready.',
    html: '<p>Hi Ada, your account is ready.</p>',
    tag: 'welcome',
));

$email = $postilio->getEmail($sent->ids[0]);
echo "{$email->id}: ", $email->status instanceof BackedEnum ? $email->status->value : $email->status, \PHP_EOL;
foreach ($email->events as $event) {
    echo '  ', $event->occurredAt->format(DATE_ATOM), ' ', $event->type instanceof BackedEnum ? $event->type->value : $event->type, \PHP_EOL;
}
