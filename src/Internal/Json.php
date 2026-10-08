<?php

declare(strict_types=1);

namespace Postilio\Internal;

/**
 * Typed reads from a decoded JSON object. A required field that is missing or of another type throws an
 * UnexpectedValueException naming the field; an optional one may be missing or null. Unknown fields are ignored, since
 * Postilio may add fields.
 *
 * A model reads its fields into an array before `new self(...$fields)`: on PHP 8.1, an exception thrown while the
 * arguments of `new` are evaluated corrupts memory when the class has readonly properties, and the process crashes.
 *
 * @internal
 */
final class Json
{
    /**
     * @return array<mixed>
     *
     * @throws \UnexpectedValueException
     */
    public static function decode(string $json): array
    {
        try {
            $data = json_decode($json, true, 64, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \UnexpectedValueException('The body is not JSON.', 0, $e);
        }

        return \is_array($data) && (!array_is_list($data) || $data === []) ? $data : throw new \UnexpectedValueException('The body is not a JSON object.');
    }

    /** @param array<string, mixed> $data As an object, also when it is empty. */
    public static function encode(array $data): string
    {
        return json_encode((object) $data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION);
    }

    /** @param array<mixed> $data */
    public static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) ? $value : throw self::invalid($key, 'a string');
    }

    /** @param array<mixed> $data */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return $value === null || \is_string($value) ? $value : throw self::invalid($key, 'a string or null');
    }

    /** @param array<mixed> $data */
    public static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return \is_int($value) ? $value : throw self::invalid($key, 'an integer');
    }

    /** @param array<mixed> $data */
    public static function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return $value === null || \is_int($value) ? $value : throw self::invalid($key, 'an integer or null');
    }

    /** @param array<mixed> $data */
    public static function bool(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;

        return \is_bool($value) ? $value : throw self::invalid($key, 'true or false');
    }

    /** @param array<mixed> $data */
    public static function dateTime(array $data, string $key): \DateTimeImmutable
    {
        return self::nullableDateTime($data, $key) ?? throw self::invalid($key, 'a date and time');
    }

    /** @param array<mixed> $data */
    public static function nullableDateTime(array $data, string $key): ?\DateTimeImmutable
    {
        $value = self::nullableString($data, $key);
        if ($value === null) {
            return null;
        }
        // ISO 8601 with Z or an offset, with or without fractions of a second; nothing PHP would merely guess at.
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,7})?(Z|[+-]\d{2}:\d{2})$/D', $value) !== 1) {
            throw self::invalid($key, 'a date and time');
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception $e) {
            throw new \UnexpectedValueException("The field {$key} is not a date and time.", 0, $e);
        }
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    public static function object(array $data, string $key): array
    {
        return self::nullableObject($data, $key) ?? throw self::invalid($key, 'an object');
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>|null
     */
    public static function nullableObject(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        return $value === null || \is_array($value) && (!array_is_list($value) || $value === []) ? $value : throw self::invalid($key, 'an object');
    }

    /**
     * @template T
     *
     * @param array<mixed>               $data
     * @param callable(array<mixed>): T  $read
     *
     * @return list<T>
     */
    public static function objects(array $data, string $key, callable $read): array
    {
        return array_map(
            static fn(mixed $item): mixed => \is_array($item) ? $read($item) : throw self::invalid($key, 'a list of objects'),
            self::list($data, $key),
        );
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    public static function strings(array $data, string $key): array
    {
        return array_map(
            static fn(mixed $item): string => \is_string($item) ? $item : throw self::invalid($key, 'a list of strings'),
            self::list($data, $key),
        );
    }

    /**
     * A known value as its enum case, an unknown one as the string it is.
     *
     * @template T of \BackedEnum
     *
     * @param array<mixed>    $data
     * @param class-string<T> $enum
     *
     * @return T|string
     */
    public static function enum(array $data, string $key, string $enum): \BackedEnum|string
    {
        $value = self::string($data, $key);

        return $enum::tryFrom($value) ?? $value;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param array<mixed>    $data
     * @param class-string<T> $enum
     *
     * @return T|string|null
     */
    public static function nullableEnum(array $data, string $key, string $enum): \BackedEnum|string|null
    {
        $value = self::nullableString($data, $key);

        return $value === null ? null : $enum::tryFrom($value) ?? $value;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param array<mixed>    $data
     * @param class-string<T> $enum
     *
     * @return list<T|string>
     */
    public static function enums(array $data, string $key, string $enum): array
    {
        return array_map(static fn(string $value): \BackedEnum|string => $enum::tryFrom($value) ?? $value, self::strings($data, $key));
    }

    /**
     * Leaves out the fields that are null, and writes enum cases and dates as the API reads them.
     *
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    public static function fields(array $fields): array
    {
        $out = [];
        foreach ($fields as $key => $value) {
            if ($value !== null) {
                $out[$key] = self::value($value);
            }
        }

        return $out;
    }

    private static function value(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d\TH:i:sP'),
            \is_array($value) => array_map(self::value(...), $value),
            default => $value,
        };
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<mixed>
     */
    private static function list(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return \is_array($value) && array_is_list($value) ? $value : throw self::invalid($key, 'a list');
    }

    private static function invalid(string $key, string $expected): \UnexpectedValueException
    {
        return new \UnexpectedValueException("The field {$key} is not {$expected}.");
    }
}
