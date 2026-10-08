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

    /**
     * Without the API key: a trace that keeps arguments (zend.exception_ignore_args off) holds this request.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['method' => $this->method, 'url' => $this->url, 'headers' => ['Authorization' => '[redacted]'] + $this->headers];
    }
}
