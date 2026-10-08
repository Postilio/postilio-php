<?php

declare(strict_types=1);

namespace Postilio\Enum;

/**
 * The status of a message, and the type of the events in its timeline. Postilio may add values: a model keeps one it does not know as a string.
 */
enum EmailStatus: string
{
    /** Stored, waiting to be handed to the mail server. */
    case Accepted = 'accepted';

    /** Stored, waiting for its `sendAt`. */
    case Scheduled = 'scheduled';

    /** Not sent: canceled while scheduled, or no longer allowed when it was due. */
    case Canceled = 'canceled';

    /** Handed to the mail server. */
    case Queued = 'queued';

    /** Not delivered yet; it is tried again. */
    case Deferred = 'deferred';

    /** The receiving server accepted it. */
    case Delivered = 'delivered';

    /** Refused for good. */
    case Bounced = 'bounced';

    /** Not delivered within a day; no more attempts. */
    case Expired = 'expired';

    /** The recipient marked it as spam. */
    case Complained = 'complained';

    /** Not sent: the address is on the suppression list. */
    case Suppressed = 'suppressed';
}
