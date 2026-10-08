<?php

declare(strict_types=1);

namespace Postilio\Exception;

/** 400: a field is not valid. Fix the request before you send it again: the same request gets the same answer. */
final class ValidationException extends PostilioException
{
    /**
     * @param array<string, list<string>> $errors The problems per request field; `body` stands for `text` and `html`,
     *                                            `idempotencyKey` for the header.
     */
    public function __construct(string $message, public readonly array $errors, ?string $traceId = null)
    {
        parent::__construct($message, 400, traceId: $traceId);
    }
}
