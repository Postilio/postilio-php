<?php

declare(strict_types=1);

namespace Postilio\Internal;

use Postilio\Exception\TransportException;

/** @internal */
interface Transport
{
    /** @throws TransportException No answer came. */
    public function send(HttpRequest $request): HttpResponse;
}
