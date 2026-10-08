<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Internal\Json;

/** A file attached to an email. */
final class EmailAttachment
{
    public function __construct(
        public readonly string $fileName,
        /** A MIME type such as `application/pdf`, optionally with parameters. */
        public readonly string $contentType,
        /** The file's bytes, as file_get_contents() returns them: the client encodes them as base64. */
        public readonly string $content,
        /** Makes the attachment inline: the HTML refers to it as `cid:` plus this value. */
        public readonly ?string $contentId = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return Json::fields([
            'fileName' => $this->fileName,
            'contentType' => $this->contentType,
            'content' => base64_encode($this->content),
            'contentId' => $this->contentId,
        ]);
    }
}
