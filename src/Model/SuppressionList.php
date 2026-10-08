<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Internal\Json;

/** A page of the suppression list, newest first. */
final class SuppressionList
{
    /**
     * @param list<SuppressionResponse> $data
     */
    public function __construct(
        public readonly array $data,
        /** Pass as `before` for the next page; null on the last page. */
        public readonly ?string $next,
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
            Json::objects($data, 'data', SuppressionResponse::fromArray(...)),
            Json::nullableString($data, 'next'),
        ];

        return new self(...$fields);
    }
}
