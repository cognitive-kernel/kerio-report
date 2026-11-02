<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
pageStart('داشبورد کلی','index.php');
try {
    $devices=(int)queryValue('SELECT COUNT(*) FROM devices');
    $active=(int)queryValue('SELECT COUNT(*) FROM devices WHERE is_active=1');
    $connections=(int)queryValue('SELECT COUNT(*) FROM connection_logs WHERE occurred_at >= CURDATE()');
    $web=(int)queryValue('SELECT COUNT(*) FROM web_logs WHERE occurred_at >= CURDATE()');
    echo '<div class="grid">'; statCard('کل سیستم‌های شناخته‌شده',$devices); statCard('سیستم‌های فعال ثبت‌شده',$active); statCard('اتصالات امروز',$connections); statCard('درخواست‌های وب امروز',$web); echo '</div>';
    $traffic=queryRows('SELECT DATE(sampled_at) day, SUM(download_delta_kb) down_kb, SUM(upload_delta_kb) up_kb FROM traffic_samples WHERE sampled_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(sampled_at) ORDER BY day');
    chart('overviewTraffic','bar',array_column($traffic,'day'),[['label'=>'دریافت (KB)','data'=>array_map('floatval',array_column($traffic,'down_kb'))],['label'=>'ارسال (KB)','data'=>array_map('floatval',array_column($traffic,'up_kb'))]]);
    echo '<div class="columns"><section class="panel"><h2>آخرین سیستم‌های مشاهده‌شده</h2><table><tr><th>نام سیستم</th><th>IP</th><th>MAC</th><th>آخرین مشاهده</th></tr>';
    foreach(queryRows('SELECT hostname,ip_address,mac_address,last_seen FROM devices ORDER BY last_seen DESC LIMIT 10') as $r) echo '<tr><td>'.e($r['hostname']?:'نامشخص').'</td><td>'.e($r['ip_address']).'</td><td>'.e($r['mac_address']).'</td><td>'.e($r['last_seen']).'</td></tr>';
    echo '</table></section><section class="panel"><h2>آخرین اتصالات</h2><table><tr><th>زمان</th><th>مبدأ</th><th>مقصد</th><th>سرویس</th></tr>';
    foreach(queryRows('SELECT occurred_at,source_ip,destination_host,service_name FROM connection_logs ORDER BY occurred_at DESC LIMIT 10') as $r) echo '<tr><td>'.e($r['occurred_at']).'</td><td>'.e($r['source_ip']).'</td><td>'.e($r['destination_host']).'</td><td>'.e($r['service_name']).'</td></tr>';
    echo '</table></section></div>';
} catch(Throwable $ex) { echo '<section class="panel">خطا در خواندن گزارش‌ها: '.e($ex->getMessage()).'</section>'; }
pageEnd();
