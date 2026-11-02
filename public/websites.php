<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
pageStart('وب‌سایت‌ها و درخواست‌ها','websites.php');
try {
 [$from,$to,$fd,$td]=dateFilters();rangeForm($fd,$td);
 $summarySql='(SELECT source_ip,COUNT(*) requests,COUNT(DISTINCT url) unique_urls FROM web_logs WHERE occurred_at BETWEEN '.db()->quote($from).' AND '.db()->quote($to).' GROUP BY source_ip) website_summary';
 $sumCols=['source_ip'=>'IP سیستم','requests'=>'تعداد درخواست','unique_urls'=>'URL یکتا'];
 $sumSort=['source_ip'=>'source_ip','requests'=>'requests','unique_urls'=>'unique_urls'];
 $s=queryPage($summarySql,'',[],array_keys($sumCols),$sumSort,'requests');
 echo '<section class="panel"><h2>تعداد درخواست به تفکیک IP</h2>';tableControls($s,$sumCols);echo '<table>';tableHeader($sumCols,$s);echo '<tbody>';
 foreach($s['rows'] as $r)echo '<tr><td>'.e($r['source_ip']).'</td><td>'.e($r['requests']).'</td><td>'.e($r['unique_urls']).'</td></tr>';
 echo '</tbody></table>';tablePagination($s);echo '</section>';
 $cols=['occurred_at'=>'زمان','source_ip'=>'IP','http_method'=>'روش','url'=>'آدرس','http_status'=>'وضعیت','response_bytes'=>'حجم پاسخ'];
 $sort=['occurred_at'=>'occurred_at','source_ip'=>'source_ip','http_method'=>'http_method','url'=>'url','http_status'=>'http_status','response_bytes'=>'response_bytes'];
 $p=queryPage('web_logs','WHERE occurred_at BETWEEN ? AND ?',[$from,$to],array_keys($cols),$sort,'occurred_at');
 echo '<section class="panel"><h2>درخواست‌های وب</h2>';tableControls($p,$cols);echo '<table>';tableHeader($cols,$p);echo '<tbody>';
 foreach($p['rows'] as $r){$url=(string)$r['url'];$scheme=strtolower((string)parse_url($url,PHP_URL_SCHEME));$link=in_array($scheme,['http','https'],true)?'<a href="'.e($url).'" target="_blank" rel="noopener noreferrer">'.e($url).'</a>':e($url);echo '<tr><td>'.e($r['occurred_at']).'</td><td>'.e($r['source_ip']).'</td><td>'.e($r['http_method']).'</td><td class="url-cell">'.$link.'</td><td>'.e($r['http_status']).'</td><td data-sort-value="'.e($r['response_bytes']).'">'.e(formatBytes($r['response_bytes'])).'</td></tr>';}
 echo '</tbody></table>';tablePagination($p);if(!$p['rows'])echo '<div class="empty">در این بازه درخواستی ثبت نشده است.</div>';echo '</section>';
 echo '<p class="muted">URL کامل صفحات HTTPS معمولاً در لاگ عادی Kerio قابل مشاهده نیست.</p>';
} catch(Throwable $ex){echo '<section class="panel">'.e($ex->getMessage()).'</section>';}
pageEnd();
