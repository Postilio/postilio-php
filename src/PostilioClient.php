<?php

declare(strict_types=1);

namespace Postilio;

use Http\Discovery\Psr17FactoryDiscovery;
use Postilio\Enum\SuppressionReason;
use Postilio\Enum\WebhookDeliveryStatus;
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
use Postilio\Internal\CurlTransport;
use Postilio\Internal\HttpRequest;
use Postilio\Internal\HttpResponse;
use Postilio\Internal\Json;
use Postilio\Internal\Psr18Transport;
use Postilio\Internal\Transport;
use Postilio\Model\ApiUsage;
use Postilio\Model\CreateDomainRequest;
use Postilio\Model\CreatedWebhookEndpoint;
use Postilio\Model\CreateSuppressionRequest;
use Postilio\Model\CreateWebhookEndpointRequest;
use Postilio\Model\DomainList;
use Postilio\Model\DomainResponse;
use Postilio\Model\EmailDetails;
use Postilio\Model\ErrorResponse;
use Postilio\Model\HttpValidationProblemDetails;
use Postilio\Model\RemoveSuppressionRequest;
use Postilio\Model\RotatedWebhookSecret;
use Postilio\Model\SendEmailRequest;
use Postilio\Model\SendEmailResponse;
use Postilio\Model\SuppressionList;
use Postilio\Model\SuppressionResponse;
use Postilio\Model\TestEmailRequest;
use Postilio\Model\TestEmailResponse;
use Postilio\Model\UpdateWebhookEndpointRequest;
use Postilio\Model\WebhookDeliveryList;
use Postilio\Model\WebhookDeliveryResponse;
use Postilio\Model\WebhookEndpointList;
use Postilio\Model\WebhookEndpointResponse;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * The Postilio API (`/v1`). Create one and reuse it. Every method throws a PostilioException, or a subclass per status,
 * for an error answer, and a TransportException when no answer came.
 */
final class PostilioClient
{
    /** The version of this SDK, sent in the User-Agent. */
    public const VERSION = '0.1.0-alpha.1';

    private const RETRYABLE_STATUSES = [408, 429, 500, 502, 503, 504];
    private const PLAN_LIMITS = ['plan_monthly_limit_reached', 'plan_daily_limit_reached', 'plan_rate_limit_reached'];
    private const FIRST_BACKOFF = 0.5;

    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly Transport $transport;
    private readonly \Closure $sleep;

    /**
     * @param string                       $apiKey         `pk_live_…` or `pk_test_…`. Keep it in a secret store; the client
     *                                                     never logs it and keeps it out of exceptions and dumps.
     * @param ClientInterface|null         $httpClient     A PSR-18 client, such as the one your framework configures. Without
     *                                                     one, the client sends with curl.
     * @param RequestFactoryInterface|null $requestFactory With $httpClient: PSR-17 factories, found by php-http/discovery
     *                                                     when you leave them out and it is installed.
     * @param int                          $maxRetries     How often a failed call is tried again: 0, off, by default. Only a
     *                                                     GET or a send with an Idempotency-Key is ever tried again, after a
     *                                                     408, 429, 500, 502, 503, 504 or a network failure.
     * @param float                        $maxRetryDelay  The longest wait before a retry, in seconds. An answer that asks to
     *                                                     wait longer (Retry-After) throws at once instead.
     * @param float                        $timeout        Seconds for a whole request with curl; a PSR-18 client has its own.
     * @param float                        $connectTimeout Seconds to connect with curl.
     * @param (\Closure(float): void)|null $sleep          @internal Waits between retries; for tests.
     *
     * @throws \InvalidArgumentException A setting is not valid.
     */
    public function __construct(
        #[\SensitiveParameter]
        string $apiKey,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        string $baseUrl = 'https://api.postilio.eu',
        private readonly int $maxRetries = 0,
        private readonly float $maxRetryDelay = 30.0,
        float $timeout = 30.0,
        float $connectTimeout = 10.0,
        ?\Closure $sleep = null,
    ) {
        if (!str_starts_with($apiKey, 'pk_')) {
            throw new \InvalidArgumentException('The API key must be a Postilio API key: pk_live_… or pk_test_….');
        }
        $scheme = parse_url($baseUrl, \PHP_URL_SCHEME);
        if (($scheme !== 'https' && $scheme !== 'http') || parse_url($baseUrl, \PHP_URL_HOST) === null) {
            throw new \InvalidArgumentException('The base URL must be an absolute http(s) URL.');
        }
        if ($maxRetries < 0 || $maxRetryDelay < 0) {
            throw new \InvalidArgumentException('Retries and the retry delay must be zero or more.');
        }
        if ($timeout <= 0 || $connectTimeout <= 0) {
            throw new \InvalidArgumentException('The timeouts must be more than zero seconds.');
        }
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->transport = $httpClient === null
            ? new CurlTransport($timeout, $connectTimeout)
            : new Psr18Transport($httpClient, $requestFactory ?? self::discoverRequestFactory(), $streamFactory ?? self::discoverStreamFactory());
        $this->sleep = $sleep ?? static function (float $seconds): void {
            usleep((int) round($seconds * 1_000_000));
        };
    }

    /**
     * Sends an email: one message per recipient (`to`, `cc` and `bcc`). Needs the `emails:send` scope; with a test key
     * nothing is delivered. Every send carries an Idempotency-Key, so sending again after a lost answer, also by the
     * client's own retries, never sends twice.
     *
     * @param string|null $idempotencyKey Your own key of 1 to 256 characters, such as `order-1042-receipt`, to be safe
     *                                    across restarts and queues too; one is made per call when you leave it out.
     */
    public function sendEmail(SendEmailRequest $request, ?string $idempotencyKey = null): SendEmailResponse
    {
        if ($idempotencyKey === '') {
            throw new \InvalidArgumentException('An Idempotency-Key is 1 to 256 characters.');
        }

        return $this->call('POST', '/v1/emails', $request->toArray(), SendEmailResponse::fromArray(...), $idempotencyKey ?? self::uuid());
    }

    /**
     * Sends one test email to an address of your own, to check a domain end to end; Postilio's sample message unless the
     * request has its own. Needs `emails:send`; 10 a day per project.
     */
    public function sendTestEmail(TestEmailRequest $request): TestEmailResponse
    {
        return $this->call('POST', '/v1/emails/test', $request->toArray(), read: TestEmailResponse::fromArray(...));
    }

    /** Gets a message and its timeline of events. Needs `emails:read`; a key finds only messages of its own mode. */
    public function getEmail(string $id): EmailDetails
    {
        return $this->call('GET', '/v1/emails/' . rawurlencode($id), read: EmailDetails::fromArray(...));
    }

    /**
     * Cancels a message sent with `sendAt` while it waits: it gets the status `canceled`. Needs `emails:send`. A message
     * on its way, or one that was never scheduled, throws a ConflictException (`email_not_scheduled`).
     */
    public function cancelEmail(string $id): void
    {
        $this->call('DELETE', '/v1/emails/' . rawurlencode($id));
    }

    /** Adds a sending domain; the answer lists the DNS records to create. Needs `domains:manage`. */
    public function createDomain(CreateDomainRequest $request): DomainResponse
    {
        return $this->call('POST', '/v1/domains', $request->toArray(), read: DomainResponse::fromArray(...));
    }

    /** Lists the project's sending domains. Needs `domains:manage`. */
    public function listDomains(): DomainList
    {
        return $this->call('GET', '/v1/domains', read: DomainList::fromArray(...));
    }

    /** Gets a sending domain with its records and DMARC check. Needs `domains:manage`. */
    public function getDomain(string $id): DomainResponse
    {
        return $this->call('GET', '/v1/domains/' . rawurlencode($id), read: DomainResponse::fromArray(...));
    }

    /** Removes a sending domain. Needs `domains:manage`. */
    public function deleteDomain(string $id): void
    {
        $this->call('DELETE', '/v1/domains/' . rawurlencode($id));
    }

    /**
     * Checks a domain's DNS records and DMARC record now, once a minute at most, and answers the domain with its new
     * status. Needs `domains:manage`.
     */
    public function checkDomain(string $id): DomainResponse
    {
        return $this->call('POST', '/v1/domains/' . rawurlencode($id) . '/check', read: DomainResponse::fromArray(...));
    }

    /**
     * Lists suppressed addresses, newest first. Needs `suppressions:manage`.
     *
     * @param string|null $q      Part of an address.
     * @param string|null $before The `next` of the previous page.
     * @param int|null    $limit  1 to 100; 50 by default.
     */
    public function listSuppressions(?string $q = null, SuppressionReason|string|null $reason = null, ?string $before = null, ?int $limit = null): SuppressionList
    {
        $query = self::query(['q' => $q, 'reason' => $reason, 'before' => $before, 'limit' => $limit]);

        return $this->call('GET', '/v1/suppressions' . $query, read: SuppressionList::fromArray(...));
    }

    /** Puts an address on the suppression list, with the reason `manual`. Needs `suppressions:manage`. */
    public function createSuppression(CreateSuppressionRequest $request): SuppressionResponse
    {
        return $this->call('POST', '/v1/suppressions', $request->toArray(), read: SuppressionResponse::fromArray(...));
    }

    /** Takes an address off the suppression list; a complaint only with a reason. Needs `suppressions:manage`. */
    public function deleteSuppression(string $id, ?RemoveSuppressionRequest $request = null): void
    {
        $this->call('DELETE', '/v1/suppressions/' . rawurlencode($id), $request?->toArray());
    }

    /** Lists the project's webhook endpoints. Needs `webhooks:manage`. */
    public function listWebhookEndpoints(): WebhookEndpointList
    {
        return $this->call('GET', '/v1/webhooks', read: WebhookEndpointList::fromArray(...));
    }

    /** Adds a webhook endpoint. The answer holds its signing secret, shown this once. Needs `webhooks:manage`. */
    public function createWebhookEndpoint(CreateWebhookEndpointRequest $request): CreatedWebhookEndpoint
    {
        return $this->call('POST', '/v1/webhooks', $request->toArray(), read: CreatedWebhookEndpoint::fromArray(...));
    }

    /** Gets a webhook endpoint. Needs `webhooks:manage`. */
    public function getWebhookEndpoint(string $id): WebhookEndpointResponse
    {
        return $this->call('GET', '/v1/webhooks/' . rawurlencode($id), read: WebhookEndpointResponse::fromArray(...));
    }

    /** Changes a webhook endpoint; what the request leaves null stays as it is. Needs `webhooks:manage`. */
    public function updateWebhookEndpoint(string $id, UpdateWebhookEndpointRequest $request): WebhookEndpointResponse
    {
        return $this->call('PATCH', '/v1/webhooks/' . rawurlencode($id), $request->toArray(), read: WebhookEndpointResponse::fromArray(...));
    }

    /** Removes a webhook endpoint. Needs `webhooks:manage`. */
    public function deleteWebhookEndpoint(string $id): void
    {
        $this->call('DELETE', '/v1/webhooks/' . rawurlencode($id));
    }

    /** Rotates the signing secret; the old one keeps signing for 24 hours. Needs `webhooks:manage`. */
    public function rotateWebhookSecret(string $id): RotatedWebhookSecret
    {
        return $this->call('POST', '/v1/webhooks/' . rawurlencode($id) . '/secret', read: RotatedWebhookSecret::fromArray(...));
    }

    /** Sends a `webhook.test.v1` event once, also to a paused endpoint. Needs `webhooks:manage`. */
    public function sendWebhookTestEvent(string $id): WebhookDeliveryResponse
    {
        return $this->call('POST', '/v1/webhooks/' . rawurlencode($id) . '/test', read: WebhookDeliveryResponse::fromArray(...));
    }

    /**
     * Lists an endpoint's deliveries, newest first. Needs `webhooks:manage`.
     *
     * @param string|null $before The `next` of the previous page.
     * @param int|null    $limit  1 to 100; 50 by default.
     */
    public function listWebhookDeliveries(string $id, WebhookDeliveryStatus|string|null $status = null, ?string $before = null, ?int $limit = null): WebhookDeliveryList
    {
        $query = self::query(['status' => $status, 'before' => $before, 'limit' => $limit]);

        return $this->call('GET', '/v1/webhooks/' . rawurlencode($id) . '/deliveries' . $query, read: WebhookDeliveryList::fromArray(...));
    }

    /** Tries a delivery once more right away, with the same id and payload. Needs `webhooks:manage`. */
    public function retryWebhookDelivery(string $id, string $deliveryId): void
    {
        $this->call('POST', '/v1/webhooks/' . rawurlencode($id) . '/deliveries/' . rawurlencode($deliveryId) . '/retry');
    }

    /**
     * Gets the project's usage in a UTC month and where its organization stands against its plan. Needs `usage:read`
     * (live keys only).
     *
     * @param string|null $month `yyyy-MM`; the current month by default.
     */
    public function getUsage(?string $month = null): ApiUsage
    {
        return $this->call('GET', '/v1/usage' . self::query(['month' => $month]), read: ApiUsage::fromArray(...));
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['baseUrl' => $this->baseUrl, 'maxRetries' => $this->maxRetries, 'maxRetryDelay' => $this->maxRetryDelay];
    }

    /**
     * Sends a call, retrying it when that is allowed and the answer says it may help, and reads the answer.
     *
     * @template T
     *
     * @param non-empty-string                       $method
     * @param non-empty-string                       $path
     * @param array<string, mixed>|null              $body
     * @param (callable(array<mixed>): T)|null       $read Reads the answer's JSON into its model.
     *
     * @return ($read is null ? null : T)
     */
    private function call(string $method, string $path, ?array $body = null, ?callable $read = null, ?string $idempotencyKey = null): mixed
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => 'postilio-php/' . self::VERSION . ' php/' . \PHP_VERSION,
        ];
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
        }
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }
        $request = new HttpRequest($method, $this->baseUrl . $path, $headers, $body === null ? null : Json::encode($body));
        // Sending again cannot do anything twice: a read, or a send the server recognises by its Idempotency-Key.
        $replayable = $method === 'GET' || ($method === 'POST' && $idempotencyKey !== null);
        $call = $method . ' ' . explode('?', $path, 2)[0];

        for ($attempt = 0; ; $attempt++) {
            try {
                $response = $this->transport->send($request);
            } catch (TransportException $e) {
                if ($replayable && $e->networkError && $attempt < $this->maxRetries) {
                    ($this->sleep)($this->backoff($attempt));
                    continue;
                }
                throw new TransportException("{$call} failed: {$e->getMessage()}", $e->networkError, $e->getPrevious() ?? $e);
            }
            if ($response->status >= 200 && $response->status < 300) {
                return $read === null ? null : self::read($call, $response, $read);
            }
            $wait = $this->retryAfter($response) ?? $this->backoff($attempt);
            if ($replayable && \in_array($response->status, self::RETRYABLE_STATUSES, true)
                && $attempt < $this->maxRetries && $wait <= $this->maxRetryDelay) {
                ($this->sleep)($wait);
                continue;
            }
            throw self::error($call, $response, $this->retryAfter($response));
        }
    }

    /**
     * @template T
     *
     * @param callable(array<mixed>): T $read
     *
     * @return T
     */
    private static function read(string $call, HttpResponse $response, callable $read): mixed
    {
        try {
            return $read(Json::decode($response->body));
        } catch (\UnexpectedValueException $e) {
            throw new PostilioException(
                "{$call} answered {$response->status} with a body that is not the expected JSON: {$e->getMessage()}",
                $response->status,
                previous: $e,
            );
        }
    }

    private static function error(string $call, HttpResponse $response, ?float $retryAfter): PostilioException
    {
        $status = $response->status;
        try {
            $body = Json::decode($response->body);
        } catch (\UnexpectedValueException) {
            $body = [];
        }
        try {
            $error = ErrorResponse::fromArray($body);
        } catch (\UnexpectedValueException) {
            $error = null;
        }
        try {
            $problem = HttpValidationProblemDetails::fromArray($body);
        } catch (\UnexpectedValueException) {
            $problem = null;
        }
        $code = $error?->error;
        $seconds = $retryAfter === null ? null : (int) ceil($retryAfter);
        $message = "{$call} answered {$status}" . ($code === null ? '' : " ({$code})") . ($error?->message === null ? '' : ': ' . rtrim($error->message, '.'));

        if ($status === 400) {
            $problems = [];
            foreach ($problem->errors ?? [] as $field => $texts) {
                $problems[] = $field . ': ' . implode(' ', $texts);
            }

            $text = $message . ($problems === [] ? '' : ': ' . rtrim(implode(' ', $problems), '.'));

            return new ValidationException($text . '.', $problem->errors ?? [], $problem?->traceId);
        }
        $arguments = [$message . '.', $status, $code, $error?->message, $problem?->traceId, $seconds];

        return match (true) {
            $status === 401 => new AuthenticationException(...$arguments),
            $status === 403 => new PermissionException(...$arguments),
            $status === 404 => new NotFoundException(...$arguments),
            $status === 409 => new ConflictException(...$arguments),
            $status === 413 => new PayloadTooLargeException(...$arguments),
            $status === 422 => new UnprocessableException(...$arguments),
            $status === 429 && \in_array($code, self::PLAN_LIMITS, true) => new PlanLimitException(...$arguments),
            $status === 429 => new RateLimitException(...$arguments),
            $status >= 500 => new ServerException(...$arguments),
            default => new PostilioException(...$arguments),
        };
    }

    /** The wait the answer asks for, in seconds: Retry-After as a number of seconds or as an HTTP date. */
    private function retryAfter(HttpResponse $response): ?float
    {
        $value = $response->header('Retry-After');
        if ($value === null) {
            return null;
        }
        if (preg_match('/^\d+$/D', $value) === 1) {
            return (float) $value;
        }
        $date = \DateTimeImmutable::createFromFormat('D, d M Y H:i:s \G\M\T', $value, new \DateTimeZone('UTC'));

        return $date === false ? null : (float) max(0, $date->getTimestamp() - time());
    }

    // 0.5 s, 1 s, 2 s, … up to the maximum, with ±20% spread so clients that failed together do not retry together.
    private function backoff(int $attempt): float
    {
        return min(self::FIRST_BACKOFF * 2 ** $attempt * (0.8 + random_int(0, 400) / 1000), $this->maxRetryDelay);
    }

    /** @param array<string, string|int|\BackedEnum|null> $parameters */
    private static function query(array $parameters): string
    {
        $present = [];
        foreach ($parameters as $name => $value) {
            if ($value !== null) {
                $present[$name] = $value instanceof \BackedEnum ? $value->value : $value;
            }
        }

        return $present === [] ? '' : '?' . http_build_query($present, '', '&', \PHP_QUERY_RFC3986);
    }

    /** A random (version 4) UUID. */
    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = \chr(\ord($bytes[6]) & 0x0F | 0x40);
        $bytes[8] = \chr(\ord($bytes[8]) & 0x3F | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private static function discoverRequestFactory(): RequestFactoryInterface
    {
        self::requireDiscovery();

        return Psr17FactoryDiscovery::findRequestFactory();
    }

    private static function discoverStreamFactory(): StreamFactoryInterface
    {
        self::requireDiscovery();

        return Psr17FactoryDiscovery::findStreamFactory();
    }

    private static function requireDiscovery(): void
    {
        if (!class_exists(Psr17FactoryDiscovery::class)) {
            throw new \InvalidArgumentException('Pass the PSR-17 request and stream factories with the PSR-18 client, or install php-http/discovery.');
        }
    }
}
