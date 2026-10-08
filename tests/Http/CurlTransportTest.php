<?php

declare(strict_types=1);

namespace Postilio\Tests\Http;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Postilio\Exception\PostilioException;
use Postilio\Exception\RateLimitException;
use Postilio\Exception\TransportException;
use Postilio\Internal\CurlTransport;
use Postilio\Internal\HttpRequest;
use Postilio\PostilioClient;

/** The built-in transport against a local `php -S` server (tests/Http/echo-server.php). */
#[RequiresPhpExtension('curl')]
final class CurlTransportTest extends TestCase
{
    private const API_KEY = 'pk_test_abcdefghijklmnopqrstuvwxyz012345';

    /** @var resource|null */
    private static $server;
    private static string $address;

    public static function setUpBeforeClass(): void
    {
        self::$address = self::freeAddress();
        $command = [\PHP_BINARY, '-S', self::$address, __DIR__ . '/echo-server.php'];
        self::$server = proc_open($command, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes) ?: null;
        [$host, $port] = explode(':', self::$address);
        for ($i = 0; $i < 50; $i++) {
            $connection = @fsockopen($host, (int) $port, timeout: 0.1);
            if ($connection !== false) {
                fclose($connection);

                return;
            }
            usleep(100_000);
        }
        throw new \RuntimeException('The test server did not start.');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
    }

    #[Test]
    public function sendSendsTheMethodHeadersAndBodyAndReadsTheAnswer(): void
    {
        $body = '{"name":"' . str_repeat('a', 4000) . '.example.com"}';
        $request = new HttpRequest('PATCH', 'http://' . self::$address . '/echo?x=1', ['Authorization' => 'Bearer k', 'Content-Type' => 'application/json', 'Idempotency-Key' => 'k-1'], $body);

        $response = (new CurlTransport(5.0, 2.0))->send($request);

        self::assertSame(200, $response->status);
        self::assertSame('yes', $response->header('X-Answer'));
        self::assertSame([
            'method' => 'PATCH',
            'uri' => '/echo?x=1',
            'authorization' => 'Bearer k',
            'contentType' => 'application/json',
            'idempotencyKey' => 'k-1',
            'expect' => null,
            'body' => $body,
        ], json_decode($response->body, true));
    }

    #[Test]
    public function sendWithoutABodySendsNone(): void
    {
        $response = (new CurlTransport(5.0, 2.0))->send(new HttpRequest('DELETE', 'http://' . self::$address . '/echo', [], null));

        $echo = json_decode($response->body, true);
        self::assertIsArray($echo);
        self::assertSame('', $echo['body']);
    }

    #[Test]
    public function errorAnswerIsMappedWithItsRetryAfter(): void
    {
        $client = new PostilioClient(self::API_KEY, baseUrl: 'http://' . self::$address . '/status/429');

        try {
            $client->listDomains();
            self::fail('No exception.');
        } catch (RateLimitException $e) {
            self::assertSame('from_the_server', $e->errorCode);
            self::assertSame(5, $e->retryAfter);
        }
    }

    #[Test]
    public function redirectIsNotFollowed(): void
    {
        $client = new PostilioClient(self::API_KEY, baseUrl: 'http://' . self::$address . '/redirect');

        $this->expectException(PostilioException::class);
        $this->expectExceptionCode(302);

        $client->listDomains();
    }

    #[Test]
    public function timeoutIsANetworkFailure(): void
    {
        $client = new PostilioClient(self::API_KEY, baseUrl: 'http://' . self::$address . '/slow', timeout: 0.5);

        try {
            $client->listDomains();
            self::fail('No exception.');
        } catch (TransportException $e) {
            self::assertTrue($e->networkError);
            self::assertStringStartsWith('GET /v1/domains failed: ', $e->getMessage());
        }
    }

    #[Test]
    public function refusedConnectionIsANetworkFailure(): void
    {
        $client = new PostilioClient(self::API_KEY, baseUrl: 'http://' . self::freeAddress());

        try {
            $client->listDomains();
            self::fail('No exception.');
        } catch (TransportException $e) {
            self::assertTrue($e->networkError);
        }
    }

    #[Test]
    public function failureKeepsTheKeyOutOfItsTraceWhenArgumentsAreKept(): void
    {
        $ignoreArgs = (string) ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', '0');
        try {
            (new PostilioClient(self::API_KEY, baseUrl: 'http://' . self::freeAddress()))->listDomains();
            self::fail('No exception.');
        } catch (TransportException $e) {
            self::assertStringNotContainsString(self::API_KEY, print_r($e, true));
        } finally {
            ini_set('zend.exception_ignore_args', $ignoreArgs);
        }
    }

    private static function freeAddress(): string
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0') ?: throw new \RuntimeException('No free port.');
        $address = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return $address;
    }
}
