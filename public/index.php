<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
pageStart('داشبورد کلی','index.php');
try {
 $devices=(int)queryValue('SELECT COUNT(*) FROM devices');$active=(int)queryValue('SELECT COUNT(*) FROM devices WHERE is_active=1');$connections=(int)queryValue('SELECT COUNT(*) FROM connection_logs WHERE occurred_at>=CURDATE()');$web=(int)queryValue('SELECT COUNT(*) FROM web_logs WHERE occurred_at>=CURDATE()');
 echo '<div class="grid">';statCard('کل سیستم‌های شناخته‌شده',$devices);statCard('سیستم‌های فعال',$active);statCard('اتصالات امروز',$connections);statCard('درخواست‌های وب امروز',$web);echo '</div>';
 [$from,$to,$fd,$td]=dateFilters(); rangeForm($fd,$td);
 $liveTop=queryRows('SELECT d.hostname,c.ip_address,c.consumption_kb,c.sampled_at FROM (SELECT t.device_id,t.sampled_at,(COALESCE(t.download_delta_kb,0)+COALESCE(t.upload_delta_kb,0)) consumption_kb FROM traffic_samples t INNER JOIN (SELECT device_id,MAX(sampled_at) sampled_at FROM traffic_samples GROUP BY device_id) latest ON latest.device_id=t.device_id AND latest.sampled_at=t.sampled_at) c INNER JOIN devices d ON d.id=c.device_id ORDER BY c.consumption_kb DESC LIMIT 10');
 $rangeTop=queryRows('SELECT c.source_ip,MAX(d.hostname) hostname,SUM(COALESCE(c.bytes_total,0)) total_bytes FROM connection_logs c LEFT JOIN devices d ON d.id=c.device_id WHERE c.occurred_at BETWEEN ? AND ? GROUP BY c.source_ip ORDER BY total_bytes DESC LIMIT 10',[$from,$to]);
 echo '<div class="columns"><section class="panel"><h2>۱۰ مصرف‌کننده برتر در لحظه (آخرین نمونه ثبت‌شده)</h2><table><thead><tr><th>سیستم</th><th>IP</th><th>مصرف در آخرین نمونه</th><th>زمان نمونه</th></tr></thead><tbody>';
 foreach($liveTop as $r) echo '<tr><td>'.e($r['hostname']?:'نامشخص').'</td><td>'.e($r['ip_address']).'</td><td>'.e(number_format((float)$r['consumption_kb'],2).' KB').'</td><td>'.e($r['sampled_at']).'</td></tr>';
 if(!$liveTop) echo '<tr><td colspan="4">هنوز نمونه ترافیکی ثبت نشده است.</td></tr>'; echo '</tbody></table></section><section class="panel"><h2>۱۰ مصرف‌کننده برتر در بازه انتخابی</h2><table><thead><tr><th>سیستم</th><th>IP</th><th>مصرف کل</th></tr></thead><tbody>';
 foreach($rangeTop as $r) echo '<tr><td>'.e($r['hostname']?:'نامشخص').'</td><td>'.e($r['source_ip']).'</td><td>'.e(formatBytes($r['total_bytes'])).'</td></tr>';
 if(!$rangeTop) echo '<tr><td colspan="3">در این بازه داده‌ای ثبت نشده است.</td></tr>'; echo '</tbody></table></section></div>';
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
