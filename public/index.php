<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
pageStart('داشبورد کلی','index.php');
try {
 $devices=(int)queryValue('SELECT COUNT(*) FROM devices');$active=(int)queryValue('SELECT COUNT(*) FROM devices WHERE is_active=1');$connections=(int)queryValue('SELECT COUNT(*) FROM connection_logs WHERE occurred_at>=CURDATE()');$web=(int)queryValue('SELECT COUNT(*) FROM web_logs WHERE occurred_at>=CURDATE()');
 echo '<div class="grid">';statCard('کل سیستم‌های شناخته‌شده',$devices);statCard('سیستم‌های فعال',$active);statCard('اتصالات امروز',$connections);statCard('درخواست‌های وب امروز',$web);echo '</div>';
 $traffic=queryRows('SELECT DATE(occurred_at) day,SUM(COALESCE(bytes_in,0))/1024 down_kb,SUM(COALESCE(bytes_out,0))/1024 up_kb FROM connection_logs WHERE occurred_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) GROUP BY DATE(occurred_at) ORDER BY day');
 chart('overviewTraffic','bar',array_column($traffic,'day'),[['label'=>'دریافت (KB)','data'=>array_map('floatval',array_column($traffic,'down_kb'))],['label'=>'ارسال (KB)','data'=>array_map('floatval',array_column($traffic,'up_kb'))]]);
 $cols=['occurred_at'=>'زمان','source_ip'=>'IP مبدأ','destination_host'=>'مقصد','service_name'=>'سرویس'];
 $sort=['occurred_at'=>'occurred_at','source_ip'=>'source_ip','destination_host'=>'destination_host','service_name'=>'service_name'];
 $p=queryPage('connection_logs','',[],array_keys($cols),$sort,'occurred_at');
 echo '<section class="panel"><h2>اتصالات ثبت‌شده</h2>';tableControls($p,$cols);echo '<table>';tableHeader($cols,$p);echo '<tbody>';
 foreach($p['rows'] as $r)echo '<tr><td>'.e($r['occurred_at']).'</td><td>'.e($r['source_ip']).'</td><td>'.e($r['destination_host']).'</td><td>'.e($r['service_name']).'</td></tr>';
 echo '</tbody></table>';tablePagination($p);echo '</section>';
} catch(Throwable $ex){echo '<section class="panel">خطا در خواندن گزارش‌ها: '.e($ex->getMessage()).'</section>';}
pageEnd();
