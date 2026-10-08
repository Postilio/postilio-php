<?php

declare(strict_types=1);

namespace Postilio\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Postilio\Exception\PostilioException;
use Postilio\Exception\RateLimitException;
use Postilio\Exception\ServerException;
use Postilio\Exception\TransportException;
use Postilio\Model\CreateDomainRequest;
use Postilio\Model\SendEmailRequest;
use Postilio\Model\TestEmailRequest;
use Postilio\Model\UpdateWebhookEndpointRequest;
use Postilio\PostilioClient;
use Postilio\Tests\Http\FakeHttpClient;

final class RetryTest extends TestCase
{
    private const API_KEY = 'pk_test_abcdefghijklmnopqrstuvwxyz012345';
    private const ID = '01a1081b-eb5c-7685-ab38-fdd4a4ab10e5';

    private FakeHttpClient $http;

    /** @var list<float> */
    private array $waits = [];

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
    }

    #[Test]
    public function retriesAreOffByDefault(): void
    {
        $this->http->answer(503, '{"error":"service_degraded"}', ['Retry-After' => '1'])->answer(200, Fixtures::text('domain-created.json'));
        $factory = new Psr17Factory();
        $client = new PostilioClient(self::API_KEY, $this->http, $factory, $factory, sleep: $this->recordWait(...));

        $this->expectException(ServerException::class);
        try {
            $client->getDomain(self::ID);
        } finally {
            self::assertCount(1, $this->http->requests);
            self::assertSame([], $this->waits);
        }
    }

    /** @return iterable<string, array{int}> */
    public static function retryableStatuses(): iterable
    {
        foreach ([408, 429, 500, 502, 503, 504] as $status) {
            yield (string) $status => [$status];
        }
    }

    #[Test]
    #[DataProvider('retryableStatuses')]
    public function getIsRetriedOnAStatusThatMaySucceedLater(int $status): void
    {
        $this->http->answer($status)->answer(200, Fixtures::text('domain-created.json'));

        $domain = $this->client()->getDomain(self::ID);

        self::assertSame(self::ID, $domain->id);
        self::assertCount(2, $this->http->requests);
    }

    /** @return iterable<string, array{int}> */
    public static function finalStatuses(): iterable
    {
        foreach ([400, 401, 403, 404, 409, 413, 422, 501] as $status) {
            yield (string) $status => [$status];
        }
    }

    #[Test]
    #[DataProvider('finalStatuses')]
    public function getIsNotRetriedOnAStatusThatWillNotChange(int $status): void
    {
        $this->http->answer($status)->answer(200, Fixtures::text('domain-created.json'));

        $this->expectException(PostilioException::class);
        try {
            $this->client()->getDomain(self::ID);
        } finally {
            self::assertCount(1, $this->http->requests);
        }
    }

    #[Test]
    public function sendEmailIsRetriedWithTheSameIdempotencyKey(): void
    {
        $this->http->answer(500)->failNetwork()->answer(202, Fixtures::text('send-response.json'));

        $this->client()->sendEmail(self::welcome());

        self::assertCount(3, $this->http->requests);
        $key = $this->http->requests[0]->getHeaderLine('Idempotency-Key');
        self::assertNotSame('', $key);
        self::assertSame($key, $this->http->requests[1]->getHeaderLine('Idempotency-Key'));
        self::assertSame($key, $this->http->requests[2]->getHeaderLine('Idempotency-Key'));
        self::assertSame($this->http->bodies[0], $this->http->bodies[2]);
    }

    /** @return iterable<string, array{\Closure(PostilioClient): mixed, int}> */
    public static function writesWithoutAKey(): iterable
    {
        $id = self::ID;
        yield 'POST without a key, 503' => [static fn(PostilioClient $c) => $c->createDomain(new CreateDomainRequest('mail.example.com')), 503];
        yield 'POST without a key, 429' => [static fn(PostilioClient $c) => $c->createDomain(new CreateDomainRequest('mail.example.com')), 429];
        yield 'test email, 503' => [static fn(PostilioClient $c) => $c->sendTestEmail(new TestEmailRequest('a@mail.example.com', 'b@example.org')), 503];
        yield 'domain check, 429' => [static fn(PostilioClient $c) => $c->checkDomain($id), 429];
        yield 'PATCH, 503' => [static fn(PostilioClient $c) => $c->updateWebhookEndpoint($id, new UpdateWebhookEndpointRequest(paused: true)), 503];
        yield 'DELETE, 503' => [static fn(PostilioClient $c) => $c->cancelEmail($id), 503];
        yield 'DELETE, 429' => [static fn(PostilioClient $c) => $c->deleteDomain($id), 429];
    }

    /** @param \Closure(PostilioClient): mixed $call */
    #[Test]
    #[DataProvider('writesWithoutAKey')]
    public function writeWithoutAnIdempotencyKeyIsNeverRetried(\Closure $call, int $status): void
    {
        $this->http->answer($status)->answer(200, '{}');

        $this->expectException(PostilioException::class);
        try {
            $call($this->client());
        } finally {
            self::assertCount(1, $this->http->requests);
        }
    }

    #[Test]
    public function networkFailureOfAWriteWithoutAKeyIsNotRetried(): void
    {
        $this->http->failNetwork()->answer(201, Fixtures::text('domain-created.json'));

        $this->expectException(TransportException::class);
        try {
            $this->client()->createDomain(new CreateDomainRequest('mail.example.com'));
        } finally {
            self::assertCount(1, $this->http->requests);
        }
    }

    #[Test]
    public function transportFailureThatIsNotANetworkFailureIsNotRetried(): void
    {
        $this->http->fail(new class ('Invalid header') extends \RuntimeException implements \Psr\Http\Client\ClientExceptionInterface {})
            ->answer(200, Fixtures::text('domain-created.json'));

        $e = null;
        try {
            $this->client()->getDomain(self::ID);
        } catch (TransportException $e) {
        }

        self::assertFalse($e?->networkError);
        self::assertCount(1, $this->http->requests);
    }

    #[Test]
    public function waitsGrowFromHalfASecondWithSomeSpread(): void
    {
        $this->http->answer(503)->answer(503)->answer(503)->answer(200, Fixtures::text('domain-created.json'));

        $this->client(maxRetries: 3)->getDomain(self::ID);

        self::assertCount(3, $this->waits);
        self::assertEqualsWithDelta(0.5, $this->waits[0], 0.1);
        self::assertEqualsWithDelta(1.0, $this->waits[1], 0.2);
        self::assertEqualsWithDelta(2.0, $this->waits[2], 0.4);
    }

    #[Test]
    public function waitNeverExceedsTheMaximum(): void
    {
        $this->http->answer(503)->answer(503)->answer(200, Fixtures::text('domain-created.json'));

        $this->client(maxRetryDelay: 0.6)->getDomain(self::ID);

        self::assertCount(2, $this->waits);
        self::assertLessThanOrEqual(0.6, $this->waits[0]);
        self::assertSame(0.6, $this->waits[1]);
    }

    #[Test]
    public function retryAfterInSecondsIsHonoured(): void
    {
        $this->http->answer(429, '{"error":"too_many_checks"}', ['Retry-After' => '7'])->answer(200, Fixtures::text('domain-created.json'));

        $this->client()->getDomain(self::ID);

        self::assertSame([7.0], $this->waits);
    }

    #[Test]
    public function retryAfterAsADateIsHonoured(): void
    {
        $date = gmdate('D, d M Y H:i:s', time() + 10) . ' GMT';
        $this->http->answer(503, '', ['Retry-After' => $date])->answer(200, Fixtures::text('domain-created.json'));

        $this->client()->getDomain(self::ID);

        self::assertCount(1, $this->waits);
        self::assertEqualsWithDelta(10.0, $this->waits[0], 1.5);
    }

    #[Test]
    public function retryAfterInThePastRetriesAtOnce(): void
    {
        $this->http->answer(503, '', ['Retry-After' => 'Wed, 21 Oct 2015 07:28:00 GMT'])->answer(200, Fixtures::text('domain-created.json'));

        $this->client()->getDomain(self::ID);

        self::assertSame([0.0], $this->waits);
    }

    #[Test]
    public function retryAfterLongerThanTheMaximumThrowsAtOnce(): void
    {
        $this->http->answer(429, '{"error":"sandbox_daily_limit_reached"}', ['Retry-After' => '3600'])->answer(202, Fixtures::text('send-response.json'));

        try {
            $this->client()->sendEmail(self::welcome());
            self::fail('No exception.');
        } catch (RateLimitException $e) {
            self::assertSame(3600, $e->retryAfter);
        }
        self::assertCount(1, $this->http->requests);
        self::assertSame([], $this->waits);
    }

    #[Test]
    public function lastAnswerIsThrownOnceTheRetriesAreUsedUp(): void
    {
        $this->http->answer(502)->answer(502)->answer(503, '{"error":"service_degraded"}');

        try {
            $this->client()->getDomain(self::ID);
            self::fail('No exception.');
        } catch (ServerException $e) {
            self::assertSame(503, $e->status);
        }
        self::assertCount(3, $this->http->requests);
        self::assertCount(2, $this->waits);
    }

    #[Test]
    public function lastNetworkFailureIsThrownOnceTheRetriesAreUsedUp(): void
    {
        $this->http->failNetwork()->failNetwork()->failNetwork();

        $this->expectException(TransportException::class);
        try {
            $this->client()->getDomain(self::ID);
        } finally {
            self::assertCount(3, $this->http->requests);
            self::assertCount(2, $this->waits);
            self::assertEqualsWithDelta(0.5, $this->waits[0], 0.1);
        }
    }

    private function client(int $maxRetries = 2, float $maxRetryDelay = 30.0): PostilioClient
    {
        $factory = new Psr17Factory();

        return new PostilioClient(
            self::API_KEY,
            $this->http,
            $factory,
            $factory,
            maxRetries: $maxRetries,
            maxRetryDelay: $maxRetryDelay,
            sleep: $this->recordWait(...),
        );
    }

    private function recordWait(float $seconds): void
    {
        $this->waits[] = $seconds;
    }

    private static function welcome(): SendEmailRequest
    {
        return new SendEmailRequest('Acme <no-reply@mail.example.com>', ['delivered@simulator.postilio.eu'], 'Welcome', text: 'Hi.');
    }
}
