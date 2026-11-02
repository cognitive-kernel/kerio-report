<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/log_synchronizer.php';

try {
    global $config;
    $api = new KerioApiClient($config['kerio'] ?? []);
    $sync = new KerioLogSynchronizer(db(), $api);
    $result = [
        'success' => true,
        'started_at' => date(DATE_ATOM),
        'connection' => $sync->sync('connection', 1000, 20),
        'http' => $sync->sync('http', 1000, 20),
        'finished_at' => date(DATE_ATOM),
    ];
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[Kerio Log Sync] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
