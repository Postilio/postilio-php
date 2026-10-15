<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Enum\PlatformKeyStatus;
use Postilio\Internal\Json;

/** The DKIM key record Postilio publishes itself, the one a domain's DKIM CNAME points to. Not one for you to create. */
final class PlatformKeyRecord
{
    public function __construct(
        public readonly string $type,
        public readonly string $name,
        public readonly PlatformKeyStatus|string $status,
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
            Json::string($data, 'type'),
            Json::string($data, 'name'),
            Json::enum($data, 'status', PlatformKeyStatus::class),
        );
    }
}
