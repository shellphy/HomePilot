<?php

$serverName = explode(' ', getenv('SERVER_NAME') ?: ':80')[0];
$usesTls = $serverName !== ':80';
$transport = $usesTls ? 'tls' : 'tcp';
$port = $usesTls ? 443 : 80;
$host = $usesTls ? $serverName : 'localhost';
$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
]);

$socket = @stream_socket_client(
    "{$transport}://127.0.0.1:{$port}",
    $errorCode,
    $errorMessage,
    5,
    STREAM_CLIENT_CONNECT,
    $context,
);

if ($socket === false) {
    exit(1);
}

stream_set_timeout($socket, 5);
fwrite($socket, "GET /up HTTP/1.1\r\nHost: {$host}\r\nConnection: close\r\n\r\n");
$statusLine = fgets($socket);
fclose($socket);

exit(is_string($statusLine) && str_contains($statusLine, ' 200 ') ? 0 : 1);
