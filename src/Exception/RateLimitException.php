<?php

declare(strict_types=1);

namespace Postilio\Exception;

/** 429: a limit was reached; try again after retryAfter seconds. */
class RateLimitException extends PostilioException {}
