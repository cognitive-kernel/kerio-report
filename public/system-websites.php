<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
pageStart('لینک‌های بازشده به تفکیک سیستم','system-websites.php');
try {
    [$from,$to,$fd,$td]=dateFilters();
    $devices=queryRows('SELECT id,hostname,ip_address,mac_address FROM devices ORDER BY hostname,ip_address');
    $selected=filter_input(INPUT_GET,'device',FILTER_VALIDATE_INT);
    if ($selected !== null && $selected !== false && $selected < 1) $selected=null;
    echo '<form class="filters" method="get"><label>از تاریخ <input type="date" name="from" value="'.e($fd).'"></label><label>تا تاریخ <input type="date" name="to" value="'.e($td).'"></label><label>سیستم<select name="device"><option value="">همه سیستم‌ها</option>';
    foreach($devices as $d) echo '<option value="'.e($d['id']).'" '.((int)$selected===(int)$d['id']?'selected':'').'>'.e(($d['hostname']?:'بدون نام').' — '.$d['ip_address'].' — '.($d['mac_address']?:'MAC نامشخص')).'</option>';
    echo '</select></label><button>نمایش لینک‌ها</button></form>';

    $where='w.occurred_at BETWEEN ? AND ?';
    $params=[$from,$to];
    if ($selected) {
        $where.=' AND (w.device_id = ? OR (w.device_id IS NULL AND w.source_ip = (SELECT ip_address FROM devices WHERE id = ?)))';
        $params[]=$selected; $params[]=$selected;
    }
    $summary=queryRows('SELECT w.source_ip,COALESCE(d.hostname,"نامشخص") hostname,COUNT(*) requests,COUNT(DISTINCT w.url) unique_urls FROM web_logs w LEFT JOIN devices d ON d.id=w.device_id OR (w.device_id IS NULL AND d.ip_address=w.source_ip) WHERE '.$where.' GROUP BY w.source_ip,d.hostname ORDER BY requests DESC LIMIT 100',$params);
    echo '<section class="panel"><h2>خلاصه لینک‌ها به تفکیک سیستم</h2><table><tr><th>نام سیستم</th><th>IP</th><th>تعداد درخواست</th><th>لینک یکتا</th></tr>';
    foreach($summary as $r) echo '<tr><td>'.e($r['hostname']).'</td><td>'.e($r['source_ip']).'</td><td>'.e($r['requests']).'</td><td>'.e($r['unique_urls']).'</td></tr>';
    echo '</table>'; if(!$summary) echo '<div class="empty">برای این سیستم و بازه زمانی لینکی ثبت نشده است.</div>'; echo '</section>';

    $rows=queryRows('SELECT w.occurred_at,w.source_ip,w.http_method,w.url,w.http_status,w.response_bytes,COALESCE(d.hostname,"نامشخص") hostname FROM web_logs w LEFT JOIN devices d ON d.id=w.device_id OR (w.device_id IS NULL AND d.ip_address=w.source_ip) WHERE '.$where.' ORDER BY w.occurred_at DESC LIMIT 1000',$params);
    echo '<section class="panel"><h2>فهرست لینک‌های بازشده (حداکثر ۱۰۰۰ رکورد)</h2><table><tr><th>زمان</th><th>سیستم</th><th>IP</th><th>روش</th><th>لینک</th><th>وضعیت</th><th>حجم پاسخ</th></tr>';
    foreach($rows as $r) {
        $url=(string)$r['url']; $scheme=strtolower((string)parse_url($url,PHP_URL_SCHEME));
        $link=in_array($scheme,['http','https'],true) ? '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer">'.e($url).'</a>' : e($url);
        echo '<tr><td>'.e($r['occurred_at']).'</td><td>'.e($r['hostname']).'</td><td>'.e($r['source_ip']).'</td><td>'.e($r['http_method']).'</td><td style="max-width:600px;overflow-wrap:anywhere;white-space:normal">'.$link.'</td><td>'.e($r['http_status']).'</td><td>'.e($r['response_bytes']).'</td></tr>';
    }
    echo '</table>'; if(!$rows) echo '<div class="empty">هیچ لینکی در این بازه ثبت نشده است.</div>'; echo '</section>';
    echo '<p class="muted">انتساب لینک‌ها ابتدا با شناسه دستگاه و در صورت نبود آن با IP مبدأ انجام می‌شود. URL کامل صفحات HTTPS معمولاً در لاگ عادی Kerio قابل مشاهده نیست.</p>';
} catch(Throwable $ex) { echo '<section class="panel">'.e($ex->getMessage()).'</section>'; }
pageEnd();
