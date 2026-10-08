<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Internal\Json;

/** The project's numbers in the month: `billable` is what is billed. */
final class ApiProjectUsage
{
    public function __construct(
        public readonly string $id,
        /** Accepted minus suppressed. */
        public readonly int $billable,
        public readonly int $accepted,
        public readonly int $suppressed,
        public readonly int $delivered,
        public readonly int $bounced,
        public readonly int $complained,
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
            Json::int($data, 'billable'),
            Json::int($data, 'accepted'),
            Json::int($data, 'suppressed'),
            Json::int($data, 'delivered'),
            Json::int($data, 'bounced'),
            Json::int($data, 'complained'),
        ];

        return new self(...$fields);
    }
}
