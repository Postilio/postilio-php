<?php

declare(strict_types=1);

namespace Postilio\Exception;

/** 413: the message, its size times its recipients, or the request body is too large; errorMessage names the limit. */
final class PayloadTooLargeException extends PostilioException {}
