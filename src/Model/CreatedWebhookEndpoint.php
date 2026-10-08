<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Internal\Json;

/** A new webhook endpoint and its signing secret. */
final class CreatedWebhookEndpoint
{
    public function __construct(
        public readonly WebhookEndpointResponse $endpoint,
        /** The signing secret (`whsec_…`), shown this once: store it in your secret store now. */
        public readonly string $secret,
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
            WebhookEndpointResponse::fromArray(Json::object($data, 'endpoint')),
            Json::string($data, 'secret'),
        );
    }
}
