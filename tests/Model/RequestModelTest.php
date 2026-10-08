<?php

declare(strict_types=1);

namespace Postilio\Tests\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Postilio\Enum\WebhookEventType;
use Postilio\Enum\WebhookMode;
use Postilio\Model\CreateDomainRequest;
use Postilio\Model\CreateSuppressionRequest;
use Postilio\Model\CreateWebhookEndpointRequest;
use Postilio\Model\EmailAttachment;
use Postilio\Model\RemoveSuppressionRequest;
use Postilio\Model\SendEmailRequest;
use Postilio\Model\TestEmailRequest;
use Postilio\Model\UpdateWebhookEndpointRequest;
use Postilio\Tests\Fixtures;

/** Every request model written as the JSON the docs send (see tests/Fixtures/README.md). */
final class RequestModelTest extends TestCase
{
    #[Test]
    public function sendEmailRequestWritesTheDocsExample(): void
    {
        $request = new SendEmailRequest(
            from: 'Acme <no-reply@mail.example.com>',
            to: ['ada.lovelace@example.com'],
            subject: 'Your sign-in code',
            text: 'Your code is 482913.',
            html: '<p>Your code is <b>482913</b>.</p>',
            tag: 'sign-in',
            replyTo: 'support@example.com',
        );

        self::assertJsonStringEqualsJsonString(Fixtures::text('send-request.json'), self::json($request->toArray()));
    }

    #[Test]
    public function sendEmailRequestWritesCcAndBcc(): void
    {
        $request = new SendEmailRequest(
            from: 'Acme Support <support@mail.example.com>',
            to: ['ada.lovelace@example.com'],
            subject: 'Your ticket 4711',
            text: 'Solved: the export works again.',
            cc: ['account-manager@example.com'],
            bcc: ['archive@example.com'],
        );

        self::assertJsonStringEqualsJsonString(Fixtures::text('send-cc-bcc-request.json'), self::json($request->toArray()));
    }

    #[Test]
    public function sendEmailRequestWritesTheHeadersAsAnObject(): void
    {
        $request = new SendEmailRequest(
            from: 'Acme <news@mail.example.com>',
            to: ['delivered@simulator.postilio.eu'],
            subject: 'What is new in October',
            text: 'Three new features. Unsubscribe: https://example.com/unsubscribe/3f9a1c7e',
            tag: 'newsletter',
            headers: [
                'List-Unsubscribe' => '<https://example.com/unsubscribe/3f9a1c7e>, <mailto:unsubscribe@example.com?subject=3f9a1c7e>',
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
                'X-Campaign-Id' => 'october-2026',
            ],
        );

        self::assertJsonStringEqualsJsonString(Fixtures::text('send-headers-request.json'), self::json($request->toArray()));
    }

    #[Test]
    public function sendEmailRequestWritesSendAtWithItsOffsetAndAttachmentsAsBase64(): void
    {
        $request = new SendEmailRequest(
            from: 'Acme <no-reply@mail.example.com>',
            to: ['ada.lovelace@example.com'],
            subject: 'Your invoice',
            html: '<p><img src="cid:logo"> See the attachment.</p>',
            attachments: [
                new EmailAttachment('invoice.pdf', 'application/pdf', "\x01\x02\x03"),
                new EmailAttachment('logo.png', 'image/png', 'png', contentId: 'logo'),
            ],
            sendAt: new \DateTimeImmutable('2026-11-02T09:00:00.250+01:00'),
        );

        self::assertJsonStringEqualsJsonString(<<<'JSON'
            {
              "from": "Acme <no-reply@mail.example.com>",
              "to": ["ada.lovelace@example.com"],
              "subject": "Your invoice",
              "html": "<p><img src=\"cid:logo\"> See the attachment.</p>",
              "attachments": [
                { "fileName": "invoice.pdf", "contentType": "application/pdf", "content": "AQID" },
                { "fileName": "logo.png", "contentType": "image/png", "content": "cG5n", "contentId": "logo" }
              ],
              "sendAt": "2026-11-02T09:00:00+01:00"
            }
            JSON, self::json($request->toArray()));
    }

    #[Test]
    public function sendEmailRequestPassesASendAtStringAsItIs(): void
    {
        $request = new SendEmailRequest('a@mail.example.com', ['b@example.com'], 'Hi', text: 'Hi.', sendAt: '2026-11-02T08:00:00Z');

        self::assertSame('2026-11-02T08:00:00Z', $request->toArray()['sendAt']);
    }

    #[Test]
    public function sendEmailRequestLeavesOutEmptyHeaders(): void
    {
        $request = new SendEmailRequest('a@mail.example.com', ['b@example.com'], 'Hi', text: 'Hi.', headers: []);

        self::assertArrayNotHasKey('headers', $request->toArray());
    }

    #[Test]
    public function testEmailRequestWritesTheDocsExample(): void
    {
        self::assertJsonStringEqualsJsonString(
            Fixtures::text('test-email-request.json'),
            self::json((new TestEmailRequest('no-reply@mail.example.com', 'ada@example.org'))->toArray()),
        );
    }

    #[Test]
    public function testEmailRequestWritesItsOwnMessage(): void
    {
        self::assertSame(
            ['from' => 'a@mail.example.com', 'to' => 'b@example.org', 'subject' => 'Hi', 'text' => 'Hi.', 'html' => '<p>Hi.</p>'],
            (new TestEmailRequest('a@mail.example.com', 'b@example.org', 'Hi', 'Hi.', '<p>Hi.</p>'))->toArray(),
        );
    }

    #[Test]
    public function createDomainRequestWritesTheDocsExample(): void
    {
        self::assertJsonStringEqualsJsonString(
            Fixtures::text('create-domain-request.json'),
            self::json((new CreateDomainRequest('mail.example.com'))->toArray()),
        );
    }

    #[Test]
    public function createSuppressionRequestWritesTheDocsExample(): void
    {
        self::assertJsonStringEqualsJsonString(
            Fixtures::text('create-suppression-request.json'),
            self::json((new CreateSuppressionRequest('former.customer@example.org'))->toArray()),
        );
    }

    #[Test]
    public function removeSuppressionRequestWritesTheDocsExample(): void
    {
        self::assertJsonStringEqualsJsonString(
            Fixtures::text('remove-suppression-request.json'),
            self::json((new RemoveSuppressionRequest('The customer subscribed again on 5 October.'))->toArray()),
        );
    }

    #[Test]
    public function createWebhookEndpointRequestWritesTheDocsExample(): void
    {
        $request = new CreateWebhookEndpointRequest(
            'https://api.example.com/hooks/postilio',
            [WebhookEventType::Delivered, WebhookEventType::Bounced, 'complained'],
        );

        self::assertJsonStringEqualsJsonString(Fixtures::text('create-webhook-endpoint-request.json'), self::json($request->toArray()));
    }

    #[Test]
    public function createWebhookEndpointRequestWritesTheModeAndDescription(): void
    {
        $request = new CreateWebhookEndpointRequest('https://api.example.com/hooks', [WebhookEventType::Delivered], 'Orders', WebhookMode::Test);

        self::assertSame(
            ['url' => 'https://api.example.com/hooks', 'events' => ['delivered'], 'description' => 'Orders', 'mode' => 'test'],
            $request->toArray(),
        );
    }

    #[Test]
    public function updateWebhookEndpointRequestWritesOnlyWhatChanges(): void
    {
        self::assertSame('{"paused":false}', self::json((new UpdateWebhookEndpointRequest(paused: false))->toArray()));
        self::assertSame(
            '{"url":"https://api.example.com/v2","events":["bounced"],"description":""}',
            self::json((new UpdateWebhookEndpointRequest('https://api.example.com/v2', [WebhookEventType::Bounced], ''))->toArray()),
        );
    }

    /** @param array<string, mixed> $data */
    private static function json(array $data): string
    {
        return json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }
}
