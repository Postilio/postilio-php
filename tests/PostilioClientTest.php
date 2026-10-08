<?php

declare(strict_types=1);

namespace Postilio\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Postilio\Enum\ErrorCode;
use Postilio\Enum\SuppressionReason;
use Postilio\Enum\WebhookDeliveryStatus;
use Postilio\Enum\WebhookEventType;
use Postilio\Exception\AuthenticationException;
use Postilio\Exception\ConflictException;
use Postilio\Exception\NotFoundException;
use Postilio\Exception\PayloadTooLargeException;
use Postilio\Exception\PermissionException;
use Postilio\Exception\PlanLimitException;
use Postilio\Exception\PostilioException;
use Postilio\Exception\RateLimitException;
use Postilio\Exception\ServerException;
use Postilio\Exception\TransportException;
use Postilio\Exception\UnprocessableException;
use Postilio\Exception\ValidationException;
use Postilio\Model\ApiUsage;
use Postilio\Model\CreateDomainRequest;
use Postilio\Model\CreatedWebhookEndpoint;
use Postilio\Model\CreateSuppressionRequest;
use Postilio\Model\CreateWebhookEndpointRequest;
use Postilio\Model\DomainList;
use Postilio\Model\DomainResponse;
use Postilio\Model\EmailDetails;
use Postilio\Model\RemoveSuppressionRequest;
use Postilio\Model\RotatedWebhookSecret;
use Postilio\Model\SendEmailRequest;
use Postilio\Model\SuppressionList;
use Postilio\Model\SuppressionResponse;
use Postilio\Model\TestEmailRequest;
use Postilio\Model\TestEmailResponse;
use Postilio\Model\UpdateWebhookEndpointRequest;
use Postilio\Model\WebhookDeliveryList;
use Postilio\Model\WebhookDeliveryResponse;
use Postilio\Model\WebhookEndpointList;
use Postilio\Model\WebhookEndpointResponse;
use Postilio\PostilioClient;
use Postilio\Tests\Http\FakeHttpClient;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;

final class PostilioClientTest extends TestCase
{
    private const API_KEY = 'pk_test_abcdefghijklmnopqrstuvwxyz012345';
    private const ID = '01a10ce5-a09a-788b-9243-d7c9c59773d2';
    private const OTHER_ID = '01a10f10-aaaa-7bbb-8ccc-ddddeeeeffff';

    private FakeHttpClient $http;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
    }

    #[Test]
    public function sendEmailPostsTheRequestAsJsonWithTheKeyAndTheSdkVersion(): void
    {
        $this->http->answer(202, Fixtures::text('send-response.json'));

        $sent = $this->client()->sendEmail(new SendEmailRequest('Acme <no-reply@mail.example.com>', ['ada.lovelace@example.com'], 'Hi', text: 'Hi.'));

        $request = $this->http->last();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://api.postilio.eu/v1/emails', (string) $request->getUri());
        self::assertSame('Bearer ' . self::API_KEY, $request->getHeaderLine('Authorization'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertStringStartsWith('postilio-php/' . PostilioClient::VERSION . ' ', $request->getHeaderLine('User-Agent'));
        self::assertSame('{"from":"Acme <no-reply@mail.example.com>","to":["ada.lovelace@example.com"],"subject":"Hi","text":"Hi."}', $this->http->bodies[0]);
        self::assertSame(['01a10ce5-a09a-788b-9243-d7c9c59773d2', '01a10ce5-a09a-7dde-9fde-5676dcbd0094'], $sent->ids);
    }

    #[Test]
    public function sendEmailWithoutAKeyMakesAnIdempotencyKeyPerCall(): void
    {
        $this->http->answer(202, Fixtures::text('send-response.json'))->answer(202, Fixtures::text('send-response.json'));
        $client = $this->client();

        $client->sendEmail(self::welcome());
        $client->sendEmail(self::welcome());

        $first = $this->http->requests[0]->getHeaderLine('Idempotency-Key');
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $first);
        self::assertNotSame($first, $this->http->requests[1]->getHeaderLine('Idempotency-Key'));
    }

    #[Test]
    public function sendEmailWithAKeySendsThatKey(): void
    {
        $this->http->answer(202, Fixtures::text('send-response.json'));

        $this->client()->sendEmail(self::welcome(), 'order-1042-receipt');

        self::assertSame('order-1042-receipt', $this->http->last()->getHeaderLine('Idempotency-Key'));
    }

    #[Test]
    public function sendEmailWithAnEmptyKeyThrowsWithoutSending(): void
    {
        try {
            $this->client()->sendEmail(self::welcome(), '');
            self::fail('No exception.');
        } catch (\InvalidArgumentException) {
            self::assertSame([], $this->http->requests);
        }
    }

    /**
     * @return iterable<string, array{\Closure(PostilioClient): mixed, string, string, string, int, string, class-string|null}>
     */
    public static function operations(): iterable
    {
        $id = self::ID;
        $other = self::OTHER_ID;
        $endpoint = Fixtures::item('created-webhook-endpoint.json', 'endpoint');
        $delivery = Fixtures::item('webhook-delivery-list.json', 'data', 0);
        $suppression = Fixtures::item('suppression-list.json', 'data', 0);

        yield 'sendTestEmail' => [static fn(PostilioClient $c) => $c->sendTestEmail(new TestEmailRequest('no-reply@mail.example.com', 'ada@example.org')),
            'POST', '/v1/emails/test', '{"from":"no-reply@mail.example.com","to":"ada@example.org"}', 202, Fixtures::text('test-email-response.json'), TestEmailResponse::class];
        yield 'getEmail' => [static fn(PostilioClient $c) => $c->getEmail($id), 'GET', "/v1/emails/{$id}", '', 200, Fixtures::text('email-bounced.json'), EmailDetails::class];
        yield 'cancelEmail' => [static fn(PostilioClient $c) => $c->cancelEmail($id), 'DELETE', "/v1/emails/{$id}", '', 204, '', null];
        yield 'createDomain' => [static fn(PostilioClient $c) => $c->createDomain(new CreateDomainRequest('mail.example.com')),
            'POST', '/v1/domains', '{"name":"mail.example.com"}', 201, Fixtures::text('domain-created.json'), DomainResponse::class];
        yield 'listDomains' => [static fn(PostilioClient $c) => $c->listDomains(), 'GET', '/v1/domains', '', 200, '{"data":[]}', DomainList::class];
        yield 'getDomain' => [static fn(PostilioClient $c) => $c->getDomain($id), 'GET', "/v1/domains/{$id}", '', 200, Fixtures::text('domain-verified.json'), DomainResponse::class];
        yield 'deleteDomain' => [static fn(PostilioClient $c) => $c->deleteDomain($id), 'DELETE', "/v1/domains/{$id}", '', 204, '', null];
        yield 'checkDomain' => [static fn(PostilioClient $c) => $c->checkDomain($id), 'POST', "/v1/domains/{$id}/check", '', 200, Fixtures::text('domain-verified.json'), DomainResponse::class];
        yield 'listSuppressions' => [static fn(PostilioClient $c) => $c->listSuppressions(), 'GET', '/v1/suppressions', '', 200, Fixtures::text('suppression-list.json'), SuppressionList::class];
        yield 'createSuppression' => [static fn(PostilioClient $c) => $c->createSuppression(new CreateSuppressionRequest('former.customer@example.org')),
            'POST', '/v1/suppressions', '{"address":"former.customer@example.org"}', 201, $suppression, SuppressionResponse::class];
        yield 'deleteSuppression' => [static fn(PostilioClient $c) => $c->deleteSuppression($id), 'DELETE', "/v1/suppressions/{$id}", '', 204, '', null];
        yield 'deleteSuppression with a reason' => [static fn(PostilioClient $c) => $c->deleteSuppression($id, new RemoveSuppressionRequest('Subscribed again on 5 October.')),
            'DELETE', "/v1/suppressions/{$id}", '{"reason":"Subscribed again on 5 October."}', 204, '', null];
        yield 'listWebhookEndpoints' => [static fn(PostilioClient $c) => $c->listWebhookEndpoints(), 'GET', '/v1/webhooks', '', 200, Fixtures::text('webhook-endpoint-list.json'), WebhookEndpointList::class];
        yield 'createWebhookEndpoint' => [static fn(PostilioClient $c) => $c->createWebhookEndpoint(new CreateWebhookEndpointRequest('https://api.example.com/hooks/postilio', [WebhookEventType::Delivered])),
            'POST', '/v1/webhooks', '{"url":"https://api.example.com/hooks/postilio","events":["delivered"]}', 201, Fixtures::text('created-webhook-endpoint.json'), CreatedWebhookEndpoint::class];
        yield 'getWebhookEndpoint' => [static fn(PostilioClient $c) => $c->getWebhookEndpoint($id), 'GET', "/v1/webhooks/{$id}", '', 200, $endpoint, WebhookEndpointResponse::class];
        yield 'updateWebhookEndpoint' => [static fn(PostilioClient $c) => $c->updateWebhookEndpoint($id, new UpdateWebhookEndpointRequest(paused: false)),
            'PATCH', "/v1/webhooks/{$id}", '{"paused":false}', 200, $endpoint, WebhookEndpointResponse::class];
        yield 'deleteWebhookEndpoint' => [static fn(PostilioClient $c) => $c->deleteWebhookEndpoint($id), 'DELETE', "/v1/webhooks/{$id}", '', 204, '', null];
        yield 'rotateWebhookSecret' => [static fn(PostilioClient $c) => $c->rotateWebhookSecret($id), 'POST', "/v1/webhooks/{$id}/secret", '', 200, Fixtures::text('rotated-webhook-secret.json'), RotatedWebhookSecret::class];
        yield 'sendWebhookTestEvent' => [static fn(PostilioClient $c) => $c->sendWebhookTestEvent($id), 'POST', "/v1/webhooks/{$id}/test", '', 202, $delivery, WebhookDeliveryResponse::class];
        yield 'listWebhookDeliveries' => [static fn(PostilioClient $c) => $c->listWebhookDeliveries($id), 'GET', "/v1/webhooks/{$id}/deliveries", '', 200, Fixtures::text('webhook-delivery-list.json'), WebhookDeliveryList::class];
        yield 'retryWebhookDelivery' => [static fn(PostilioClient $c) => $c->retryWebhookDelivery($id, $other), 'POST', "/v1/webhooks/{$id}/deliveries/{$other}/retry", '', 202, '', null];
        yield 'getUsage' => [static fn(PostilioClient $c) => $c->getUsage(), 'GET', '/v1/usage', '', 200, Fixtures::text('usage.json'), ApiUsage::class];
    }

    /**
     * @param \Closure(PostilioClient): mixed $call
     * @param class-string|null                       $answerType
     */
    #[Test]
    #[DataProvider('operations')]
    public function operationSendsItsMethodPathAndBodyAndReadsTheAnswer(
        \Closure $call,
        string $method,
        string $path,
        string $body,
        int $status,
        string $answer,
        ?string $answerType,
    ): void {
        $this->http->answer($status, $answer);

        $result = $call($this->client());

        self::assertSame($method, $this->http->last()->getMethod());
        self::assertSame('https://api.postilio.eu' . $path, (string) $this->http->last()->getUri());
        self::assertSame($body, $this->http->bodies[0]);
        self::assertSame($body === '' ? '' : 'application/json', $this->http->last()->getHeaderLine('Content-Type'));
        self::assertSame('', $this->http->last()->getHeaderLine('Idempotency-Key'));
        if ($answerType === null) {
            self::assertNull($result);
        } else {
            self::assertInstanceOf($answerType, $result);
        }
    }

    #[Test]
    public function listSuppressionsEscapesTheFiltersIntoTheQuery(): void
    {
        $this->http->answer(200, '{"data":[],"next":null}');

        $this->client()->listSuppressions('ada+test@example.com', SuppressionReason::HardBounce, self::ID, 20);

        self::assertSame(
            'https://api.postilio.eu/v1/suppressions?q=ada%2Btest%40example.com&reason=hard_bounce&before=' . self::ID . '&limit=20',
            (string) $this->http->last()->getUri(),
        );
    }

    #[Test]
    public function listWebhookDeliveriesPutsTheFiltersInTheQuery(): void
    {
        $this->http->answer(200, '{"data":[],"next":null}');

        $this->client()->listWebhookDeliveries(self::ID, WebhookDeliveryStatus::Failed, self::OTHER_ID, 5);

        self::assertSame(
            'https://api.postilio.eu/v1/webhooks/' . self::ID . '/deliveries?status=failed&before=' . self::OTHER_ID . '&limit=5',
            (string) $this->http->last()->getUri(),
        );
    }

    #[Test]
    public function getUsagePutsTheMonthInTheQuery(): void
    {
        $this->http->answer(200, Fixtures::text('usage.json'));

        $this->client()->getUsage('2026-10');

        self::assertSame('https://api.postilio.eu/v1/usage?month=2026-10', (string) $this->http->last()->getUri());
    }

    #[Test]
    public function idIsEscapedInThePath(): void
    {
        $this->http->answer(404);

        try {
            $this->client()->getEmail('../domains?x=1');
        } catch (NotFoundException) {
        }

        self::assertSame('https://api.postilio.eu/v1/emails/..%2Fdomains%3Fx%3D1', (string) $this->http->last()->getUri());
    }

    #[Test]
    public function baseUrlKeepsItsPathWithOrWithoutATrailingSlash(): void
    {
        $this->http->answer(200, '{"data":[]}')->answer(200, '{"data":[]}');

        $this->client(baseUrl: 'http://localhost:26299/postilio/')->listDomains();
        $this->client(baseUrl: 'http://localhost:26299/postilio')->listDomains();

        self::assertSame('http://localhost:26299/postilio/v1/domains', (string) $this->http->requests[0]->getUri());
        self::assertSame('http://localhost:26299/postilio/v1/domains', (string) $this->http->requests[1]->getUri());
    }

    /**
     * @return iterable<string, array{int, string, array<string, string>, class-string<PostilioException>, string|null, string|null, int|null}>
     */
    public static function errors(): iterable
    {
        yield '401' => [401, '{"error":"invalid_api_key"}', [], AuthenticationException::class, 'invalid_api_key', null, null];
        yield '403' => [403, '{"error":"insufficient_scope"}', [], PermissionException::class, 'insufficient_scope', null, null];
        yield '404 without a body' => [404, '', [], NotFoundException::class, null, null, null];
        yield '409' => [409, '{"error":"domain_exists"}', [], ConflictException::class, 'domain_exists', null, null];
        yield '409 limit of the plan' => [409, '{"error":"domain_limit_reached"}', [], ConflictException::class, 'domain_limit_reached', null, null];
        yield '413 with a message' => [413, Fixtures::text('error-message-too-large.json'), [], PayloadTooLargeException::class,
            'message_too_large', 'A message may be at most 10485760 bytes; this one is 10485761.', null];
        yield '422' => [422, '{"error":"cc_bcc_require_single_to"}', [], UnprocessableException::class, 'cc_bcc_require_single_to', null, null];
        yield '429 sandbox' => [429, '{"error":"sandbox_daily_limit_reached"}', ['Retry-After' => '3600'], RateLimitException::class, 'sandbox_daily_limit_reached', null, 3600];
        yield '429 plan month' => [429, '{"error":"plan_monthly_limit_reached"}', ['Retry-After' => '86400'], PlanLimitException::class, 'plan_monthly_limit_reached', null, 86400];
        yield '429 plan day' => [429, '{"error":"plan_daily_limit_reached"}', [], PlanLimitException::class, 'plan_daily_limit_reached', null, null];
        yield '429 plan minute' => [429, '{"error":"plan_rate_limit_reached"}', ['Retry-After' => '12'], PlanLimitException::class, 'plan_rate_limit_reached', null, 12];
        yield '429 a code added later' => [429, '{"error":"a_later_limit"}', [], RateLimitException::class, 'a_later_limit', null, null];
        yield '503 degraded' => [503, '{"error":"service_degraded"}', ['Retry-After' => '30'], ServerException::class, 'service_degraded', null, 30];
        yield '502 from a proxy' => [502, '<html>Bad gateway</html>', [], ServerException::class, null, null, null];
        yield '418 another status' => [418, '{"error":"teapot"}', [], PostilioException::class, 'teapot', null, null];
    }

    /**
     * @param array<string, string>            $headers
     * @param class-string<PostilioException>  $type
     */
    #[Test]
    #[DataProvider('errors')]
    public function errorAnswerThrowsTheExceptionOfItsStatusWithTheCodeAndMessage(
        int $status,
        string $body,
        array $headers,
        string $type,
        ?string $code,
        ?string $message,
        ?int $retryAfter,
    ): void {
        $this->http->answer($status, $body, $headers);

        try {
            $this->client()->listDomains();
            self::fail('No exception.');
        } catch (PostilioException $e) {
            self::assertSame($type, $e::class);
            self::assertSame($status, $e->status);
            self::assertSame($status, $e->getCode());
            self::assertSame($code, $e->errorCode);
            self::assertSame($message, $e->errorMessage);
            self::assertSame($retryAfter, $e->retryAfter);
            self::assertSame(
                "GET /v1/domains answered {$status}" . ($code === null ? '' : " ({$code})") . ($message === null ? '' : ': ' . rtrim($message, '.')) . '.',
                $e->getMessage(),
            );
        }
    }

    #[Test]
    public function errorOfAKnownCodeIsItsEnumCase(): void
    {
        $this->http->answer(422, '{"error":"unverified_sender_domain"}')->answer(429, '{"error":"a_later_limit"}');

        $known = self::catch(fn() => $this->client()->sendEmail(self::welcome()));
        $unknown = self::catch(fn() => $this->client()->sendEmail(self::welcome()));

        self::assertSame(ErrorCode::UnverifiedSenderDomain, $known->error());
        self::assertNull($unknown->error());
    }

    #[Test]
    public function validationErrorHoldsTheProblemsPerFieldAndTheTraceId(): void
    {
        $this->http->answer(400, Fixtures::text('problem-details.json'), ['Content-Type' => 'application/problem+json']);

        $e = self::catch(fn() => $this->client()->sendEmail(self::welcome()));

        self::assertInstanceOf(ValidationException::class, $e);
        self::assertSame(400, $e->status);
        self::assertSame(['to' => ['Between 1 and 50 recipients are required.'], 'body' => ['Either text or html is required.']], $e->errors);
        self::assertSame('00-4d6a1c9b71484cc59ac852cda603b93c-28de36d1c118bc08-00', $e->traceId);
        self::assertSame('POST /v1/emails answered 400: to: Between 1 and 50 recipients are required. body: Either text or html is required.', $e->getMessage());
    }

    /** @return iterable<string, array{string}> */
    public static function bodiesWithoutProblems(): iterable
    {
        yield 'no body' => [''];
        yield 'problems of another shape' => ['{"errors":{"to":"not a list"},"traceId":"00-abc-def-00"}'];
    }

    #[Test]
    #[DataProvider('bodiesWithoutProblems')]
    public function validationErrorWithoutReadableProblemsHasNone(string $body): void
    {
        $this->http->answer(400, $body);

        $e = self::catch(fn() => $this->client()->listDomains());

        self::assertInstanceOf(ValidationException::class, $e);
        self::assertSame([], $e->errors);
        self::assertNull($e->traceId);
        self::assertSame('GET /v1/domains answered 400.', $e->getMessage());
    }

    #[Test]
    public function serverErrorHoldsTheTraceIdToQuote(): void
    {
        $this->http->answer(500, '{"title":"An error occurred while processing your request.","status":500,"traceId":"00-abc-def-00"}');

        $e = self::catch(fn() => $this->client()->listDomains());

        self::assertInstanceOf(ServerException::class, $e);
        self::assertSame('00-abc-def-00', $e->traceId);
    }

    #[Test]
    public function errorMessageLeavesOutTheQueryAndNeverHoldsTheKey(): void
    {
        $this->http->answer(401, '{"error":"invalid_api_key"}');

        $e = self::catch(fn() => $this->client()->listSuppressions('ada@example.com'));

        self::assertSame('GET /v1/suppressions answered 401 (invalid_api_key).', $e->getMessage());
        self::assertStringNotContainsString(self::API_KEY, (string) $e);
    }

    #[Test]
    public function answerThatIsNotTheExpectedJsonThrows(): void
    {
        $this->http->answer(200, '{"data": "not a list"}');

        $e = self::catch(fn() => $this->client()->listDomains());

        self::assertSame(PostilioException::class, $e::class);
        self::assertSame(200, $e->status);
        self::assertSame('GET /v1/domains answered 200 with a body that is not the expected JSON: The field data is not a list.', $e->getMessage());
        self::assertInstanceOf(\UnexpectedValueException::class, $e->getPrevious());
    }

    #[Test]
    public function transportFailureThrowsATransportExceptionWithTheCause(): void
    {
        $this->http->failNetwork();

        $e = self::catch(fn() => $this->client()->listDomains());

        self::assertInstanceOf(TransportException::class, $e);
        self::assertTrue($e->networkError);
        self::assertSame(0, $e->status);
        self::assertSame('GET /v1/domains failed: Connection refused', $e->getMessage());
        self::assertSame('Connection refused', $e->getPrevious()?->getMessage());
    }

    #[Test]
    public function clientUsesTheFactoriesItIsGiven(): void
    {
        $this->http->answer(200, '{"data":[]}');
        $factory = new class implements RequestFactoryInterface {
            public int $requests = 0;

            public function createRequest(string $method, $uri): RequestInterface
            {
                $this->requests++;

                return (new Psr17Factory())->createRequest($method, $uri)->withHeader('X-Made-By', 'the given factory');
            }
        };

        (new PostilioClient(self::API_KEY, $this->http, $factory, new Psr17Factory()))->listDomains();

        self::assertSame(1, $factory->requests);
        self::assertSame('the given factory', $this->http->last()->getHeaderLine('X-Made-By'));
    }

    #[Test]
    public function clientWithoutFactoriesFindsThemThroughDiscovery(): void
    {
        $this->http->answer(200, '{"data":[]}');

        $domains = (new PostilioClient(self::API_KEY, $this->http))->listDomains();

        self::assertSame([], $domains->data);
    }

    /** @return iterable<string, array{\Closure(): PostilioClient}> */
    public static function invalidSettings(): iterable
    {
        yield 'not an api key' => [static fn() => new PostilioClient('sk_live_123')];
        yield 'empty key' => [static fn() => new PostilioClient('')];
        yield 'relative base url' => [static fn() => new PostilioClient(self::API_KEY, baseUrl: 'api.postilio.eu')];
        yield 'base url of another scheme' => [static fn() => new PostilioClient(self::API_KEY, baseUrl: 'ftp://api.postilio.eu')];
        yield 'negative retries' => [static fn() => new PostilioClient(self::API_KEY, maxRetries: -1)];
        yield 'negative retry delay' => [static fn() => new PostilioClient(self::API_KEY, maxRetryDelay: -1.0)];
        yield 'no timeout' => [static fn() => new PostilioClient(self::API_KEY, timeout: 0.0)];
        yield 'no connect timeout' => [static fn() => new PostilioClient(self::API_KEY, connectTimeout: 0.0)];
    }

    /** @param \Closure(): PostilioClient $create */
    #[Test]
    #[DataProvider('invalidSettings')]
    public function constructorInvalidSettingThrows(\Closure $create): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $create();
    }

    #[Test]
    public function constructorInvalidKeyIsNotInTheMessage(): void
    {
        try {
            new PostilioClient('sk_live_secret_value');
            self::fail('No exception.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringNotContainsString('secret_value', $e->getMessage());
        }
    }

    #[Test]
    public function dumpOfTheClientShowsTheAddressButNotTheKey(): void
    {
        $dump = print_r($this->client(), true);

        self::assertStringNotContainsString(self::API_KEY, $dump);
        self::assertStringContainsString('[baseUrl] => https://api.postilio.eu', $dump);
    }

    private function client(string $baseUrl = 'https://api.postilio.eu'): PostilioClient
    {
        $factory = new Psr17Factory();

        return new PostilioClient(self::API_KEY, $this->http, $factory, $factory, baseUrl: $baseUrl);
    }

    private static function welcome(): SendEmailRequest
    {
        return new SendEmailRequest('Acme <no-reply@mail.example.com>', ['delivered@simulator.postilio.eu'], 'Welcome', text: 'Hi.');
    }

    /** @param \Closure(): mixed $call */
    private static function catch(\Closure $call): PostilioException
    {
        try {
            $call();
        } catch (PostilioException $e) {
            return $e;
        }
        self::fail('No exception.');
    }
}
