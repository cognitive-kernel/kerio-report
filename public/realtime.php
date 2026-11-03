<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
pageStart('وضعیت لحظه‌ای','realtime.php');
try {
 $cols=['is_active'=>'وضعیت','hostname'=>'نام سیستم','ip_address'=>'IP','mac_address'=>'MAC','last_seen'=>'آخرین مشاهده'];
 $sort=['is_active'=>'is_active','hostname'=>'hostname','ip_address'=>'ip_address','mac_address'=>'mac_address','last_seen'=>'last_seen'];
 $p=queryPage('devices','',[],array_keys($cols),$sort,'is_active');
 echo '<section class="panel"><h2>آخرین وضعیت ثبت‌شده دستگاه‌ها</h2><p class="muted">وضعیت بر اساس آخرین نمونه ذخیره‌شده در MySQL نمایش داده می‌شود.</p>';tableControls($p,$cols);echo '<table>';tableHeader($cols,$p);echo '<tbody>';
 foreach($p['rows'] as $r)echo '<tr><td>'.($r['is_active']?'فعال':'غیرفعال').'</td><td>'.e($r['hostname']?:'نامشخص').'</td><td>'.e($r['ip_address']).'</td><td>'.e(formatMacAddress($r['mac_address'])).'</td><td>'.e($r['last_seen']).'</td></tr>';
 echo '</tbody></table>';tablePagination($p);if(!$p['rows'])echo '<div class="empty">هنوز داده‌ای ثبت نشده است.</div>';echo '</section>';
 echo '<script>setTimeout(()=>location.reload(),30000)</script>';
} catch(Throwable $ex){echo '<section class="panel">'.e($ex->getMessage()).'</section>';}
pageEnd();
