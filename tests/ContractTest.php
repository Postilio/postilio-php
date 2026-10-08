<?php

declare(strict_types=1);

namespace Postilio\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Postilio\Enum\EmailStatus;
use Postilio\Enum\ErrorCode;
use Postilio\Exception\AuthenticationException;
use Postilio\Exception\ConflictException;
use Postilio\Model\SendEmailRequest;
use Postilio\PostilioClient;

/**
 * Runs against a real Postilio with a test key, so nothing is delivered. Skipped unless POSTILIO_CONTRACT_BASE_URL,
 * POSTILIO_CONTRACT_API_KEY (`pk_test_`, scopes emails:send and emails:read) and POSTILIO_CONTRACT_FROM (an address on a
 * verified domain of the key's project) are set. See CONTRIBUTING.md.
 */
#[Group('contract')]
final class ContractTest extends TestCase
{
    private const DELIVERED = 'delivered@simulator.postilio.eu';

    #[Test]
    public function serverServesTheSpecThisClientWasBuiltFrom(): void
    {
        self::client();
        $live = file_get_contents(self::env('POSTILIO_CONTRACT_BASE_URL') . '/openapi/v1.json');
        $copy = file_get_contents(__DIR__ . '/../spec/openapi-v1.json');
        self::assertIsString($live);
        self::assertIsString($copy);
        $live = json_decode($live, true, flags: \JSON_THROW_ON_ERROR);
        $copy = json_decode($copy, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($live);
        self::assertIsArray($copy);

        self::assertEquals($copy['paths'] ?? null, $live['paths'] ?? null, "The server's /v1 operations differ from spec/openapi-v1.json.");
        self::assertEquals($copy['components'] ?? null, $live['components'] ?? null, "The server's /v1 schemas differ from spec/openapi-v1.json.");
    }

    #[Test]
    public function sendEmailSameIdempotencyKeyIsAcceptedOnceAndFoundWithItsEvents(): void
    {
        $client = self::client();
        $key = 'postilio-php-contract-' . bin2hex(random_bytes(8));
        $request = new SendEmailRequest(self::env('POSTILIO_CONTRACT_FROM'), [self::DELIVERED], 'Contract test', text: "Sent by the PHP SDK's contract tests.", tag: 'sdk-contract');

        $first = $client->sendEmail($request, $key);
        $repeat = $client->sendEmail($request, $key);
        self::assertCount(1, $first->ids);
        $email = $client->getEmail($first->ids[0]);

        self::assertSame($first->ids, $repeat->ids);
        self::assertTrue($email->test);
        self::assertSame('sdk-contract', $email->tag);
        self::assertSame(EmailStatus::Delivered, $email->status);
        self::assertContains(EmailStatus::Delivered, array_map(static fn($e) => $e->type, $email->events));
    }

    #[Test]
    public function sendEmailSameIdempotencyKeyOtherBodyThrowsConflict(): void
    {
        $client = self::client();
        $key = 'postilio-php-contract-' . bin2hex(random_bytes(8));
        $client->sendEmail(new SendEmailRequest(self::env('POSTILIO_CONTRACT_FROM'), [self::DELIVERED], 'One', text: 'One.'), $key);

        try {
            $client->sendEmail(new SendEmailRequest(self::env('POSTILIO_CONTRACT_FROM'), [self::DELIVERED], 'Two', text: 'Two.'), $key);
            self::fail('No exception.');
        } catch (ConflictException $e) {
            self::assertSame(ErrorCode::IdempotencyKeyReusedWithDifferentRequest, $e->error());
        }
    }

    #[Test]
    public function sendEmailTestKeyWithSendAtIsSimulatedAtOnceSoCancelingConflicts(): void
    {
        $client = self::client();
        $sent = $client->sendEmail(new SendEmailRequest(
            self::env('POSTILIO_CONTRACT_FROM'),
            [self::DELIVERED],
            'Scheduled',
            text: 'Simulated at once.',
            sendAt: new \DateTimeImmutable('+1 hour'),
        ));

        try {
            $client->cancelEmail($sent->ids[0]);
            self::fail('No exception.');
        } catch (ConflictException $e) {
            self::assertSame(ErrorCode::EmailNotScheduled, $e->error());
        }
    }

    #[Test]
    public function getEmailWithAnUnknownKeyThrowsAuthentication(): void
    {
        self::client();
        $client = new PostilioClient('pk_test_' . str_repeat('0', 32), baseUrl: self::env('POSTILIO_CONTRACT_BASE_URL'));

        try {
            $client->getEmail('01a10ce5-a09a-788b-9243-d7c9c59773d2');
            self::fail('No exception.');
        } catch (AuthenticationException $e) {
            self::assertSame(ErrorCode::InvalidApiKey, $e->error());
        }
    }

    private static function client(): PostilioClient
    {
        foreach (['POSTILIO_CONTRACT_BASE_URL', 'POSTILIO_CONTRACT_API_KEY', 'POSTILIO_CONTRACT_FROM'] as $name) {
            if (getenv($name) === false) {
                self::markTestSkipped('Set POSTILIO_CONTRACT_BASE_URL, POSTILIO_CONTRACT_API_KEY and POSTILIO_CONTRACT_FROM to run the contract tests.');
            }
        }
        // A live key would deliver real mail.
        self::assertStringStartsWith('pk_test_', self::env('POSTILIO_CONTRACT_API_KEY'));

        return new PostilioClient(self::env('POSTILIO_CONTRACT_API_KEY'), baseUrl: self::env('POSTILIO_CONTRACT_BASE_URL'));
    }

    private static function env(string $name): string
    {
        $value = getenv($name);
        self::assertIsString($value, "{$name} is not set.");

        return $value;
    }
}
