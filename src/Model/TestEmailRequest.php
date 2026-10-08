<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Internal\Json;

/** A test email: without subject, text and html, Postilio's sample message. */
final class TestEmailRequest
{
    public function __construct(
        /** An address on a verified domain of the project. */
        public readonly string $from,
        /** One address: confirmed for test mail in the project, or a member's of its organization. */
        public readonly string $to,
        public readonly ?string $subject = null,
        public readonly ?string $text = null,
        public readonly ?string $html = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return Json::fields(['from' => $this->from, 'to' => $this->to, 'subject' => $this->subject, 'text' => $this->text, 'html' => $this->html]);
    }
}
