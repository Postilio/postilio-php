<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Internal\Json;

/** The body of a 400: the problems per field. Read leniently, since a 400 for a body that is not JSON has none. */
final class HttpValidationProblemDetails
{
    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(
        public readonly ?string $type,
        public readonly ?string $title,
        public readonly ?int $status,
        public readonly ?string $detail,
        public readonly ?string $instance,
        /** The problems per request field; `body` stands for `text` and `html`, `idempotencyKey` for the header. */
        public readonly array $errors,
        /** Quote it when you contact support. */
        public readonly ?string $traceId = null,
    ) {}

    /**
     * Reads the model from a decoded JSON answer.
     *
     * @param array<mixed> $data
     *
     * @throws \UnexpectedValueException A field is of another type.
     */
    public static function fromArray(array $data): self
    {
        $errors = [];
        foreach (Json::nullableObject($data, 'errors') ?? [] as $field => $_) {
            $errors[(string) $field] = Json::strings(Json::object($data, 'errors'), (string) $field);
        }

        $fields = [
            Json::nullableString($data, 'type'),
            Json::nullableString($data, 'title'),
            Json::nullableInt($data, 'status'),
            Json::nullableString($data, 'detail'),
            Json::nullableString($data, 'instance'),
            $errors,
            Json::nullableString($data, 'traceId'),
        ];

        return new self(...$fields);
    }
}
