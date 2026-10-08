<?php

declare(strict_types=1);

namespace Postilio\Tests\Http;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

/** A PSR-18 network failure, such as a refused connection; like real clients, it keeps the request. */
final class NetworkFailure extends \RuntimeException implements NetworkExceptionInterface
{
    public function __construct(string $message, private readonly RequestInterface $request)
    {
        parent::__construct($message);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
