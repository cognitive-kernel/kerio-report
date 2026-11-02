<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
pageStart('اتصالات شبکه','connections.php');
try {
    [$from,$to,$fd,$td]=dateFilters(); rangeForm($fd,$td);
    $rows=queryRows('SELECT occurred_at,source_ip,source_port,destination_host,destination_ip,destination_port,protocol,service_name,duration_seconds,bytes_total FROM connection_logs WHERE occurred_at BETWEEN ? AND ? ORDER BY occurred_at DESC LIMIT 500',[$from,$to]);
    echo '<section class="panel"><h2>جزئیات اتصالات (حداکثر ۵۰۰ رکورد)</h2><table><tr><th>زمان</th><th>IP مبدأ</th><th>پورت</th><th>مقصد</th><th>پروتکل</th><th>سرویس</th><th>مدت (ثانیه)</th><th>حجم (Bytes)</th></tr>';
    foreach($rows as $r) echo '<tr><td>'.e($r['occurred_at']).'</td><td>'.e($r['source_ip']).'</td><td>'.e($r['source_port']).'</td><td>'.e(($r['destination_host']?:$r['destination_ip']).':'.$r['destination_port']).'</td><td>'.e($r['protocol']).'</td><td>'.e($r['service_name']).'</td><td>'.e($r['duration_seconds']).'</td><td>'.e($r['bytes_total']).'</td></tr>';
    echo '</table>'; if(!$rows) echo '<div class="empty">در این بازه اتصالی ثبت نشده است.</div>'; echo '</section>';
} catch(Throwable $ex) { echo '<section class="panel">'.e($ex->getMessage()).'</section>'; }
pageEnd();
