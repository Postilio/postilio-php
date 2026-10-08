<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Enum\WebhookEventType;
use Postilio\Enum\WebhookMode;
use Postilio\Internal\Json;

/** A webhook endpoint. */
final class WebhookEndpointResponse
{
    /**
     * @param list<WebhookEventType|string> $events
     */
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly ?string $description,
        /** The message events it gets. */
        public readonly array $events,
        public readonly WebhookMode|string $mode,
        public readonly bool $paused,
        public readonly ?string $pauseReason,
        public readonly ?\DateTimeImmutable $pausedAt,
        /** Every attempt since then failed; after 72 hours of that the endpoint is paused. */
        public readonly ?\DateTimeImmutable $failingSince,
        /** The last characters of the signing secret. */
        public readonly string $secretHint,
        /** Until then the secret from before the last rotation still signs as well. */
        public readonly ?\DateTimeImmutable $previousSecretExpiresAt,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?WebhookLastDelivery $lastDelivery,
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
            Json::string($data, 'url'),
            Json::nullableString($data, 'description'),
            Json::enums($data, 'events', WebhookEventType::class),
            Json::enum($data, 'mode', WebhookMode::class),
            Json::bool($data, 'paused'),
            Json::nullableString($data, 'pauseReason'),
            Json::nullableDateTime($data, 'pausedAt'),
            Json::nullableDateTime($data, 'failingSince'),
            Json::string($data, 'secretHint'),
            Json::nullableDateTime($data, 'previousSecretExpiresAt'),
            Json::dateTime($data, 'createdAt'),
            ($v = Json::nullableObject($data, 'lastDelivery')) === null ? null : WebhookLastDelivery::fromArray($v),
        ];

        return new self(...$fields);
    }
}
