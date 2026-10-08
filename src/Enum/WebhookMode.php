<?php

declare(strict_types=1);

namespace Postilio\Enum;

/**
 * Which messages' events a webhook endpoint gets. Postilio may add values: a model keeps one it does not know as a string.
 */
enum WebhookMode: string
{
    /** Messages sent with a live key. */
    case Live = 'live';

    /** Messages sent with a test key. */
    case Test = 'test';
}
