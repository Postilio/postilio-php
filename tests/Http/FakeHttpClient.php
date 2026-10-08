<?php

declare(strict_types=1);

namespace Postilio\Tests\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** A PSR-18 client that answers from a queue and keeps every request it got, with its body read. */
final class FakeHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<string> */
    public array $bodies = [];

    /** @var list<ResponseInterface|\Throwable|'network'> */
    private array $answers = [];

    /** @param array<string, string> $headers */
    public function answer(int $status, string $body = '', array $headers = []): self
    {
        $this->answers[] = new Response($status, $body === '' ? $headers : $headers + ['Content-Type' => 'application/json'], $body);

        return $this;
    }

    public function fail(\Throwable $error): self
    {
        $this->answers[] = $error;

        return $this;
    }

    public function failNetwork(): self
    {
        $this->answers[] = 'network';

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $this->bodies[] = (string) $request->getBody();
        $answer = array_shift($this->answers) ?? throw new \LogicException('No answer queued for ' . $request->getMethod() . ' ' . $request->getUri());
        if ($answer === 'network') {
            throw new NetworkFailure('Connection refused', $request);
        }
        if ($answer instanceof \Throwable) {
            throw $answer;
        }

        return $answer;
    }

    public function last(): RequestInterface
    {
        return $this->requests[array_key_last($this->requests) ?? throw new \LogicException('No request was sent.')];
    }
}
