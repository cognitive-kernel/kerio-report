<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/log_synchronizer.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$pdo = null;
$lockAcquired = false;

try {
    global $config;
    $pdo = db();

    $lock = $pdo->query("SELECT GET_LOCK('kerio_report_browser_refresh', 0)")->fetchColumn();
    if ((int)$lock !== 1) {
        http_response_code(202);
        echo json_encode([
            'success' => true,
            'status' => 'already_running',
            'message' => 'A refresh cycle is already running.'
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    $lockAcquired = true;

    $startedAt = date(DATE_ATOM);
    $collector = collectActiveHosts();

    $api = new KerioApiClient($config['kerio'] ?? []);
    $sync = new KerioLogSynchronizer($pdo, $api);

    $connection = $sync->sync('connection', 5000, 20);
    $http = $sync->sync('http', 5000, 20);

    echo json_encode([
        'success' => true,
        'started_at' => $startedAt,
        'finished_at' => date(DATE_ATOM),
        'collector' => [
            'active_hosts' => $collector['received'],
            'stored_devices' => $collector['stored'],
        ],
        'connection' => $connection,
        'http' => $http,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'failed_at' => date(DATE_ATOM),
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} finally {
    if ($lockAcquired && $pdo instanceof PDO) {
        try {
            $pdo->query("SELECT RELEASE_LOCK('kerio_report_browser_refresh')");
        } catch (Throwable $ignored) {
        }
    }
}
