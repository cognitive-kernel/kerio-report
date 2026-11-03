<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
pageStart('گزارش سیستم‌ها','systems.php');
try {
 $fromSql='(SELECT d.id,d.hostname,d.ip_address,d.mac_address,d.last_seen,d.is_active,COALESCE(c.connections,0) connections,COALESCE(w.requests,0) requests,COALESCE(t.down_kb,0) down_kb,COALESCE(t.up_kb,0) up_kb,(COALESCE(t.down_kb,0)+COALESCE(t.up_kb,0))*1024 total_bytes FROM devices d LEFT JOIN (SELECT device_id,COUNT(*) connections FROM connection_logs GROUP BY device_id) c ON c.device_id=d.id LEFT JOIN (SELECT device_id,COUNT(*) requests FROM web_logs GROUP BY device_id) w ON w.device_id=d.id LEFT JOIN (SELECT device_id,SUM(download_delta_kb) down_kb,SUM(upload_delta_kb) up_kb FROM traffic_samples GROUP BY device_id) t ON t.device_id=d.id) report';
 $cols=['hostname'=>'سیستم','ip_address'=>'IP','mac_address'=>'MAC','is_active'=>'وضعیت','connections'=>'اتصالات','requests'=>'درخواست وب','down_kb'=>'دریافت (KB)','up_kb'=>'ارسال (KB)','total_bytes'=>'مصرف کل','last_seen'=>'آخرین مشاهده'];
 $sort=['hostname'=>'hostname','ip_address'=>'ip_address','mac_address'=>'mac_address','is_active'=>'is_active','connections'=>'connections','requests'=>'requests','down_kb'=>'down_kb','up_kb'=>'up_kb','total_bytes'=>'total_bytes','last_seen'=>'last_seen'];
 $p=queryPage($fromSql,'',[],array_keys($cols),$sort,'total_bytes');
 echo '<section class="panel"><h2>خلاصه مصرف و فعالیت هر دستگاه</h2>'; tableControls($p,$cols); echo '<table>';tableHeader($cols,$p);echo '<tbody>';
 foreach($p['rows'] as $r) echo '<tr><td>'.e($r['hostname']?:'نامشخص').'</td><td>'.e($r['ip_address']).'</td><td>'.e(formatMacAddress($r['mac_address'])).'</td><td>'.($r['is_active']?'فعال':'غیرفعال').'</td><td>'.e($r['connections']).'</td><td>'.e($r['requests']).'</td><td>'.e(formatBytes((float)$r['down_kb']*1024)).'</td><td>'.e(formatBytes((float)$r['up_kb']*1024)).'</td><td data-sort-value="'.e($r['total_bytes']).'">'.e(formatBytes($r['total_bytes'])).'</td><td>'.e($r['last_seen']).'</td></tr>';
 echo '</tbody></table>';tablePagination($p);if(!$p['rows'])echo '<div class="empty">دستگاهی ثبت نشده است.</div>';echo '</section>';
} catch(Throwable $ex){echo '<section class="panel">'.e($ex->getMessage()).'</section>';}
pageEnd();
