<?php

declare(strict_types=1);

namespace Postilio\Exception;

/** No answer came: the connection failed or timed out, or the HTTP client refused the request. */
final class TransportException extends PostilioException
{
    public function __construct(
        string $message,
        /** True when the request may not have reached Postilio, so sending it again can help. */
        public readonly bool $networkError,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
