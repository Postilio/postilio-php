<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Enum\WebhookDeliveryStatus;
use Postilio\Internal\Json;

/** A delivery of an event to a webhook endpoint. */
final class WebhookDeliveryResponse
{
    public function __construct(
        public readonly string $id,
        /** The `webhook-id` header: the same for every attempt. */
        public readonly string $eventId,
        /** The payload type, such as `email.delivered.v1`. */
        public readonly string $type,
        public readonly ?string $emailId,
        public readonly ?string $to,
        public readonly WebhookDeliveryStatus|string $status,
        public readonly int $attempts,
        public readonly ?\DateTimeImmutable $lastAttemptAt,
        public readonly ?int $lastStatusCode,
        public readonly ?string $lastError,
        public readonly ?int $lastDurationMs,
        /** The start of the last response body, at most 256 characters. */
        public readonly ?string $responseSnippet,
        /** Set while pending. */
        public readonly ?\DateTimeImmutable $nextAttemptAt,
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
        return new self(
            Json::string($data, 'id'),
            Json::string($data, 'eventId'),
            Json::string($data, 'type'),
            Json::nullableString($data, 'emailId'),
            Json::nullableString($data, 'to'),
            Json::enum($data, 'status', WebhookDeliveryStatus::class),
            Json::int($data, 'attempts'),
            Json::nullableDateTime($data, 'lastAttemptAt'),
            Json::nullableInt($data, 'lastStatusCode'),
            Json::nullableString($data, 'lastError'),
            Json::nullableInt($data, 'lastDurationMs'),
            Json::nullableString($data, 'responseSnippet'),
            Json::nullableDateTime($data, 'nextAttemptAt'),
            Json::dateTime($data, 'createdAt'),
        );
    }
}
