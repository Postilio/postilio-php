<?php

declare(strict_types=1);

namespace Postilio\Internal;

/** @internal */
final class HttpRequest
{
    /**
     * @param non-empty-string      $method
     * @param non-empty-string      $url
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly array $headers,
        public readonly ?string $body,
    ) {}
}
