<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Internal\Json;

/** The key's project in one UTC month, and where its organization stands. */
final class ApiUsage
{
    public function __construct(
        /** `yyyy-MM`. */
        public readonly string $month,
        /** The month is closed: these numbers no longer change. */
        public readonly bool $final,
        /** The start of the next month, 00:00 UTC. */
        public readonly \DateTimeImmutable $resetsAt,
        public readonly ApiUsageOrganization $organization,
        public readonly ApiProjectUsage $project,
        public readonly ApiKeyMonthUsage $apiKey,
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
            Json::string($data, 'month'),
            Json::bool($data, 'final'),
            Json::dateTime($data, 'resetsAt'),
            ApiUsageOrganization::fromArray(Json::object($data, 'organization')),
            ApiProjectUsage::fromArray(Json::object($data, 'project')),
            ApiKeyMonthUsage::fromArray(Json::object($data, 'apiKey')),
        ];

        return new self(...$fields);
    }
}
