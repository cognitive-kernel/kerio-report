<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
pageStart('گزارش سیستم‌ها','systems.php');
try {
    $devices=queryRows('SELECT d.id,d.hostname,d.ip_address,d.mac_address,d.last_seen,d.is_active,
      COALESCE(c.connections,0) connections,COALESCE(w.requests,0) requests,
      COALESCE(t.down_kb,0) down_kb,COALESCE(t.up_kb,0) up_kb
      FROM devices d
      LEFT JOIN (SELECT device_id,COUNT(*) connections FROM connection_logs GROUP BY device_id) c ON c.device_id=d.id
      LEFT JOIN (SELECT device_id,COUNT(*) requests FROM web_logs GROUP BY device_id) w ON w.device_id=d.id
      LEFT JOIN (SELECT device_id,SUM(download_delta_kb) down_kb,SUM(upload_delta_kb) up_kb FROM traffic_samples GROUP BY device_id) t ON t.device_id=d.id
      ORDER BY d.last_seen DESC');
    echo '<section class="panel"><h2>خلاصه مصرف و فعالیت هر دستگاه</h2><table><tr><th>سیستم</th><th>IP</th><th>MAC</th><th>وضعیت</th><th>اتصالات</th><th>درخواست وب</th><th>دریافت KB</th><th>ارسال KB</th><th>آخرین مشاهده</th></tr>';
    foreach($devices as $r) echo '<tr><td>'.e($r['hostname']?:'نامشخص').'</td><td>'.e($r['ip_address']).'</td><td>'.e($r['mac_address']).'</td><td><span class="tag">'.($r['is_active']?'فعال':'غیرفعال').'</span></td><td>'.e($r['connections']).'</td><td>'.e($r['requests']).'</td><td>'.e(round((float)$r['down_kb'],2)).'</td><td>'.e(round((float)$r['up_kb'],2)).'</td><td>'.e($r['last_seen']).'</td></tr>';
    echo '</table></section>';
    if (!$devices) echo '<div class="empty">هنوز دستگاهی در دیتابیس ثبت نشده است.</div>';
} catch(Throwable $ex) { echo '<section class="panel">'.e($ex->getMessage()).'</section>'; }
pageEnd();
