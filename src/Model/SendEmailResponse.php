<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Internal\Json;

/** The answer to a send: one message per recipient. */
final class SendEmailResponse
{
    /**
     * @param list<string> $ids
     * @param list<string> $suppressed
     */
    public function __construct(
        /** One message id per recipient, in the order of `to`, then `cc`, then `bcc`. */
        public readonly array $ids,
        /** Recipients on the suppression list: accepted with status `suppressed`, not sent. */
        public readonly array $suppressed,
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
        $fields = [
            Json::strings($data, 'ids'),
            Json::strings($data, 'suppressed'),
        ];

        return new self(...$fields);
    }
}
