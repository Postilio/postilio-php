<?php

declare(strict_types=1);

namespace Postilio\Tests;

/** Reads the payloads in tests/Fixtures. */
final class Fixtures
{
    public static function text(string $name): string
    {
        $text = file_get_contents(__DIR__ . '/Fixtures/' . $name);
        if ($text === false) {
            throw new \RuntimeException("No fixture {$name}.");
        }

        return $text;
    }

    /** One part of a fixture, such as the first item of its data, as JSON. */
    public static function item(string $name, string|int ...$path): string
    {
        $data = self::json($name);
        foreach ($path as $key) {
            $data = \is_array($data) && \array_key_exists($key, $data) ? $data[$key] : throw new \RuntimeException("No {$key} in {$name}.");
        }

        return json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }

    /** @return array<mixed> */
    public static function json(string $name): array
    {
        $data = json_decode(self::text($name), true, flags: \JSON_THROW_ON_ERROR);
        if (!\is_array($data)) {
            throw new \RuntimeException("Fixture {$name} is not a JSON object.");
        }

        return $data;
    }
}
