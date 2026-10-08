<?php

declare(strict_types=1);

namespace Postilio\Enum;

/**
 * The status of a webhook delivery. Postilio may add values: a model keeps one it does not know as a string.
 */
enum WebhookDeliveryStatus: string
{
    /** Not delivered yet; it is retried. */
    case Pending = 'pending';

    /** The endpoint answered 2xx. */
    case Delivered = 'delivered';

    /** No attempt succeeded within 3 days. */
    case Failed = 'failed';
}
