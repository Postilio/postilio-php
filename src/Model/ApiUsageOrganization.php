<?php

declare(strict_types=1);

namespace Postilio\Model;

use Postilio\Enum\UsageState;
use Postilio\Internal\Json;

/** Where the organization stands against its plan, without its numbers. */
final class ApiUsageOrganization
{
    public function __construct(
        /** The code of the plan the organization has now. */
        public readonly ?string $plan,
        /** Null for a month other than the current one. */
        public readonly UsageState|string|null $state,
    ) {}

    /**
     * Reads the model from a decoded JSON answer.
     *
     * @param array<mixed> $data
     *
     * @throws \UnexpectedValueException A field is missing or of another type.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Json::nullableString($data, 'plan'),
            Json::nullableEnum($data, 'state', UsageState::class),
        );
    }
}
