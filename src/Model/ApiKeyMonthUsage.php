<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Internal\Json;

/** The calling key's own month, by the day its messages were accepted; informative. */
final class ApiKeyMonthUsage
{
    public function __construct(
        public readonly string $id,
        public readonly int $accepted,
        /** Accepted minus suppressed. */
        public readonly int $sent,
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
        return new self(
            Json::string($data, 'id'),
            Json::int($data, 'accepted'),
            Json::int($data, 'sent'),
            Json::int($data, 'suppressed'),
            Json::int($data, 'delivered'),
            Json::int($data, 'bounced'),
            Json::int($data, 'complained'),
        );
    }
}
