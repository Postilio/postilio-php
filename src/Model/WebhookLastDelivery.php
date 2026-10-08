<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Enum\WebhookDeliveryStatus;
use Postilio\Internal\Json;

/** The last attempt to deliver to an endpoint. */
final class WebhookLastDelivery
{
    public function __construct(
        public readonly \DateTimeImmutable $at,
        /** The receiver's HTTP status; null when no answer came. */
        public readonly ?int $statusCode,
        /** The delivery's status now. */
        public readonly WebhookDeliveryStatus|string $status,
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
            Json::dateTime($data, 'at'),
            Json::nullableInt($data, 'statusCode'),
            Json::enum($data, 'status', WebhookDeliveryStatus::class),
        ];

        return new self(...$fields);
    }
}
