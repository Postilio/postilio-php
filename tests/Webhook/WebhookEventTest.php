<?php

declare(strict_types=1);

namespace Postilio\Tests\Webhook;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Postilio\Enum\EmailEventReason;
use Postilio\Enum\EmailStatus;
use Postilio\Tests\Fixtures;
use Postilio\Webhook\WebhookEvent;
use Postilio\Webhook\WebhookEventData;

final class WebhookEventTest extends TestCase
{
    #[Test]
    public function parseReadsTheDocsExample(): void
    {
        $at = new \DateTimeImmutable('2026-10-03T14:07:45.102+00:00');

        self::assertEquals(
            new WebhookEvent('email.bounced.v1', $at, new WebhookEventData(
                emailId: '0199a7c4-5a1e-7d2b-9c41-6f3e0b8a2d17',
                projectId: '0199a1b2-0000-7000-8000-000000000001',
                to: 'ada.lovelace@example.com',
                tag: 'sign-in',
                test: false,
                event: EmailStatus::Bounced,
                occurredAt: $at,
                attempt: 1,
                smtpCode: 550,
                enhancedCode: '5.1.1',
                classification: 'InvalidRecipient',
                reason: EmailEventReason::RecipientRejected,
                response: '550 5.1.1 The email account that you tried to reach does not exist',
                remoteHost: 'mx.example.com',
            )),
            WebhookEvent::parse(Fixtures::text('webhook-bounced.json')),
        );
    }

    #[Test]
    public function parseReadsATestEventAndIgnoresFieldsAddedLater(): void
    {
        $event = WebhookEvent::parse('{"type":"webhook.test.v1","timestamp":"2026-10-03T14:07:45+00:00","data":'
            . '{"endpointId":"01a10f00-1111-7222-8333-444455556666","projectId":"0199a1b2-0000-7000-8000-000000000001","test":true,"aFieldAddedLater":1}}');

        self::assertSame('webhook.test.v1', $event->type);
        self::assertSame('01a10f00-1111-7222-8333-444455556666', $event->data->endpointId);
        self::assertTrue($event->data->test);
        self::assertNull($event->data->event);
        self::assertNull($event->data->emailId);
    }

    #[Test]
    public function parseReadsTheSendAtOfAScheduledMessage(): void
    {
        $event = WebhookEvent::parse('{"type":"email.scheduled.v1","timestamp":"2026-10-03T14:07:45+00:00","data":'
            . '{"emailId":"0199a7c4-5a1e-7d2b-9c41-6f3e0b8a2d17","test":false,"event":"scheduled","sendAt":"2026-11-02T09:00:00+01:00"}}');

        self::assertSame(EmailStatus::Scheduled, $event->data->event);
        self::assertEquals(new \DateTimeImmutable('2026-11-02T08:00:00Z'), $event->data->sendAt);
    }

    #[Test]
    public function parseBodyThatIsNotAnEventThrows(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        WebhookEvent::parse('[1, 2]');
    }

    #[Test]
    public function parseBodyThatIsNotJsonThrows(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        WebhookEvent::parse('{"type":');
    }
}
