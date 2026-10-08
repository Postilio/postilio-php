<?php

declare(strict_types=1);

namespace Postilio\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Postilio\Enum\ErrorCode;
use Postilio\PostilioClient;

/**
 * Holds the client to spec/openapi-v1.json, the copy of the API's /v1 document: the copy is the one the platform's docs
 * describe, there is one method per operation, and one model per schema with the same fields, types and nullability.
 * Updating the copy makes these fail until the client follows.
 */
final class SpecTest extends TestCase
{
    private const SPEC = __DIR__ . '/../spec/openapi-v1.json';
    private const FINGERPRINT = __DIR__ . '/../spec/openapi-v1.sha256';

    // Error bodies are read leniently, and problem details carry a traceId the spec leaves out.
    private const ERROR_SCHEMAS = ['ErrorResponse', 'HttpValidationProblemDetails'];
    private const EXTENSIONS = ['HttpValidationProblemDetails.traceId'];

    #[Test]
    public function copyMatchesItsFingerprint(): void
    {
        self::assertSame(trim(self::read(self::FINGERPRINT)), hash_file('sha256', self::SPEC));
    }

    #[Test]
    public function copyIsTheOneThePlatformDocsDescribe(): void
    {
        $fingerprint = self::platform() . '/docs/openapi.sha256';
        if (!is_file($fingerprint)) {
            self::markTestSkipped("No platform checkout at {$fingerprint}; set POSTILIO_PLATFORM_DIR to compare with its fingerprint.");
        }

        self::assertSame(
            trim(self::read($fingerprint)),
            trim(self::read(self::FINGERPRINT)),
            'The platform changed its /v1 document: run tools/sync-spec.sh and follow these tests.',
        );
    }

    #[Test]
    public function errorCodesAreTheOnesThePlatformDocsList(): void
    {
        $errors = self::platform() . '/docs/errors.md';
        if (!is_file($errors)) {
            self::markTestSkipped("No platform checkout at {$errors}; set POSTILIO_PLATFORM_DIR to compare with its error codes.");
        }
        $table = explode('## The sandbox', explode('## Error codes', self::read($errors), 2)[1] ?? '', 2)[0];
        preg_match_all('/^\| `\d{3}` \| `([a-z_]+)` \|/m', $table, $matches);

        self::assertEqualsCanonicalizing($matches[1], array_map(static fn(ErrorCode $c): string => $c->value, ErrorCode::cases()));
    }

    #[Test]
    public function clientHasOneMethodPerOperation(): void
    {
        $operations = [];
        foreach (self::paths() as $path) {
            foreach ($path as $operation) {
                $operations[] = lcfirst(self::string($operation, 'operationId'));
            }
        }
        $methods = [];
        foreach ((new \ReflectionClass(PostilioClient::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (!$method->isStatic() && !str_starts_with($method->getName(), '__')) {
                $methods[] = $method->getName();
            }
        }
        sort($operations);
        sort($methods);

        self::assertSame($operations, $methods);
    }

    /** @return iterable<string, array{string}> */
    public static function schemaNames(): iterable
    {
        foreach (array_keys(self::schemas()) as $name) {
            yield $name => [$name];
        }
    }

    #[Test]
    #[DataProvider('schemaNames')]
    public function schemaHasAModelWithTheSameFields(string $schema): void
    {
        $class = 'Postilio\\Model\\' . $schema;
        self::assertTrue(class_exists($class), "No class {$class}.");
        $parameters = [];
        foreach ((new \ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $parameter) {
            if (!\in_array("{$schema}.{$parameter->getName()}", self::EXTENSIONS, true)) {
                $parameters[$parameter->getName()] = $parameter;
            }
        }
        $fields = self::schemas()[$schema]['properties'] ?? [];
        self::assertIsArray($fields);

        self::assertEqualsCanonicalizing(array_keys($fields), array_keys($parameters));
        $mismatches = [];
        foreach ($fields as $name => $field) {
            self::assertIsArray($field);
            $problem = self::mismatch($field, $parameters[$name], self::isResponse($schema));
            if ($problem !== null) {
                $mismatches[] = "{$schema}.{$name}: {$problem}";
            }
        }
        self::assertSame([], $mismatches);
    }

    /** @param array<mixed> $field */
    private static function mismatch(array $field, \ReflectionParameter $parameter, bool $checkNullability): ?string
    {
        $type = $parameter->getType();
        if ($type === null) {
            return 'no type';
        }
        $variants = $field['oneOf'] ?? [$field];
        self::assertIsArray($variants);
        $nullable = ($field['nullable'] ?? false) === true;
        $reference = null;
        foreach ($variants as $variant) {
            self::assertIsArray($variant);
            $nullable = $nullable || ($variant['nullable'] ?? false) === true;
            $reference ??= isset($variant['$ref']) && \is_string($variant['$ref']) ? $variant['$ref'] : null;
        }
        if ($checkNullability && $nullable !== $type->allowsNull()) {
            return 'nullable is ' . var_export($nullable, true) . " in the spec, {$type} in PHP";
        }
        $names = [];
        foreach ($type instanceof \ReflectionUnionType ? $type->getTypes() : [$type] as $part) {
            if ($part instanceof \ReflectionNamedType) {
                $names[] = $part->getName();
            }
        }
        $expected = $reference !== null
            ? ['Postilio\\Model\\' . basename($reference)]
            : match ([$field['type'] ?? null, $field['format'] ?? null]) {
                ['string', 'date-time'] => [\DateTimeImmutable::class, \DateTimeInterface::class],
                ['string', 'uuid'], ['string', null] => ['string'],
                ['integer', 'int16'], ['integer', 'int32'], ['integer', 'int64'] => ['int'],
                ['boolean', null] => ['bool'],
                ['array', null], ['object', null] => ['array'],
                default => [],
            };

        return array_intersect($expected, $names) === [] ? json_encode($field) . " in the spec, {$type} in PHP" : null;
    }

    // A request model may require a field the server's record marks nullable; what comes back must match exactly.
    private static function isResponse(string $schema): bool
    {
        if (\in_array($schema, self::ERROR_SCHEMAS, true)) {
            return false;
        }
        $found = [];
        foreach (self::paths() as $path) {
            foreach ($path as $operation) {
                self::collectReferences($operation['responses'] ?? [], $found);
            }
        }

        return isset($found[$schema]);
    }

    /** @param array<string, true> $found */
    private static function collectReferences(mixed $node, array &$found): void
    {
        if (!\is_array($node)) {
            return;
        }
        if (isset($node['$ref']) && \is_string($node['$ref'])) {
            $name = basename($node['$ref']);
            if (!isset($found[$name])) {
                $found[$name] = true;
                self::collectReferences(self::schemas()[$name] ?? null, $found);
            }

            return;
        }
        foreach ($node as $child) {
            self::collectReferences($child, $found);
        }
    }

    /** @return array<string, array<string, array<mixed>>> */
    private static function paths(): array
    {
        $paths = self::spec()['paths'] ?? null;
        self::assertIsArray($paths);

        /** @var array<string, array<string, array<mixed>>> $paths */
        return $paths;
    }

    /** @return array<string, array<mixed>> */
    private static function schemas(): array
    {
        $components = self::spec()['components'] ?? null;
        self::assertIsArray($components);
        $schemas = $components['schemas'] ?? null;
        self::assertIsArray($schemas);

        /** @var array<string, array<mixed>> $schemas */
        return $schemas;
    }

    /** @return array<mixed> */
    private static function spec(): array
    {
        static $spec = null;
        $spec ??= json_decode(self::read(self::SPEC), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($spec);

        return $spec;
    }

    /** @param array<mixed> $data */
    private static function string(array $data, string $key): string
    {
        self::assertIsString($data[$key] ?? null);

        return $data[$key];
    }

    // A checkout of the Postilio platform repository: next to this one, or where POSTILIO_PLATFORM_DIR says.
    private static function platform(): string
    {
        return getenv('POSTILIO_PLATFORM_DIR') ?: __DIR__ . '/../../Postilio';
    }

    private static function read(string $file): string
    {
        $content = file_get_contents($file);
        self::assertIsString($content);

        return $content;
    }
}
