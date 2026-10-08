<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Enum\DnsRecordStatus;
use Postilio\Internal\Json;

/** A DNS record a sending domain needs. */
final class DnsRecord
{
    public function __construct(
        public readonly string $type,
        public readonly string $name,
        public readonly string $value,
        public readonly DnsRecordStatus|string $status,
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
            Json::string($data, 'type'),
            Json::string($data, 'name'),
            Json::string($data, 'value'),
            Json::enum($data, 'status', DnsRecordStatus::class),
        ];

        return new self(...$fields);
    }
}
