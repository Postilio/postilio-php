<?php

declare(strict_types=1);

namespace Postilio\Enum;

/**
 * The status of a sending domain. Postilio may add values: a model keeps one it does not know as a string.
 */
enum DomainStatus: string
{
    /** Not all records found yet; nothing can be sent from it. */
    case Pending = 'pending';

    /** All records found. */
    case Verified = 'verified';

    /** A record went missing; it keeps sending for 72 hours after `failingSince`. */
    case Failing = 'failing';
}
