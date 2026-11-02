<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
pageStart('گزارش بازه زمانی','range.php');
try {
    [$from,$to,$fromDate,$toDate]=dateFilters(); rangeForm($fromDate,$toDate);
    $traffic=queryRows('SELECT DATE(sampled_at) day,SUM(download_delta_kb) down_kb,SUM(upload_delta_kb) up_kb FROM traffic_samples WHERE sampled_at BETWEEN ? AND ? GROUP BY DATE(sampled_at) ORDER BY day',[$from,$to]);
    $connections=(int)queryValue('SELECT COUNT(*) FROM connection_logs WHERE occurred_at BETWEEN ? AND ?',[$from,$to]);
    $requests=(int)queryValue('SELECT COUNT(*) FROM web_logs WHERE occurred_at BETWEEN ? AND ?',[$from,$to]);
    echo '<div class="grid">'; statCard('کل اتصالات',$connections); statCard('درخواست‌های وب',$requests); statCard('دریافت',formatBytes(array_sum(array_map('floatval',array_column($traffic,'down_kb')))*1024)); statCard('ارسال',formatBytes(array_sum(array_map('floatval',array_column($traffic,'up_kb')))*1024)); echo '</div>';
    chart('rangeTraffic','line',array_column($traffic,'day'),[['label'=>'دریافت (KB)','data'=>array_map('floatval',array_column($traffic,'down_kb'))],['label'=>'ارسال (KB)','data'=>array_map('floatval',array_column($traffic,'up_kb'))]]);
    echo '<section class="panel"><h2>پراکندگی اتصالات بر اساس سیستم</h2><table><tr><th>IP</th><th>تعداد اتصال</th><th>حجم مصرفی</th></tr>';
    foreach(queryRows('SELECT source_ip,COUNT(*) total,SUM(COALESCE(bytes_total,0)) bytes FROM connection_logs WHERE occurred_at BETWEEN ? AND ? GROUP BY source_ip ORDER BY total DESC LIMIT 100',[$from,$to]) as $r) echo '<tr><td>'.e($r['source_ip']).'</td><td>'.e($r['total']).'</td><td>'.e(formatBytes($r['bytes'])).'</td></tr>';
    echo '</table></section>';
} catch(Throwable $ex) { echo '<section class="panel">'.e($ex->getMessage()).'</section>'; }
pageEnd();
