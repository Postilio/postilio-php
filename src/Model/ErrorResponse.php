<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Internal\Json;

/** The body of most error answers. */
final class ErrorResponse
{
    public function __construct(
        /** A stable code; see ErrorCode. */
        public readonly string $error,
        /** Only with some codes: a sentence for people, such as the limit that was crossed. */
        public readonly ?string $message,
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
            Json::string($data, 'error'),
            Json::nullableString($data, 'message'),
        );
    }
}
