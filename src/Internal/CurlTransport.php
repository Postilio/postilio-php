<?php

declare(strict_types=1);

namespace Postilio\Internal;

use Postilio\Exception\TransportException;

/**
 * The transport when the caller passes no PSR-18 client: a curl handle per request, no redirects, HTTP(S) only.
 *
 * @internal
 */
final class CurlTransport implements Transport
{
    // Failures where the request may not have reached the server, or its answer got lost: worth another try.
    private const NETWORK_ERRORS = [
        \CURLE_COULDNT_RESOLVE_HOST,
        \CURLE_COULDNT_CONNECT,
        \CURLE_OPERATION_TIMEDOUT,
        \CURLE_SSL_CONNECT_ERROR,
        \CURLE_GOT_NOTHING,
        \CURLE_SEND_ERROR,
        \CURLE_RECV_ERROR,
    ];

    public function __construct(private readonly float $timeout, private readonly float $connectTimeout) {}

    public function send(HttpRequest $request): HttpResponse
    {
        // No "Expect: 100-continue" round trip before a larger body.
        $headers = ['Expect:'];
        foreach ($request->headers as $name => $value) {
            $headers[] = "{$name}: {$value}";
        }
        $answerHeaders = [];
        $handle = curl_init();
        curl_setopt($handle, \CURLOPT_URL, $request->url);
        curl_setopt($handle, \CURLOPT_CUSTOMREQUEST, $request->method);
        curl_setopt($handle, \CURLOPT_HTTPHEADER, $headers);
        curl_setopt($handle, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, \CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($handle, \CURLOPT_PROTOCOLS, \CURLPROTO_HTTP | \CURLPROTO_HTTPS);
        curl_setopt($handle, \CURLOPT_NOSIGNAL, true);
        curl_setopt($handle, \CURLOPT_TIMEOUT_MS, (int) ceil($this->timeout * 1000));
        curl_setopt($handle, \CURLOPT_CONNECTTIMEOUT_MS, (int) ceil($this->connectTimeout * 1000));
        curl_setopt($handle, \CURLOPT_HEADERFUNCTION, static function ($_, string $line) use (&$answerHeaders): int {
            if (str_starts_with($line, 'HTTP/')) {
                $answerHeaders = []; // a new answer, after a 100 Continue
            } elseif (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $answerHeaders[strtolower(trim($name))] = trim($value);
            }

            return \strlen($line);
        });
        if ($request->body !== null) {
            curl_setopt($handle, \CURLOPT_POSTFIELDS, $request->body);
        }
        $body = curl_exec($handle);
        if (!\is_string($body)) {
            throw new TransportException(curl_error($handle), \in_array(curl_errno($handle), self::NETWORK_ERRORS, true));
        }

        return new HttpResponse(curl_getinfo($handle, \CURLINFO_RESPONSE_CODE), $answerHeaders, $body);
    }
}
