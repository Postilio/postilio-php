<?php

declare(strict_types=1);

namespace Postilio\Internal;

use Postilio\Exception\TransportException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Sends through the caller's PSR-18 client. Its timeouts and TLS settings are the client's own.
 *
 * @internal
 */
final class Psr18Transport implements Transport
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

    public function send(HttpRequest $request): HttpResponse
    {
        $message = $this->requestFactory->createRequest($request->method, $request->url);
        foreach ($request->headers as $name => $value) {
            $message = $message->withHeader($name, $value);
        }
        if ($request->body !== null) {
            $message = $message->withBody($this->streamFactory->createStream($request->body));
        }
        try {
            $response = $this->client->sendRequest($message);
        } catch (ClientExceptionInterface $e) {
            throw new TransportException($e->getMessage(), $e instanceof NetworkExceptionInterface, $e);
        }
        $headers = [];
        foreach (array_keys($response->getHeaders()) as $name) {
            $headers[strtolower((string) $name)] = $response->getHeaderLine((string) $name);
        }

        return new HttpResponse($response->getStatusCode(), $headers, (string) $response->getBody());
    }
}
