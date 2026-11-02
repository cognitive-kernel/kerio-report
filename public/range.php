<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
pageStart('گزارش بازه زمانی','range.php');
try {
 [$from,$to,$fd,$td]=dateFilters();rangeForm($fd,$td);
 $traffic=queryRows('SELECT DATE(occurred_at) day,SUM(COALESCE(bytes_in,0))/1024 down_kb,SUM(COALESCE(bytes_out,0))/1024 up_kb FROM connection_logs WHERE occurred_at BETWEEN ? AND ? GROUP BY DATE(occurred_at) ORDER BY day',[$from,$to]);
 $connections=(int)queryValue('SELECT COUNT(*) FROM connection_logs WHERE occurred_at BETWEEN ? AND ?',[$from,$to]);$requests=(int)queryValue('SELECT COUNT(*) FROM web_logs WHERE occurred_at BETWEEN ? AND ?',[$from,$to]);
 echo '<div class="grid">';statCard('کل اتصالات',$connections);statCard('درخواست‌های وب',$requests);statCard('دریافت',formatBytes(array_sum(array_map('floatval',array_column($traffic,'down_kb')))*1024));statCard('ارسال',formatBytes(array_sum(array_map('floatval',array_column($traffic,'up_kb')))*1024));echo '</div>';
 chart('rangeTraffic','line',array_column($traffic,'day'),[['label'=>'دریافت (KB)','data'=>array_map('floatval',array_column($traffic,'down_kb'))],['label'=>'ارسال (KB)','data'=>array_map('floatval',array_column($traffic,'up_kb'))]]);
 $cols=['source_ip'=>'IP','total'=>'تعداد اتصال','bytes'=>'حجم مصرفی'];
 $sort=['source_ip'=>'source_ip','total'=>'total','bytes'=>'bytes'];
 $fromSql='(SELECT source_ip,COUNT(*) total,SUM(COALESCE(bytes_total,0)) bytes FROM connection_logs WHERE occurred_at BETWEEN '.db()->quote($from).' AND '.db()->quote($to).' GROUP BY source_ip) traffic_by_ip';
 $p=queryPage($fromSql,'',[],array_keys($cols),$sort,'total');
 echo '<section class="panel"><h2>پراکندگی اتصالات بر اساس سیستم</h2>';tableControls($p,$cols);echo '<table>';tableHeader($cols,$p);echo '<tbody>';
 foreach($p['rows'] as $r)echo '<tr><td>'.e($r['source_ip']).'</td><td>'.e($r['total']).'</td><td data-sort-value="'.e($r['bytes']).'">'.e(formatBytes($r['bytes'])).'</td></tr>';
 echo '</tbody></table>';tablePagination($p);echo '</section>';
} catch(Throwable $ex){echo '<section class="panel">'.e($ex->getMessage()).'</section>';}
pageEnd();
