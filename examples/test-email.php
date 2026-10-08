<?php

// Sends Postilio's sample test email to an address of your own: php examples/test-email.php
//   POSTILIO_TEST_TO  a member's address, or one confirmed for test mail in the portal
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Postilio\Model\TestEmailRequest;

$postilio = postilio();
$to = getenv('POSTILIO_TEST_TO');
if ($to === false || $to === '') {
    echo 'Skipped: set POSTILIO_TEST_TO to an address of your own.', \PHP_EOL;
    exit(0);
}

$sent = $postilio->sendTestEmail(new TestEmailRequest(sender(), $to));
$email = $postilio->getEmail($sent->id);
echo "{$email->id} via {$email->via}: ", $email->status instanceof BackedEnum ? $email->status->value : $email->status, \PHP_EOL;
