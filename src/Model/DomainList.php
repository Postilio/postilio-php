<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Internal\Json;

/** The project's sending domains. */
final class DomainList
{
    /**
     * @param list<DomainResponse> $data
     */
    public function __construct(
        public readonly array $data,
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
            Json::objects($data, 'data', DomainResponse::fromArray(...)),
        ];

        return new self(...$fields);
    }
}
