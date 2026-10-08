<?php

declare(strict_types=1);

namespace Postilio\Tests\Webhook;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Postilio\Webhook\WebhookVerifier;

final class WebhookVerifierTest extends TestCase
{
    // The test vector of the Standard Webhooks specification, as in the .NET SDK's tests.
    private const SECRET = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';
    private const ID = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
    private const TIMESTAMP = 1614265330;
    private const BODY = '{"test": 2432232314}';
    private const SIGNATURE = 'v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE=';
    private const OTHER_SECRET = 'whsec_c2VjcmV0LW9mLWEtcm90YXRlZC1lbmRwb2ludA==';

    /** @return iterable<string, array{string, string, string, string, string, int, bool}> */
    public static function vectors(): iterable
    {
        yield 'the vector' => [self::SECRET, self::ID, '1614265330', self::BODY, self::SIGNATURE, 0, true];
        yield 'secret without prefix' => ['MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw', self::ID, '1614265330', self::BODY, self::SIGNATURE, 0, true];
        yield 'other body' => [self::SECRET, self::ID, '1614265330', '{"test": 2432232315}', self::SIGNATURE, 0, false];
        yield 'other id' => [self::SECRET, 'msg_other', '1614265330', self::BODY, self::SIGNATURE, 0, false];
        yield 'other timestamp' => [self::SECRET, self::ID, '1614265331', self::BODY, self::SIGNATURE, 0, false];
        yield 'other secret' => [self::OTHER_SECRET, self::ID, '1614265330', self::BODY, self::SIGNATURE, 0, false];
        yield 'five minutes later' => [self::SECRET, self::ID, '1614265330', self::BODY, self::SIGNATURE, 300, true];
        yield 'five minutes earlier' => [self::SECRET, self::ID, '1614265330', self::BODY, self::SIGNATURE, -300, true];
        yield 'over five minutes later' => [self::SECRET, self::ID, '1614265330', self::BODY, self::SIGNATURE, 301, false];
        yield 'over five minutes earlier' => [self::SECRET, self::ID, '1614265330', self::BODY, self::SIGNATURE, -301, false];
        yield 'timestamp not a number' => [self::SECRET, self::ID, 'not-a-number', self::BODY, self::SIGNATURE, 0, false];
        yield 'timestamp far ahead' => [self::SECRET, self::ID, '99999999999999', self::BODY, self::SIGNATURE, 0, false];
        yield 'timestamp beyond an integer' => [self::SECRET, self::ID, '99999999999999999999999', self::BODY, self::SIGNATURE, 0, false];
        yield 'negative timestamp' => [self::SECRET, self::ID, '-1614265330', self::BODY, self::SIGNATURE, 0, false];
        yield 'timestamp with a sign' => [self::SECRET, self::ID, '+1614265330', self::BODY, self::SIGNATURE, 0, false];
        yield 'timestamp with spaces' => [self::SECRET, self::ID, ' 1614265330', self::BODY, self::SIGNATURE, 0, false];
        yield 'other version' => [self::SECRET, self::ID, '1614265330', self::BODY, 'v2,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE=', 0, false];
        yield 'no version' => [self::SECRET, self::ID, '1614265330', self::BODY, 'g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE=', 0, false];
        yield 'not base64' => [self::SECRET, self::ID, '1614265330', self::BODY, 'v1,not-base64!', 0, false];
        yield 'one byte off' => [self::SECRET, self::ID, '1614265330', self::BODY, 'v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1A==', 0, false];
        yield 'too long' => [self::SECRET, self::ID, '1614265330', self::BODY, 'v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE=AAAA', 0, false];
        yield 'rotation, second matches' => [self::SECRET, self::ID, '1614265330', self::BODY, 'v1,AAAA v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE=', 0, true];
        yield 'rotation, first matches' => [self::SECRET, self::ID, '1614265330', self::BODY, 'v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE= v1,AAAA', 0, true];
        yield 'rotation, neither matches' => [self::SECRET, self::ID, '1614265330', self::BODY, 'v1,AAAA9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE= v1,g0hM', 0, false];
        yield 'empty signature' => [self::SECRET, self::ID, '1614265330', self::BODY, '', 0, false];
        yield 'empty id' => [self::SECRET, '', '1614265330', self::BODY, self::SIGNATURE, 0, false];
    }

    #[Test]
    #[DataProvider('vectors')]
    public function verifyAcceptsOnlyAFreshMatchingSignature(
        string $secret,
        string $id,
        string $timestamp,
        string $body,
        string $signature,
        int $secondsLater,
        bool $valid,
    ): void {
        $verifier = new WebhookVerifier($secret);

        self::assertSame($valid, $verifier->verify($id, $timestamp, $signature, $body, self::TIMESTAMP + $secondsLater));
    }

    #[Test]
    public function verifyMissingHeadersIsFalse(): void
    {
        self::assertFalse((new WebhookVerifier(self::SECRET))->verify(null, null, null, self::BODY, self::TIMESTAMP));
        self::assertFalse((new WebhookVerifier(self::SECRET))->verify(self::ID, '1614265330', null, self::BODY, self::TIMESTAMP));
    }

    #[Test]
    public function verifyNoToleranceAcceptsOnlyTheExactTime(): void
    {
        $verifier = new WebhookVerifier(self::SECRET, toleranceSeconds: 0);

        self::assertTrue($verifier->verify(self::ID, '1614265330', self::SIGNATURE, self::BODY, self::TIMESTAMP));
        self::assertFalse($verifier->verify(self::ID, '1614265330', self::SIGNATURE, self::BODY, self::TIMESTAMP + 1));
    }

    #[Test]
    public function verifyWiderToleranceAcceptsAnOlderTimestamp(): void
    {
        $verifier = new WebhookVerifier(self::SECRET, toleranceSeconds: 600);

        self::assertTrue($verifier->verify(self::ID, '1614265330', self::SIGNATURE, self::BODY, self::TIMESTAMP + 600));
        self::assertFalse($verifier->verify(self::ID, '1614265330', self::SIGNATURE, self::BODY, self::TIMESTAMP + 601));
    }

    #[Test]
    public function verifyWithoutAClockUsesTheCurrentTime(): void
    {
        self::assertFalse((new WebhookVerifier(self::SECRET))->verify(self::ID, '1614265330', self::SIGNATURE, self::BODY));
    }

    /** @return iterable<string, array{string}> */
    public static function badSecrets(): iterable
    {
        yield 'empty' => [''];
        yield 'prefix only' => ['whsec_'];
        yield 'not base64' => ['whsec_not base64'];
    }

    #[Test]
    #[DataProvider('badSecrets')]
    public function constructorSecretThatIsNotBase64Throws(string $secret): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new WebhookVerifier($secret);
    }

    #[Test]
    public function constructorNegativeToleranceThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new WebhookVerifier(self::SECRET, toleranceSeconds: -1);
    }
}
