<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Internal\Json;

/** The answer to a test email. */
final class TestEmailResponse
{
    public function __construct(
        /** The message; follow it with getEmail(). */
        public readonly string $id,
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
            Json::string($data, 'id'),
        );
    }
}
