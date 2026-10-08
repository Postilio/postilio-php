<?php

declare(strict_types=1);

namespace Postilio\Exception;

use Postilio\Enum\ErrorCode;

/**
 * An error answer of the API, or a request that got none. A subclass per status says what kind; the message names the
 * call and the status, never the API key.
 */
class PostilioException extends \RuntimeException
{
    public function __construct(
        string $message,
        /** The HTTP status; 0 when no answer came. Also getCode(). */
        public readonly int $status = 0,
        /** The API's stable code, such as `unverified_sender_domain`; null when the answer had none. */
        public readonly ?string $errorCode = null,
        /** A sentence that comes with some codes, such as the limit that was crossed. */
        public readonly ?string $errorMessage = null,
        /** Quote it when you contact support. */
        public readonly ?string $traceId = null,
        /** The Retry-After the answer carried, in seconds. */
        public readonly ?int $retryAfter = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    /** The code as its enum case; null for no code, or one this version of the SDK does not know yet. */
    public function error(): ?ErrorCode
    {
        return $this->errorCode === null ? null : ErrorCode::tryFrom($this->errorCode);
    }
}
