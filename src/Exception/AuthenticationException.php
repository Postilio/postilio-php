<?php

declare(strict_types=1);

namespace Postilio\Exception;

/** 401: the API key is missing, unknown or revoked. */
final class AuthenticationException extends PostilioException {}
