<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Internal\Json;

/** The project's webhook endpoints. */
final class WebhookEndpointList
{
    /**
     * @param list<WebhookEndpointResponse> $data
     */
    public function __construct(
        public readonly array $data,
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
            Json::objects($data, 'data', WebhookEndpointResponse::fromArray(...)),
        );
    }
}
