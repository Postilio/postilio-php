<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Enum\DomainStatus;
use Postilio\Internal\Json;

/** A sending domain. */
final class DomainResponse
{
    /**
     * @param list<DnsRecord> $records
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly DomainStatus|string $status,
        public readonly ?\DateTimeImmutable $checkedAt,
        /** The DNS records to create. */
        public readonly array $records,
        public readonly \DateTimeImmutable $createdAt,
        /** The dashboard user who added it; null when it was added with an API key. */
        public readonly ?string $addedBy,
        /** Since when its records are missing, while it is failing. */
        public readonly ?\DateTimeImmutable $failingSince,
        /** Live messages sent from it today and the 29 days before (UTC). */
        public readonly int $sent30d,
        /** Null until the first check. */
        public readonly ?DmarcCheck $dmarc,
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
        $fields = [
            Json::string($data, 'id'),
            Json::string($data, 'name'),
            Json::enum($data, 'status', DomainStatus::class),
            Json::nullableDateTime($data, 'checkedAt'),
            Json::objects($data, 'records', DnsRecord::fromArray(...)),
            Json::dateTime($data, 'createdAt'),
            Json::nullableString($data, 'addedBy'),
            Json::nullableDateTime($data, 'failingSince'),
            Json::int($data, 'sent30d'),
            ($v = Json::nullableObject($data, 'dmarc')) === null ? null : DmarcCheck::fromArray($v),
        ];

        return new self(...$fields);
    }
}
