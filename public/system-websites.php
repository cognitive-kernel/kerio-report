<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
pageStart('لینک‌های بازشده به تفکیک سیستم','system-websites.php');
try {
 [$from,$to,$fd,$td]=dateFilters();
 $devices=queryRows('SELECT id,hostname,ip_address,mac_address FROM devices ORDER BY hostname,ip_address');
 $selected=filter_input(INPUT_GET,'device',FILTER_VALIDATE_INT);
 if($selected!==null&&$selected!==false&&$selected<1)$selected=null;
 echo '<form class="filters" method="get"><label>از تاریخ <input type="date" name="from" value="'.e($fd).'"></label><label>تا تاریخ <input type="date" name="to" value="'.e($td).'"></label><label>سیستم<select name="device"><option value="">همه سیستم‌ها</option>';
 foreach($devices as $d)echo '<option value="'.e($d['id']).'" '.((int)$selected===(int)$d['id']?'selected':'').'>'.e(($d['hostname']?:'بدون نام').' — '.$d['ip_address'].' — '.($d['mac_address']?:'MAC نامشخص')).'</option>';
 echo '</select></label><button>اعمال فیلتر</button></form>';
 $where='WHERE occurred_at BETWEEN '.db()->quote($from).' AND '.db()->quote($to);
 if($selected)$where.=' AND (device_id = '.(int)$selected.' OR (device_id IS NULL AND source_ip = (SELECT ip_address FROM devices WHERE id = '.(int)$selected.')))';
 $summarySql='(SELECT source_ip,COUNT(*) requests,COUNT(DISTINCT url) unique_urls FROM web_logs '.$where.' GROUP BY source_ip) system_web_summary';
 $sumCols=['source_ip'=>'IP','requests'=>'تعداد درخواست','unique_urls'=>'لینک یکتا'];
 $sumSort=['source_ip'=>'source_ip','requests'=>'requests','unique_urls'=>'unique_urls'];
 $s=queryPage($summarySql,'',[],array_keys($sumCols),$sumSort,'requests');
 echo '<section class="panel"><h2>خلاصه لینک‌ها به تفکیک سیستم</h2>';tableControls($s,$sumCols);echo '<table>';tableHeader($sumCols,$s);echo '<tbody>';
 foreach($s['rows'] as $r)echo '<tr><td>'.e($r['source_ip']).'</td><td>'.e($r['requests']).'</td><td>'.e($r['unique_urls']).'</td></tr>';
 echo '</tbody></table>';tablePagination($s);echo '</section>';
 $cols=['occurred_at'=>'زمان','source_ip'=>'IP','http_method'=>'روش','url'=>'لینک','http_status'=>'وضعیت','response_bytes'=>'حجم پاسخ'];
 $sort=['occurred_at'=>'occurred_at','source_ip'=>'source_ip','http_method'=>'http_method','url'=>'url','http_status'=>'http_status','response_bytes'=>'response_bytes'];
 $p=queryPage('web_logs',$where,[],array_keys($cols),$sort,'occurred_at');
 echo '<section class="panel"><h2>فهرست لینک‌های بازشده</h2>';tableControls($p,$cols);echo '<table>';tableHeader($cols,$p);echo '<tbody>';
 foreach($p['rows'] as $r){$url=(string)$r['url'];$scheme=strtolower((string)parse_url($url,PHP_URL_SCHEME));$link=in_array($scheme,['http','https'],true)?'<a href="'.e($url).'" target="_blank" rel="noopener noreferrer">'.e($url).'</a>':e($url);echo '<tr><td>'.e($r['occurred_at']).'</td><td>'.e($r['source_ip']).'</td><td>'.e($r['http_method']).'</td><td class="url-cell">'.$link.'</td><td>'.e($r['http_status']).'</td><td data-sort-value="'.e($r['response_bytes']).'">'.e(formatBytes($r['response_bytes'])).'</td></tr>';}
 echo '</tbody></table>';tablePagination($p);if(!$p['rows'])echo '<div class="empty">هیچ لینکی در این بازه ثبت نشده است.</div>';echo '</section>';
 echo '<p class="muted">URL کامل صفحات HTTPS معمولاً در لاگ عادی Kerio قابل مشاهده نیست.</p>';
} catch(Throwable $ex){echo '<section class="panel">'.e($ex->getMessage()).'</section>';}
pageEnd();
