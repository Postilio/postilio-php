<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Internal\Json;

/** A page of an endpoint's deliveries, newest first. */
final class WebhookDeliveryList
{
    /**
     * @param list<WebhookDeliveryResponse> $data
     */
    public function __construct(
        public readonly array $data,
        /** Pass as `before` for the next page; null on the last page. */
        public readonly ?string $next,
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
            Json::objects($data, 'data', WebhookDeliveryResponse::fromArray(...)),
            Json::nullableString($data, 'next'),
        );
    }
}
