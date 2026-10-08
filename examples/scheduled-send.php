<?php

// Schedules an email for an hour from now, then cancels it: php examples/scheduled-send.php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Postilio\Exception\ConflictException;
use Postilio\Model\SendEmailRequest;

$postilio = postilio();

$sent = $postilio->sendEmail(new SendEmailRequest(
    from: 'Acme <' . sender() . '>',
    to: ['delivered@simulator.postilio.eu'],
    subject: 'Your trial ends tomorrow',
    text: 'Your trial of Acme ends tomorrow at noon.',
    tag: 'trial-reminder',
    headers: ['List-Unsubscribe' => '<https://example.com/unsubscribe/3f9a1c7e>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click'],
    sendAt: new DateTimeImmutable('+1 hour'),
));
$id = $sent->ids[0];
echo "Scheduled {$id} for ", $postilio->getEmail($id)->sendAt?->format(DATE_ATOM), \PHP_EOL;

try {
    $postilio->cancelEmail($id);
} catch (ConflictException $e) {
    // email_not_scheduled: it is on its way already. A test key checks sendAt but simulates the message at once, so
    // only a live key's message waits to be canceled.
    echo "Not canceled: {$e->getMessage()}", \PHP_EOL;
}
$status = $postilio->getEmail($id)->status;
echo 'Now: ', $status instanceof BackedEnum ? $status->value : $status, \PHP_EOL;
