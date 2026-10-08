<?php

declare(strict_types=1);

namespace Postilio\Tests\Http;

use Nyholm\Psr7\Request;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

/** A PSR-18 network failure, such as a refused connection. */
final class NetworkFailure extends \RuntimeException implements NetworkExceptionInterface
{
    public function getRequest(): RequestInterface
    {
        return new Request('GET', 'https://api.postilio.eu/');
    }
}
