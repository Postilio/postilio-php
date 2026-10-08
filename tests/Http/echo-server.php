<?php

// The router of `php -S` for CurlTransportTest: answers what the test asks for in the path.
declare(strict_types=1);

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url(\is_string($uri) ? $uri : '/', \PHP_URL_PATH);
if (preg_match('#^/status/(\d{3})/#', (string) $path, $m) === 1) {
    http_response_code((int) $m[1]);
    header('Retry-After: 5');
    echo '{"error":"from_the_server"}';

    return true;
}
if (str_starts_with((string) $path, '/slow')) {
    sleep(3);
}
if (str_starts_with((string) $path, '/redirect')) {
    header('Location: /echo', true, 302);

    return true;
}
header('Content-Type: application/json');
header('X-Answer: yes');
echo json_encode([
    'method' => $_SERVER['REQUEST_METHOD'] ?? null,
    'uri' => $_SERVER['REQUEST_URI'] ?? null,
    'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
    'contentType' => $_SERVER['CONTENT_TYPE'] ?? null,
    'idempotencyKey' => $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null,
    'expect' => $_SERVER['HTTP_EXPECT'] ?? null,
    'body' => file_get_contents('php://input'),
]);

return true;
