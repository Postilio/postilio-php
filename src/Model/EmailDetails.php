<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Enum\EmailStatus;
use Postilio\Internal\Json;

/** A message and its timeline. */
final class EmailDetails
{
    /**
     * @param list<EmailEvent> $events
     */
    public function __construct(
        public readonly string $id,
        public readonly EmailStatus|string $status,
        public readonly string $from,
        public readonly string $to,
        /** Null when the project does not keep subjects. */
        public readonly ?string $subject,
        public readonly ?string $tag,
        public readonly \DateTimeImmutable $acceptedAt,
        /** Sent with a test key. */
        public readonly bool $test,
        /** The timeline, oldest first. */
        public readonly array $events,
        /** `api`, `smtp`, or `test_mail` for a test email. */
        public readonly string $via,
        /** When it is scheduled to go out; null when it was sent at once. */
        public readonly ?\DateTimeImmutable $sendAt,
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
            Json::enum($data, 'status', EmailStatus::class),
            Json::string($data, 'from'),
            Json::string($data, 'to'),
            Json::nullableString($data, 'subject'),
            Json::nullableString($data, 'tag'),
            Json::dateTime($data, 'acceptedAt'),
            Json::bool($data, 'test'),
            Json::objects($data, 'events', EmailEvent::fromArray(...)),
            Json::string($data, 'via'),
            Json::nullableDateTime($data, 'sendAt'),
        );
    }
}
