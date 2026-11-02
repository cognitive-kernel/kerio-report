<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/collector.php';

try {
    $result = collectActiveHosts();
    echo json_encode([
        'success' => true,
        'collected_at' => date(DATE_ATOM),
        'active_hosts' => $result['received'],
        'stored_devices' => $result['stored'],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[Kerio Collector] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
