<?php

$serverName = explode(' ', getenv('SERVER_NAME') ?: ':80')[0];
$usesTls = $serverName !== ':80';
$transport = $usesTls ? 'tls' : 'tcp';
$port = $usesTls ? 443 : 80;
$host = $usesTls ? $serverName : 'localhost';
$context = stream_context_create($usesTls ? [
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'peer_name' => $host,
        'SNI_enabled' => true,
    ],
] : []);

$socket = @stream_socket_client(
    "{$transport}://127.0.0.1:{$port}",
    $errorCode,
    $errorMessage,
    5,
    STREAM_CLIENT_CONNECT,
    $context,
);

if ($socket === false) {
    fwrite(STDERR, "健康检查连接失败：{$errorMessage} ({$errorCode})\n");

    exit(1);
}

stream_set_timeout($socket, 5);
fwrite($socket, "GET /up HTTP/1.1\r\nHost: {$host}\r\nConnection: close\r\n\r\n");
$statusLine = fgets($socket);
fclose($socket);

if (! is_string($statusLine) || ! str_contains($statusLine, ' 200 ')) {
    fwrite(STDERR, '健康检查返回异常：'.($statusLine ?: '无响应')."\n");

    exit(1);
}

exit(0);
