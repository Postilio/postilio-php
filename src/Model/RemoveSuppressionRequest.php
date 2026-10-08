<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Internal\Json;

/** Why an address comes off the suppression list. */
final class RemoveSuppressionRequest
{
    public function __construct(
        /** Required, 10 to 500 characters, when the entry is a complaint; kept with the removal. */
        public readonly ?string $reason = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return Json::fields(['reason' => $this->reason]);
    }
}
