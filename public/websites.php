<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
pageStart('وب‌سایت‌ها و درخواست‌ها','websites.php');
try {
    [$from,$to,$fd,$td]=dateFilters(); rangeForm($fd,$td);
    $summary=queryRows('SELECT source_ip,COUNT(*) requests,COUNT(DISTINCT url) unique_urls FROM web_logs WHERE occurred_at BETWEEN ? AND ? GROUP BY source_ip ORDER BY requests DESC LIMIT 50',[$from,$to]);
    echo '<section class="panel"><h2>تعداد درخواست به تفکیک IP</h2><table><tr><th>IP سیستم</th><th>تعداد درخواست</th><th>URL یکتا</th></tr>';
    foreach($summary as $r) echo '<tr><td>'.e($r['source_ip']).'</td><td>'.e($r['requests']).'</td><td>'.e($r['unique_urls']).'</td></tr>';
    echo '</table></section>';
    $rows=queryRows('SELECT occurred_at,source_ip,http_method,url,http_status,response_bytes FROM web_logs WHERE occurred_at BETWEEN ? AND ? ORDER BY occurred_at DESC LIMIT 500',[$from,$to]);
    echo '<section class="panel"><h2>آخرین آدرس‌های ثبت‌شده (حداکثر ۵۰۰ رکورد)</h2><table><tr><th>زمان</th><th>IP</th><th>روش</th><th>آدرس</th><th>وضعیت</th><th>حجم پاسخ</th></tr>';
    foreach($rows as $r) echo '<tr><td>'.e($r['occurred_at']).'</td><td>'.e($r['source_ip']).'</td><td>'.e($r['http_method']).'</td><td style="max-width:520px;overflow-wrap:anywhere;white-space:normal">'.e($r['url']).'</td><td>'.e($r['http_status']).'</td><td>'.e($r['response_bytes']).'</td></tr>';
    echo '</table>'; if(!$rows) echo '<div class="empty">در این بازه درخواست وبی ثبت نشده است.</div>'; echo '</section>';
    echo '<p class="muted">توجه: URL کامل معمولاً فقط برای HTTP قابل مشاهده است؛ مسیر صفحات HTTPS به‌دلیل رمزنگاری TLS در لاگ معمولی Kerio ثبت نمی‌شود.</p>';
} catch(Throwable $ex) { echo '<section class="panel">'.e($ex->getMessage()).'</section>'; }
pageEnd();
