<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
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
         ORDER BY consumption_kb DESC
         LIMIT 10'
    );

    echo json_encode([
        'success' => true,
        'finished_at' => date(DATE_ATOM),
        'live_top' => $liveTop,
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'failed_at' => date(DATE_ATOM),
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
