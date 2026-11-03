<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/collector.php';
require_once dirname(__DIR__) . '/app/log_synchronizer.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$pdo = null;
$lockAcquired = false;
$lockName = 'kerio_report_active_refresh';

try {
    set_time_limit(180);
    $pdo = db();
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $lock->execute([$lockName]);
    $lockAcquired = (int)$lock->fetchColumn() === 1;

    if (!$lockAcquired) {
        echo json_encode(['success' => true, 'busy' => true, 'finished_at' => date(DATE_ATOM)], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    $collection = collectActiveHosts();
    $api = new KerioApiClient($config['kerio'] ?? []);
    $synchronizer = new KerioLogSynchronizer($pdo, $api);
    $connectionSync = $synchronizer->sync('connection', 250, 1);
    $httpSync = $synchronizer->sync('http', 250, 1);

    $liveTop = queryRows(
        'SELECT d.hostname, d.ip_address, d.mac_address, t.sampled_at,
                (COALESCE(t.download_delta_kb, 0) + COALESCE(t.upload_delta_kb, 0)) AS consumption_kb
         FROM traffic_samples t
         INNER JOIN (
             SELECT device_id, MAX(sampled_at) AS sampled_at
             FROM traffic_samples
             GROUP BY device_id
         ) latest ON latest.device_id = t.device_id AND latest.sampled_at = t.sampled_at
         INNER JOIN devices d ON d.id = t.device_id
         WHERE t.sampled_at >= DATE_SUB(NOW(), INTERVAL 90 SECOND)
         ORDER BY consumption_kb DESC
         LIMIT 10'
    );

    echo json_encode([
        'success' => true,
        'busy' => false,
        'collected' => $collection,
        'connection_sync' => $connectionSync,
        'http_sync' => $httpSync,
        'live_top' => $liveTop,
        'finished_at' => date(DATE_ATOM),
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'failed_at' => date(DATE_ATOM)], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} finally {
    if ($lockAcquired && $pdo instanceof PDO) {
        try {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        } catch (Throwable $ignored) {
            // Closing the database connection also releases the named lock.
        }
    }
}
