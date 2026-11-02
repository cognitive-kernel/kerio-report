<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/collector.php';

try {
    global $config;
    $client = new KerioApiClient($config['kerio'] ?? []);
    $output = [
        'success' => true,
        'tested_at' => date(DATE_ATOM),
        'connection' => $client->getLogTail('connection', 5),
        'http' => $client->getLogTail('http', 5),
    ];
    echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[Kerio Log Test] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
