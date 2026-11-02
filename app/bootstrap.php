<?php
declare(strict_types=1);

$configFile = dirname(__DIR__) . '/config.local.php';
if (!is_file($configFile)) {
    http_response_code(503);
    exit('Configuration missing. Copy config.example.php to config.local.php and configure database access.');
}
$config = require $configFile;
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Tehran');

function db(): PDO {
    static $pdo = null;
    global $config;
    if ($pdo instanceof PDO) return $pdo;
    $d = $config['database'];
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $d['host'], (int)$d['port'], $d['name'], $d['charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $d['username'], $d['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
function e(mixed $value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function queryRows(string $sql, array $params = []): array {
    $stmt = db()->prepare($sql); $stmt->execute($params); return $stmt->fetchAll();
}
function queryValue(string $sql, array $params = []): mixed {
    $stmt = db()->prepare($sql); $stmt->execute($params); return $stmt->fetchColumn();
}
function dateFilters(): array {
    $from = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
    $to = $_GET['to'] ?? date('Y-m-d');
    foreach ([$from, $to] as $date) {
        $d = DateTime::createFromFormat('Y-m-d', (string)$date);
        if (!$d || $d->format('Y-m-d') !== $date) throw new InvalidArgumentException('Invalid date range');
    }
    if ($from > $to) throw new InvalidArgumentException('Start date must not exceed end date');
    return [$from . ' 00:00:00', $to . ' 23:59:59', $from, $to];
}
function pageStart(string $title, string $active): void {
    global $config;
    $name = $config['app']['name'] ?? 'Kerio Report';
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' | '.e($name).'</title><link rel="stylesheet" href="assets/app.css"><script src="https://cdn.jsdelivr.net/npm/chart.js"></script></head><body><aside class="sidebar"><div class="brand">Kerio <b>Report</b></div><nav>';
    $links = ['index.php'=>'داشبورد کلی','range.php'=>'گزارش بازه زمانی','systems.php'=>'گزارش سیستم‌ها','realtime.php'=>'وضعیت لحظه‌ای','connections.php'=>'اتصالات شبکه','websites.php'=>'وب‌سایت‌ها','system-websites.php'=>'لینک‌های هر سیستم'];
    foreach ($links as $href=>$label) echo '<a class="'.($active===$href?'active':'').'" href="'.$href.'">'.e($label).'</a>';
    echo '</nav><div class="side-note">گزارش‌گیری از داده‌های ذخیره‌شده در Kerio Report</div></aside><main class="main"><header><div><small>سامانه مانیتورینگ شبکه</small><h1>'.e($title).'</h1></div><span class="live-dot">ذخیره‌سازی محلی</span></header>';
}
function pageEnd(): void { echo '</main></body></html>'; }
function statCard(string $label, mixed $value, string $hint=''): void {
    echo '<section class="stat"><span>'.e($label).'</span><strong>'.e(number_format((float)$value, 0)).'</strong><small>'.e($hint).'</small></section>';
}
function chart(string $id, string $type, array $labels, array $datasets): void {
    echo '<section class="panel chart-panel"><canvas id="'.e($id).'" height="105"></canvas></section><script>new Chart(document.getElementById('.json_encode($id).'),{type:'.json_encode($type).',data:{labels:'.json_encode($labels, JSON_UNESCAPED_UNICODE).',datasets:'.json_encode($datasets, JSON_UNESCAPED_UNICODE).'},options:{responsive:true,maintainAspectRatio:true,plugins:{legend:{position:"bottom"}},scales:{y:{beginAtZero:true}}}});</script>';
}
function rangeForm(string $from, string $to): void {
    echo '<form class="filters" method="get"><label>از تاریخ <input type="date" name="from" value="'.e($from).'"></label><label>تا تاریخ <input type="date" name="to" value="'.e($to).'"></label><button>اعمال بازه</button></form>';
}
