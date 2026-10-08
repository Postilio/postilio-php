<?php

// Shared by the examples: makes a client from the environment, or ends the example when there is no key.
//   POSTILIO_API_KEY   a key; use a test key (pk_test_…): nothing is delivered
//   POSTILIO_FROM      an address on a verified domain of the key's project
//   POSTILIO_BASE_URL  optional, https://api.postilio.eu by default
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Postilio\PostilioClient;

function postilio(): PostilioClient
{
    $key = getenv('POSTILIO_API_KEY');
    if ($key === false || $key === '') {
        echo 'Skipped: set POSTILIO_API_KEY (and POSTILIO_FROM) to run this example against the API.', \PHP_EOL;
        exit(0);
    }

    return new PostilioClient($key, baseUrl: getenv('POSTILIO_BASE_URL') ?: 'https://api.postilio.eu', maxRetries: 2);
}

function sender(): string
{
    $from = getenv('POSTILIO_FROM');
    if ($from === false || $from === '') {
        fwrite(\STDERR, 'Set POSTILIO_FROM to an address on a verified domain of the key\'s project.' . \PHP_EOL);
        exit(1);
    }

    return $from;
}
