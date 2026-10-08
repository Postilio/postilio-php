<?php

declare(strict_types=1);

namespace Postilio\Enum;

/**
 * The DMARC record of a sending domain, as Postilio last read it. Postilio may add values: a model keeps one it does not know as a string.
 */
enum DmarcStatus: string
{
    /** No record on the domain or a parent. */
    case Missing = 'missing';

    /** Receivers ignore the record; `issues` says why. */
    case Invalid = 'invalid';

    /** `p=none`: receivers report on mail that fails, but deliver it. */
    case Monitoring = 'monitoring';

    /** `p=quarantine` or `p=reject`. */
    case Enforced = 'enforced';
}
