<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Enum\EmailStatus;
use Postilio\Internal\Json;

/** An event in a message's timeline. See Delivery status in the docs for what the codes and reasons mean. */
final class EmailEvent
{
    public function __construct(
        public readonly EmailStatus|string $type,
        public readonly \DateTimeImmutable $occurredAt,
        /** The receiving server's reply code; null when no server replied. */
        public readonly ?int $smtpCode,
        /** The receiving server's reply, masked, or a sentence that explains what happened. */
        public readonly ?string $response,
        public readonly ?int $attempt,
        public readonly ?string $remoteHost,
        public readonly ?string $enhancedCode,
        public readonly ?string $classification,
        /** Why the attempt was delayed or failed, as a stable code such as `mailbox_full`. */
        public readonly ?string $reason,
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
            Json::enum($data, 'type', EmailStatus::class),
            Json::dateTime($data, 'occurredAt'),
            Json::nullableInt($data, 'smtpCode'),
            Json::nullableString($data, 'response'),
            Json::nullableInt($data, 'attempt'),
            Json::nullableString($data, 'remoteHost'),
            Json::nullableString($data, 'enhancedCode'),
            Json::nullableString($data, 'classification'),
            Json::nullableString($data, 'reason'),
        );
    }
}
