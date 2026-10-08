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
