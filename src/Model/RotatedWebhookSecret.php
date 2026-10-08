<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Internal\Json;

/** An endpoint's new signing secret. */
final class RotatedWebhookSecret
{
    public function __construct(
        /** The new signing secret, shown once. */
        public readonly string $secret,
        /** Until then deliveries are signed with the old secret as well. */
        public readonly \DateTimeImmutable $previousSecretExpiresAt,
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
            Json::string($data, 'secret'),
            Json::dateTime($data, 'previousSecretExpiresAt'),
        );
    }
}
