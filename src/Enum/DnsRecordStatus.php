<?php

declare(strict_types=1);

namespace Postilio\Enum;

/**
 * The status of one DNS record of a sending domain. Postilio may add values: a model keeps one it does not know as a string.
 */
enum DnsRecordStatus: string
{
    /** The record is in place. */
    case Found = 'found';

    /** The record was not found. */
    case Missing = 'missing';

    /** Not checked yet. */
    case Unknown = 'unknown';
}
