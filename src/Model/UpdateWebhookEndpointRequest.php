<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Enum\WebhookEventType;
use Postilio\Internal\Json;

/** Changes to a webhook endpoint: what is left null stays as it is. */
final class UpdateWebhookEndpointRequest
{
    /**
     * @param list<WebhookEventType|string>|null $events
     */
    public function __construct(
        public readonly ?string $url = null,
        public readonly ?array $events = null,
        /** An empty string removes it. */
        public readonly ?string $description = null,
        /** True pauses the endpoint, false resumes it. */
        public readonly ?bool $paused = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return Json::fields(['url' => $this->url, 'events' => $this->events, 'description' => $this->description, 'paused' => $this->paused]);
    }
}
