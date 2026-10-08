<?php

declare(strict_types=1);

namespace Postilio\Webhook;

/**
 * Verifies the signature of a Postilio webhook delivery (the Standard Webhooks scheme): HMAC-SHA256 over
 * `{webhook-id}.{webhook-timestamp}.{body}`, compared in constant time, with a timestamp within the tolerance either
 * way so a captured request cannot be replayed later. Create one per signing secret and reuse it.
 */
final class WebhookVerifier
{
    private const SECRET_PREFIX = 'whsec_';
    private const SIGNATURE_VERSION = 'v1';

    private readonly string $key;

    /**
     * @param string $secret           The endpoint's signing secret, `whsec_…`, as Postilio showed it.
     * @param int    $toleranceSeconds How far `webhook-timestamp` may be from now, either way: five minutes by default.
     *
     * @throws \InvalidArgumentException The secret is not base64 after the `whsec_` prefix, or the tolerance is negative.
     */
    public function __construct(#[\SensitiveParameter] string $secret, private readonly int $toleranceSeconds = 300)
    {
        $encoded = str_starts_with($secret, self::SECRET_PREFIX) ? substr($secret, \strlen(self::SECRET_PREFIX)) : $secret;
        $key = base64_decode($encoded, true);
        if ($key === false || $key === '') {
            throw new \InvalidArgumentException('A webhook secret is whsec_ followed by base64.');
        }
        if ($toleranceSeconds < 0) {
            throw new \InvalidArgumentException('The tolerance must be zero or more seconds.');
        }
        $this->key = $key;
    }

    /**
     * True when one of the signatures matches and the timestamp is within the tolerance. Pass the raw body as received,
     * not re-encoded JSON: verify before you parse it.
     *
     * @param string|null $id              The `webhook-id` header.
     * @param string|null $timestamp       The `webhook-timestamp` header.
     * @param string|null $signatureHeader The `webhook-signature` header; during a secret rotation it holds two signatures.
     * @param string      $body            The raw request body.
     * @param int|null    $now             The current Unix time; the system clock by default.
     */
    public function verify(?string $id, ?string $timestamp, ?string $signatureHeader, string $body, ?int $now = null): bool
    {
        // At most 18 digits, so the number fits an integer and a forged one far ahead is refused rather than overflowing.
        if ($signatureHeader === null || $signatureHeader === '' || $timestamp === null
            || preg_match('/^\d{1,18}$/D', $timestamp) !== 1
            || abs(($now ?? time()) - (int) $timestamp) > $this->toleranceSeconds) {
            return false;
        }
        $expected = hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", $this->key, true);
        foreach (explode(' ', $signatureHeader) as $signature) {
            $parts = explode(',', $signature, 2);
            if (\count($parts) === 2 && $parts[0] === self::SIGNATURE_VERSION
                && ($given = base64_decode($parts[1], true)) !== false
                && hash_equals($expected, $given)) {
                return true;
            }
        }

        return false;
    }
}
