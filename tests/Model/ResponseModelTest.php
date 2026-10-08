<?php

declare(strict_types=1);

namespace Postilio\Tests\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Postilio\Enum\DmarcStatus;
use Postilio\Enum\DnsRecordStatus;
use Postilio\Enum\DomainStatus;
use Postilio\Enum\EmailStatus;
use Postilio\Enum\SuppressionReason;
use Postilio\Enum\UsageState;
use Postilio\Enum\WebhookDeliveryStatus;
use Postilio\Enum\WebhookEventType;
use Postilio\Enum\WebhookMode;
use Postilio\Model\ApiKeyMonthUsage;
use Postilio\Model\ApiProjectUsage;
use Postilio\Model\ApiUsage;
use Postilio\Model\ApiUsageOrganization;
use Postilio\Model\CreatedWebhookEndpoint;
use Postilio\Model\DmarcCheck;
use Postilio\Model\DnsRecord;
use Postilio\Model\DomainList;
use Postilio\Model\DomainResponse;
use Postilio\Model\EmailDetails;
use Postilio\Model\EmailEvent;
use Postilio\Model\ErrorResponse;
use Postilio\Model\HttpValidationProblemDetails;
use Postilio\Model\RotatedWebhookSecret;
use Postilio\Model\SendEmailResponse;
use Postilio\Model\SuppressionList;
use Postilio\Model\SuppressionResponse;
use Postilio\Model\TestEmailResponse;
use Postilio\Model\WebhookDeliveryList;
use Postilio\Model\WebhookDeliveryResponse;
use Postilio\Model\WebhookEndpointList;
use Postilio\Model\WebhookEndpointResponse;
use Postilio\Model\WebhookLastDelivery;
use Postilio\Tests\Fixtures;

/** Every answer of the API read into its model, from the payloads in the docs (see tests/Fixtures/README.md). */
final class ResponseModelTest extends TestCase
{
    #[Test]
    public function sendEmailResponseReadsTheDocsExample(): void
    {
        self::assertEquals(
            new SendEmailResponse(['01a10ce5-a09a-788b-9243-d7c9c59773d2', '01a10ce5-a09a-7dde-9fde-5676dcbd0094'], []),
            SendEmailResponse::fromArray(Fixtures::json('send-response.json')),
        );
    }

    #[Test]
    public function testEmailResponseReadsTheDocsExample(): void
    {
        self::assertEquals(
            new TestEmailResponse('01a10ce5-a09a-788b-9243-d7c9c59773d2'),
            TestEmailResponse::fromArray(Fixtures::json('test-email-response.json')),
        );
    }

    #[Test]
    public function emailDetailsReadsTheDocsExampleWithItsEvents(): void
    {
        $at = new \DateTimeImmutable('2026-10-05T16:28:57.882+00:00');

        self::assertEquals(
            new EmailDetails(
                id: '01a10ce5-a09a-788b-9243-d7c9c59773d2',
                status: EmailStatus::Bounced,
                from: 'no-reply@mail.example.com',
                to: 'bounced@simulator.postilio.eu',
                subject: 'Welcome to Acme',
                tag: 'welcome',
                acceptedAt: $at,
                test: true,
                events: [
                    new EmailEvent(EmailStatus::Accepted, $at, null, null, null, null, null, null, null),
                    new EmailEvent(EmailStatus::Queued, $at, null, null, null, null, null, null, null),
                    new EmailEvent(
                        type: EmailStatus::Bounced,
                        occurredAt: $at,
                        smtpCode: 550,
                        response: '550 5.1.1 Simulated: the mailbox does not exist',
                        attempt: 1,
                        remoteHost: 'mx.simulator.postilio.eu',
                        enhancedCode: '5.1.1',
                        classification: 'InvalidRecipient',
                        reason: 'recipient_rejected',
                    ),
                ],
                via: 'api',
                sendAt: null,
            ),
            EmailDetails::fromArray(Fixtures::json('email-bounced.json')),
        );
    }

    #[Test]
    public function emailDetailsKeepsAStatusItDoesNotKnowAsAString(): void
    {
        $details = Fixtures::json('email-bounced.json');
        $details['status'] = 'a_later_status';
        $details['sendAt'] = '2026-11-02T09:00:00+01:00';

        $email = EmailDetails::fromArray($details);

        self::assertSame('a_later_status', $email->status);
        self::assertEquals(new \DateTimeImmutable('2026-11-02T08:00:00Z'), $email->sendAt);
    }

    #[Test]
    public function domainResponseReadsTheDocsExampleOfANewDomain(): void
    {
        self::assertEquals(
            new DomainResponse(
                id: '01a1081b-eb5c-7685-ab38-fdd4a4ab10e5',
                name: 'mail.example.com',
                status: DomainStatus::Pending,
                checkedAt: null,
                records: [
                    new DnsRecord('CNAME', 'pst202610._domainkey.mail.example.com', 'pst202610.ft6sub3lbo.dkim.postilio.eu', DnsRecordStatus::Unknown),
                    new DnsRecord('CNAME', 'bounce.mail.example.com', 'rp.postilio.eu', DnsRecordStatus::Unknown),
                ],
                createdAt: new \DateTimeImmutable('2026-10-04T18:10:09.884+00:00'),
                addedBy: null,
                failingSince: null,
                sent30d: 0,
                dmarc: null,
            ),
            DomainResponse::fromArray(Fixtures::json('domain-created.json')),
        );
    }

    #[Test]
    public function domainListReadsAVerifiedDomainWithItsDmarcCheck(): void
    {
        $list = DomainList::fromArray(['data' => [Fixtures::json('domain-verified.json')]]);

        self::assertCount(1, $list->data);
        self::assertSame(DomainStatus::Verified, $list->data[0]->status);
        self::assertSame(DnsRecordStatus::Found, $list->data[0]->records[1]->status);
        self::assertSame('Ada Lovelace', $list->data[0]->addedBy);
        self::assertSame(1250, $list->data[0]->sent30d);
        self::assertEquals(new DmarcCheck(DmarcStatus::Monitoring, 'mail.example.com', ['v=DMARC1; p=none'], ['no_reports']), $list->data[0]->dmarc);
    }

    #[Test]
    public function suppressionListReadsAPageAndItsCursor(): void
    {
        self::assertEquals(
            new SuppressionList(
                [new SuppressionResponse(
                    id: '01a10d2e-7c1b-7f3a-9d42-1b2c3d4e5f60',
                    address: 'former.customer@example.org',
                    reason: SuppressionReason::HardBounce,
                    detail: '550 5.1.1 The email account that you tried to reach does not exist',
                    sourceMessageId: '01a10ce5-a09a-788b-9243-d7c9c59773d2',
                    createdAt: new \DateTimeImmutable('2026-10-05T16:28:58.120+00:00'),
                )],
                '01a10d2e-7c1b-7f3a-9d42-1b2c3d4e5f60',
            ),
            SuppressionList::fromArray(Fixtures::json('suppression-list.json')),
        );
    }

    #[Test]
    public function createdWebhookEndpointReadsTheEndpointAndItsSecret(): void
    {
        self::assertEquals(
            new CreatedWebhookEndpoint(
                new WebhookEndpointResponse(
                    id: '01a10f00-1111-7222-8333-444455556666',
                    url: 'https://api.example.com/hooks/postilio',
                    description: null,
                    events: [WebhookEventType::Delivered, WebhookEventType::Bounced, WebhookEventType::Complained],
                    mode: WebhookMode::Live,
                    paused: false,
                    pauseReason: null,
                    pausedAt: null,
                    failingSince: null,
                    secretHint: 'x7Qk',
                    previousSecretExpiresAt: null,
                    createdAt: new \DateTimeImmutable('2026-10-06T09:00:00+00:00'),
                    lastDelivery: null,
                ),
                'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw',
            ),
            CreatedWebhookEndpoint::fromArray(Fixtures::json('created-webhook-endpoint.json')),
        );
    }

    #[Test]
    public function webhookEndpointListReadsAPausedEndpointWithItsLastDelivery(): void
    {
        self::assertEquals(
            new WebhookEndpointList([new WebhookEndpointResponse(
                id: '01a10f00-1111-7222-8333-444455556666',
                url: 'https://api.example.com/hooks/postilio',
                description: 'Order service',
                events: [WebhookEventType::Delivered, WebhookEventType::Bounced, WebhookEventType::Complained, 'a_later_event'],
                mode: WebhookMode::Test,
                paused: true,
                pauseReason: 'Every attempt failed for 3 days.',
                pausedAt: new \DateTimeImmutable('2026-10-09T09:00:00+00:00'),
                failingSince: new \DateTimeImmutable('2026-10-06T09:00:00+00:00'),
                secretHint: 'x7Qk',
                previousSecretExpiresAt: new \DateTimeImmutable('2026-10-07T09:00:00+00:00'),
                createdAt: new \DateTimeImmutable('2026-10-06T09:00:00+00:00'),
                lastDelivery: new WebhookLastDelivery(new \DateTimeImmutable('2026-10-09T08:59:30+00:00'), 503, WebhookDeliveryStatus::Pending),
            )]),
            WebhookEndpointList::fromArray(Fixtures::json('webhook-endpoint-list.json')),
        );
    }

    #[Test]
    public function rotatedWebhookSecretReadsTheNewSecret(): void
    {
        self::assertEquals(
            new RotatedWebhookSecret('whsec_c2VjcmV0LW9mLWEtcm90YXRlZC1lbmRwb2ludA==', new \DateTimeImmutable('2026-10-07T09:00:00+00:00')),
            RotatedWebhookSecret::fromArray(Fixtures::json('rotated-webhook-secret.json')),
        );
    }

    #[Test]
    public function webhookDeliveryListReadsAFailedDelivery(): void
    {
        self::assertEquals(
            new WebhookDeliveryList(
                [new WebhookDeliveryResponse(
                    id: '01a10f10-aaaa-7bbb-8ccc-ddddeeeeffff',
                    eventId: '01a10f10-1234-7567-89ab-cdef01234567',
                    type: 'email.delivered.v1',
                    emailId: '01a10ce5-a09a-788b-9243-d7c9c59773d2',
                    to: 'ada.lovelace@example.com',
                    status: WebhookDeliveryStatus::Failed,
                    attempts: 20,
                    lastAttemptAt: new \DateTimeImmutable('2026-10-09T08:59:30+00:00'),
                    lastStatusCode: null,
                    lastError: 'timeout',
                    lastDurationMs: 10000,
                    responseSnippet: null,
                    nextAttemptAt: null,
                    createdAt: new \DateTimeImmutable('2026-10-06T09:00:01+00:00'),
                )],
                null,
            ),
            WebhookDeliveryList::fromArray(Fixtures::json('webhook-delivery-list.json')),
        );
    }

    #[Test]
    public function apiUsageReadsTheDocsExample(): void
    {
        self::assertEquals(
            new ApiUsage(
                month: '2026-10',
                final: false,
                resetsAt: new \DateTimeImmutable('2026-11-01T00:00:00+00:00'),
                organization: new ApiUsageOrganization('growth', UsageState::Warning),
                project: new ApiProjectUsage('0199b3c0-6f3a-7d2e-9a41-2c8e5d7f1a20', 12040, 12101, 61, 11950, 88, 2),
                apiKey: new ApiKeyMonthUsage('0199b3c4-2b71-7c05-8e6d-5a9f3c1e7b42', 8020, 7981, 39, 7915, 64, 2),
            ),
            ApiUsage::fromArray(Fixtures::json('usage.json')),
        );
    }

    #[Test]
    public function apiUsageOrganizationOfAnotherMonthHasNoState(): void
    {
        self::assertEquals(new ApiUsageOrganization('growth', null), ApiUsageOrganization::fromArray(['plan' => 'growth', 'state' => null]));
    }

    #[Test]
    public function errorResponseReadsTheCodeAndTheMessage(): void
    {
        self::assertEquals(
            new ErrorResponse('message_too_large', 'A message may be at most 10485760 bytes; this one is 10485761.'),
            ErrorResponse::fromArray(Fixtures::json('error-message-too-large.json')),
        );
    }

    #[Test]
    public function httpValidationProblemDetailsReadsTheProblemsPerField(): void
    {
        self::assertEquals(
            new HttpValidationProblemDetails(
                type: 'https://tools.ietf.org/html/rfc9110#section-15.5.1',
                title: 'One or more validation errors occurred.',
                status: 400,
                detail: null,
                instance: null,
                errors: ['to' => ['Between 1 and 50 recipients are required.'], 'body' => ['Either text or html is required.']],
                traceId: '00-4d6a1c9b71484cc59ac852cda603b93c-28de36d1c118bc08-00',
            ),
            HttpValidationProblemDetails::fromArray(Fixtures::json('problem-details.json')),
        );
    }

    #[Test]
    public function fromArrayMissingARequiredFieldThrows(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('id');

        TestEmailResponse::fromArray([]);
    }

    #[Test]
    public function fromArrayFieldOfTheWrongTypeThrows(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('sent30d');

        DomainResponse::fromArray(['sent30d' => '0'] + Fixtures::json('domain-created.json'));
    }

    #[Test]
    public function fromArrayDateThatIsNotADateThrows(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('resetsAt');

        ApiUsage::fromArray(['resetsAt' => 'next month'] + Fixtures::json('usage.json'));
    }
}
