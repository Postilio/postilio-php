<?php

declare(strict_types=1);

namespace Postilio\Exception;

/** 422: valid, but it cannot be done now, such as a sender domain that is not verified. */
final class UnprocessableException extends PostilioException {}
