<?php

declare(strict_types=1);

namespace Postilio\Exception;

/** 409: conflicts with what is there, such as a reused Idempotency-Key or a limit of the plan on domains or endpoints. */
final class ConflictException extends PostilioException {}
