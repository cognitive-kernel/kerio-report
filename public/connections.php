<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
pageStart('اتصالات شبکه','connections.php');
try {
 [$from,$to,$fd,$td]=dateFilters(); rangeForm($fd,$td);
 $fromSql='connection_logs';
 $where='WHERE occurred_at BETWEEN ? AND ?'; $params=[$from,$to];
 $cols=['occurred_at'=>'زمان','source_ip'=>'IP مبدأ','source_port'=>'پورت مبدأ','destination_host'=>'نام مقصد','destination_ip'=>'IP مقصد','destination_port'=>'پورت مقصد','protocol'=>'پروتکل','service_name'=>'سرویس','duration_seconds'=>'مدت (ثانیه)','bytes_total'=>'حجم مصرفی'];
 $sort=array_combine(array_keys($cols),array_keys($cols));
 $p=queryPage($fromSql,$where,$params,array_keys($cols),$sort,'occurred_at');
 echo '<section class="panel"><h2>اتصالات شبکه</h2>'; tableControls($p,$cols); echo '<table>'; tableHeader($cols,$p); echo '<tbody>';
 foreach($p['rows'] as $r) echo '<tr><td>'.e($r['occurred_at']).'</td><td>'.e($r['source_ip']).'</td><td>'.e($r['source_port']).'</td><td>'.e($r['destination_host']).'</td><td>'.e($r['destination_ip']).'</td><td>'.e($r['destination_port']).'</td><td>'.e($r['protocol']).'</td><td>'.e($r['service_name']).'</td><td>'.e($r['duration_seconds']).'</td><td data-sort-value="'.e($r['bytes_total']).'">'.e(formatBytes($r['bytes_total'])).'</td></tr>';
 echo '</tbody></table>'; tablePagination($p); if(!$p['rows']) echo '<div class="empty">رکوردی یافت نشد.</div>'; echo '</section>';
} catch(Throwable $ex){echo '<section class="panel">'.e($ex->getMessage()).'</section>';}
pageEnd();
