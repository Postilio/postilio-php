<?php

declare(strict_types=1);

namespace Postilio\Enum;

/**
 * The message events a webhook endpoint can subscribe to. Postilio may add values: a model keeps one it does not know as a string.
 */
enum WebhookEventType: string
{
    /** The API or SMTP took the message. */
    case Accepted = 'accepted';

    /** Handed to the mail server. */
    case Queued = 'queued';

    /** The receiving server accepted it. */
    case Delivered = 'delivered';

    /** A temporary failure; it is retried. */
    case Deferred = 'deferred';

    /** Refused for good, also a bounce that arrives later. */
    case Bounced = 'bounced';

    /** Not delivered within a day. */
    case Expired = 'expired';

    /** The recipient marked it as spam. */
    case Complained = 'complained';

    /** Not sent: the address is on the suppression list. */
    case Suppressed = 'suppressed';

    /** The message waits for its `sendAt`. */
    case Scheduled = 'scheduled';

    /** A scheduled message will not be sent. */
    case Canceled = 'canceled';
}
