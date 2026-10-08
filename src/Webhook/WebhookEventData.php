<?php

declare(strict_types=1);

namespace Postilio\Webhook;

use Postilio\Enum\EmailStatus;
use Postilio\Internal\Json;

/** What a webhook event is about. Fields without a value are null; there is never message content in it. */
final class WebhookEventData
{
    public function __construct(
        /** The message the event is about. */
        public readonly ?string $emailId = null,
        public readonly ?string $projectId = null,
        /** For `webhook.test.v1`: the endpoint it was sent to. */
        public readonly ?string $endpointId = null,
        /** The recipient. */
        public readonly ?string $to = null,
        public readonly ?string $tag = null,
        /** True for a message sent with a test key, and for a test event. */
        public readonly bool $test = false,
        public readonly EmailStatus|string|null $event = null,
        /** When it happened: order events by this, since they can arrive out of order. */
        public readonly ?\DateTimeImmutable $occurredAt = null,
        public readonly ?int $attempt = null,
        public readonly ?int $smtpCode = null,
        public readonly ?string $enhancedCode = null,
        public readonly ?string $classification = null,
        /** Why an attempt was delayed or failed, or why a scheduled message was canceled, as a stable code. */
        public readonly ?string $reason = null,
        public readonly ?string $response = null,
        public readonly ?string $remoteHost = null,
        /** For a scheduled message: when it goes out. */
        public readonly ?\DateTimeImmutable $sendAt = null,
    ) {}

    /**
     * @param array<mixed> $data
     *
     * @throws \UnexpectedValueException A field is of another type.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Json::nullableString($data, 'emailId'),
            Json::nullableString($data, 'projectId'),
            Json::nullableString($data, 'endpointId'),
            Json::nullableString($data, 'to'),
            Json::nullableString($data, 'tag'),
            isset($data['test']) && Json::bool($data, 'test'),
            Json::nullableEnum($data, 'event', EmailStatus::class),
            Json::nullableDateTime($data, 'occurredAt'),
            Json::nullableInt($data, 'attempt'),
            Json::nullableInt($data, 'smtpCode'),
            Json::nullableString($data, 'enhancedCode'),
            Json::nullableString($data, 'classification'),
            Json::nullableString($data, 'reason'),
            Json::nullableString($data, 'response'),
            Json::nullableString($data, 'remoteHost'),
            Json::nullableDateTime($data, 'sendAt'),
        );
    }
}
