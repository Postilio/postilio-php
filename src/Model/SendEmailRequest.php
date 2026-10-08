<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Internal\Json;

/**
 * An email to send: one message per recipient. The API checks every field and answers a ValidationException (400) or
 * UnprocessableException (422) for one it refuses; see the sending guide for the rules.
 */
final class SendEmailRequest
{
    /**
     * @param list<string>               $to          1 to 50 bare addresses, without display names.
     * @param list<EmailAttachment>|null $attachments
     * @param list<string>|null          $cc          Only with exactly one `to`.
     * @param list<string>|null          $bcc         Only with exactly one `to`; in no header.
     * @param array<string, string>|null $headers     Up to 10: `List-Unsubscribe`, `List-Unsubscribe-Post`,
     *                                                `In-Reply-To`, `References` and `X-` headers.
     */
    public function __construct(
        /** An address on a verified domain of the key's project, optionally with a name: `Acme <no-reply@mail.example.com>`. */
        public readonly string $from,
        public readonly array $to,
        public readonly string $subject,
        public readonly ?string $text = null,
        public readonly ?string $html = null,
        /** Up to 64 letters, digits, `-` or `_`. */
        public readonly ?string $tag = null,
        public readonly ?string $replyTo = null,
        public readonly ?array $attachments = null,
        public readonly ?array $cc = null,
        public readonly ?array $bcc = null,
        public readonly ?array $headers = null,
        /**
         * When to send it, a minute to 30 days ahead: a date (written with its offset) or an ISO 8601 string with `Z` or
         * an offset. Until then the message is `scheduled` and can be canceled.
         */
        public readonly \DateTimeInterface|string|null $sendAt = null,
    ) {}

    /**
     * The request as the API reads it, without the fields that are not set.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Json::fields([
            'from' => $this->from,
            'to' => $this->to,
            'subject' => $this->subject,
            'text' => $this->text,
            'html' => $this->html,
            'tag' => $this->tag,
            'replyTo' => $this->replyTo,
            'attachments' => $this->attachments === null ? null : array_map(static fn(EmailAttachment $a): array => $a->toArray(), $this->attachments),
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            // An empty map would be written as a JSON list; the API wants an object, and no headers is the same as none.
            'headers' => $this->headers === [] ? null : $this->headers,
            'sendAt' => $this->sendAt,
        ]);
    }
}
