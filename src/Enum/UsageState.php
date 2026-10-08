<?php

declare(strict_types=1);

namespace Postilio\Enum;

/**
 * Where the organization stands against its plan's monthly limit this month. Postilio may add values: a model keeps one it does not know as a string.
 */
enum UsageState: string
{
    /** Below 80% of the monthly limit, or no limit. */
    case Ok = 'ok';

    /** From 80%. */
    case Warning = 'warning';

    /** From 100%, on a plan with overage. */
    case Over = 'over';

    /** Where sending stops. */
    case Capped = 'capped';
}
