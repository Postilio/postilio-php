<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Enum\SuppressionReason;
use Postilio\Internal\Json;

/** An address on the suppression list. */
final class SuppressionResponse
{
    public function __construct(
        public readonly string $id,
        public readonly string $address,
        public readonly SuppressionReason|string $reason,
        /** The remote server's answer for a bounce, or who added it. */
        public readonly ?string $detail,
        /** The message that caused it; it may already be gone from the log. */
        public readonly ?string $sourceMessageId,
        public readonly \DateTimeImmutable $createdAt,
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
            Json::string($data, 'address'),
            Json::enum($data, 'reason', SuppressionReason::class),
            Json::nullableString($data, 'detail'),
            Json::nullableString($data, 'sourceMessageId'),
            Json::dateTime($data, 'createdAt'),
        ];

        return new self(...$fields);
    }
}
