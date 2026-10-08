<?php

declare(strict_types=1);

namespace Postilio\Exception;

/** 5xx: an error at Postilio, or service_degraded / dns_unavailable; quote traceId when you contact support. */
final class ServerException extends PostilioException {}
