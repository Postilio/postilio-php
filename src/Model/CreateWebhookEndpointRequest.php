<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Enum\WebhookEventType;
use Postilio\Enum\WebhookMode;
use Postilio\Internal\Json;

/** A webhook endpoint to add. */
final class CreateWebhookEndpointRequest
{
    /**
     * @param list<WebhookEventType|string> $events One or more message events.
     */
    public function __construct(
        /** An absolute `https://` URL on a public address, without credentials or a fragment. */
        public readonly string $url,
        public readonly array $events,
        /** At most 200 characters. */
        public readonly ?string $description = null,
        /** `live` (the default) or `test`: a test endpoint gets the events of test-key messages only. */
        public readonly WebhookMode|string|null $mode = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return Json::fields(['url' => $this->url, 'events' => $this->events, 'description' => $this->description, 'mode' => $this->mode]);
    }
}
