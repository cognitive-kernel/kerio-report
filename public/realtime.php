<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
pageStart('وضعیت لحظه‌ای','realtime.php');
try {
    $rows=queryRows('SELECT ip_address,hostname,mac_address,last_seen,is_active FROM devices ORDER BY is_active DESC,last_seen DESC');
    echo '<section class="panel"><h2>آخرین وضعیت ثبت‌شده دستگاه‌ها</h2><p class="muted">این صفحه وضعیت آخرین نمونه ذخیره‌شده را نشان می‌دهد؛ برای به‌روزرسانی خودکار، Collector باید روی سرور اجرا و زمان‌بندی شود.</p><table><tr><th>وضعیت</th><th>نام سیستم</th><th>IP</th><th>MAC</th><th>آخرین نمونه</th></tr>';
    foreach($rows as $r) echo '<tr><td><span class="tag">'.($r['is_active']?'فعال':'غیرفعال').'</span></td><td>'.e($r['hostname']?:'نامشخص').'</td><td>'.e($r['ip_address']).'</td><td>'.e($r['mac_address']).'</td><td>'.e($r['last_seen']).'</td></tr>';
    echo '</table></section><script>setTimeout(()=>location.reload(),30000)</script>';
    if (!$rows) echo '<div class="empty">هنوز داده‌ای از Kerio دریافت نشده است.</div>';
} catch(Throwable $ex) { echo '<section class="panel">'.e($ex->getMessage()).'</section>'; }
pageEnd();
