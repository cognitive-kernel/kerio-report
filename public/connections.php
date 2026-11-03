<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
pageStart('اتصالات شبکه','connections.php');
try {
 [$from,$to,$fd,$td]=dateFilters(); rangeForm($fd,$td);
 $fromSql='connection_logs c LEFT JOIN devices d ON d.id=c.device_id';
 $where='WHERE c.occurred_at BETWEEN ? AND ?'; $params=[$from,$to];
 $cols=['occurred_at'=>'زمان','hostname'=>'نام سیستم','source_ip'=>'IP مبدأ','mac_address'=>'MAC','source_port'=>'پورت مبدأ','destination_host'=>'نام مقصد','destination_ip'=>'IP مقصد','destination_port'=>'پورت مقصد','protocol'=>'پروتکل','service_name'=>'سرویس','duration_seconds'=>'مدت (ثانیه)','bytes_total'=>'حجم مصرفی'];
 $sort=['occurred_at'=>'c.occurred_at','hostname'=>'d.hostname','source_ip'=>'c.source_ip','mac_address'=>'d.mac_address','source_port'=>'c.source_port','destination_host'=>'c.destination_host','destination_ip'=>'c.destination_ip','destination_port'=>'c.destination_port','protocol'=>'c.protocol','service_name'=>'c.service_name','duration_seconds'=>'c.duration_seconds','bytes_total'=>'c.bytes_total'];
 $search=['c.occurred_at','d.hostname','c.source_ip','d.mac_address','c.source_port','c.destination_host','c.destination_ip','c.destination_port','c.protocol','c.service_name','c.duration_seconds','c.bytes_total'];
 $p=queryPage($fromSql,$where,$params,$search,$sort,'occurred_at');
 echo '<section class="panel"><h2>اتصالات شبکه</h2>'; tableControls($p,$cols); echo '<table>'; tableHeader($cols,$p); echo '<tbody>';
 foreach($p['rows'] as $r) echo '<tr><td>'.e($r['occurred_at']).'</td><td>'.e($r['hostname']?:'نامشخص').'</td><td>'.e($r['source_ip']).'</td><td>'.e($r['mac_address']?:'نامشخص').'</td><td>'.e($r['source_port']).'</td><td>'.e($r['destination_host']).'</td><td>'.e($r['destination_ip']).'</td><td>'.e($r['destination_port']).'</td><td>'.e($r['protocol']).'</td><td>'.e($r['service_name']).'</td><td>'.e($r['duration_seconds']).'</td><td data-sort-value="'.e($r['bytes_total']).'">'.e(formatBytes($r['bytes_total'])).'</td></tr>';
 echo '</tbody></table>'; tablePagination($p); if(!$p['rows']) echo '<div class="empty">رکوردی یافت نشد.</div>'; echo '</section>';
} catch(Throwable $ex){echo '<section class="panel">'.e($ex->getMessage()).'</section>';}
pageEnd();
