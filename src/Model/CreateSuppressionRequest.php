<?php

declare(strict_types=1);

namespace Postilio\Model;

/** An address to put on the suppression list, with the reason `manual`. */
final class CreateSuppressionRequest
{
    public function __construct(
        /** One bare address, at most 254 characters. */
        public readonly string $address,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['address' => $this->address];
    }
}
