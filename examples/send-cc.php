<?php

// Sends to one recipient with a copy and a blind copy: php examples/send-cc.php
// Cc and bcc take exactly one address in `to`; every recipient gets a message of its own.
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Postilio\Exception\UnprocessableException;
use Postilio\Model\SendEmailRequest;

$postilio = postilio();

try {
    $sent = $postilio->sendEmail(new SendEmailRequest(
        from: 'Acme Support <' . sender() . '>',
        to: ['delivered@simulator.postilio.eu'],
        subject: 'Your ticket 4711',
        text: 'Solved: the export works again.',
        cc: ['delivered+manager@simulator.postilio.eu'],
        bcc: ['delivered+archive@simulator.postilio.eu'],
    ), 'ticket-4711-solved-' . date('Ymd'));
} catch (UnprocessableException $e) {
    // cc_bcc_require_single_to, unverified_sender_domain, …
    fwrite(\STDERR, $e->getMessage() . \PHP_EOL);
    exit(1);
}

echo 'Messages, in the order to, cc, bcc: ', implode(', ', $sent->ids), \PHP_EOL;
