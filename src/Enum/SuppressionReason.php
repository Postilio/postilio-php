<?php

declare(strict_types=1);

namespace Postilio\Enum;

/**
 * Why an address is on the suppression list. Postilio may add values: a model keeps one it does not know as a string.
 */
enum SuppressionReason: string
{
    /** A receiving server refused it for good. */
    case HardBounce = 'hard_bounce';

    /** The recipient marked a message as spam. */
    case Complaint = 'complaint';

    /** Added by hand. */
    case Manual = 'manual';
}
