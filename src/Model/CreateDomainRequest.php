<?php

declare(strict_types=1);

namespace Postilio\Model;

/** A sending domain to add. */
final class CreateDomainRequest
{
    public function __construct(
        /** A host name of at least two labels, such as `mail.example.com`. */
        public readonly string $name,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['name' => $this->name];
    }
}
