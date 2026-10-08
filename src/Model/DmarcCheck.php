<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Enum\DmarcStatus;
use Postilio\Internal\Json;

/** The DMARC record Postilio found at the last check. */
final class DmarcCheck
{
    /**
     * @param list<string> $records
     * @param list<string> $issues
     */
    public function __construct(
        public readonly DmarcStatus|string $status,
        /** The domain itself, or the parent the record was found on; null while missing. */
        public readonly ?string $policyDomain,
        /** The DMARC records found there, as published. */
        public readonly array $records,
        /** Why it is invalid, or advice on a valid one, such as `no_reports`. */
        public readonly array $issues,
    ) {}

    /**
     * Reads the model from a decoded JSON answer.
     *
     * @param array<mixed> $data
     *
     * @throws \UnexpectedValueException A field is missing or of another type.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Json::enum($data, 'status', DmarcStatus::class),
            Json::nullableString($data, 'policyDomain'),
            Json::strings($data, 'records'),
            Json::strings($data, 'issues'),
        );
    }
}
