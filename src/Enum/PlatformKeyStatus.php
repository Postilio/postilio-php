<?php

declare(strict_types=1);

namespace Postilio\Enum;

/**
 * Where the DKIM key record Postilio publishes for a domain stands. Postilio may add values: a model keeps one it does not know as a string.
 */
enum PlatformKeyStatus: string
{
    /** Found in DNS at the last check. */
    case Published = 'published';

    /** Postilio is publishing it, or it is not checked yet. Usually a minute. */
    case Publishing = 'publishing';

    /** Publishing it keeps failing; Postilio's team is alerted and it is retried. Nothing to do on your side. */
    case Failed = 'failed';

    /** This Postilio installation publishes these records by hand; its operator is alerted when one is missing. */
    case Manual = 'manual';
}
