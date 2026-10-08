<?php

declare(strict_types=1);

namespace Postilio\Exception;

/** 403: the key lacks the scope of this call, may not be used from this address, or its project or organization is suspended. */
final class PermissionException extends PostilioException {}
