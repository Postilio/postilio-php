<?php

declare(strict_types=1);

namespace Postilio\Webhook;

use Postilio\Internal\Json;

/** The body of a webhook delivery. Verify it with WebhookVerifier before you parse it. */
final class WebhookEvent
{
    public function __construct(
        /** The versioned event type, such as `email.delivered.v1` or `webhook.test.v1`. */
        public readonly string $type,
        /** When the delivery was created. */
        public readonly \DateTimeImmutable $timestamp,
        public readonly WebhookEventData $data,
    ) {}

    /**
     * Reads a delivery's raw body; fields added later are ignored.
     *
     * @throws \UnexpectedValueException The body is not a webhook event.
     */
    public static function parse(string $body): self
    {
        $data = Json::decode($body);

        return new self(Json::string($data, 'type'), Json::dateTime($data, 'timestamp'), WebhookEventData::fromArray(Json::object($data, 'data')));
    }
}
