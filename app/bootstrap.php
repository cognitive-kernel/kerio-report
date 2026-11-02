<?php
declare(strict_types=1);

$configFile = dirname(__DIR__) . '/config.local.php';
if (!is_file($configFile)) {
    http_response_code(503);
    exit('Configuration missing. Copy config.example.php to config.local.php and configure database access.');
}
$config = require $configFile;
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Tehran');

function db(): PDO {
    static $pdo = null;
    global $config;
    if ($pdo instanceof PDO) return $pdo;
    $d = $config['database'];
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $d['host'], (int)$d['port'], $d['name'], $d['charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $d['username'], $d['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
function e(mixed $value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function queryRows(string $sql, array $params = []): array {
    $stmt = db()->prepare($sql); $stmt->execute($params); return $stmt->fetchAll();
}
function queryValue(string $sql, array $params = []): mixed {
    $stmt = db()->prepare($sql); $stmt->execute($params); return $stmt->fetchColumn();
}
function dateFilters(): array {
    $from = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
    $to = $_GET['to'] ?? date('Y-m-d');
    foreach ([$from, $to] as $date) {
        $d = DateTime::createFromFormat('Y-m-d', (string)$date);
        if (!$d || $d->format('Y-m-d') !== $date) throw new InvalidArgumentException('Invalid date range');
    }
    if ($from > $to) throw new InvalidArgumentException('Start date must not exceed end date');
    return [$from . ' 00:00:00', $to . ' 23:59:59', $from, $to];
}

/** Fetch a fully database-filtered, database-sorted page. SQL fragments must be authored by the application. */
function queryPage(string $fromSql, string $whereSql, array $params, array $searchColumns, array $sortColumns, string $defaultSort, int $pageSize = 50): array {
    $q = trim((string)($_GET['q'] ?? ''));
    if ($q !== '' && $searchColumns) {
        $whereSql .= ($whereSql === '' ? ' WHERE ' : ' AND ') . '(' . implode(' OR ', array_map(static fn($column) => 'CAST(' . $column . ' AS CHAR) LIKE ?', $searchColumns)) . ')';
        foreach ($searchColumns as $_) $params[] = '%' . $q . '%';
    }
    $page = max(1, (int)($_GET['page'] ?? 1));
    $sort = (string)($_GET['sort'] ?? $defaultSort);
    if (!isset($sortColumns[$sort])) $sort = $defaultSort;
    $direction = strtolower((string)($_GET['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $whereSql = trim($whereSql);
    $fromClause = trim($fromSql) . ($whereSql !== '' ? ' ' . $whereSql : '');
    $total = (int)queryValue('SELECT COUNT(*) FROM ' . $fromClause, $params);
    $pages = max(1, (int)ceil($total / $pageSize));
    $page = min($page, $pages);
    $sql = 'SELECT * FROM ' . $fromClause . ' ORDER BY ' . $sortColumns[$sort] . ' ' . $direction . ' LIMIT ' . $pageSize . ' OFFSET ' . (($page - 1) * $pageSize);
    return ['rows' => queryRows($sql, $params), 'total' => $total, 'page' => $page, 'pages' => $pages, 'page_size' => $pageSize, 'q' => $q, 'sort' => $sort, 'dir' => strtolower($direction)];
}
function tableControls(array $result, array $labels): void {
    $query = $_GET;
    echo '<form class="table-query" method="get">';
    foreach (['from','to','device'] as $key) if (isset($query[$key]) && $query[$key] !== '') echo '<input type="hidden" name="'.e($key).'" value="'.e($query[$key]).'">';
    echo '<input type="search" name="q" value="'.e($result['q']).'" placeholder="جست‌وجو در تمام رکوردهای دیتابیس">';
    echo '<input type="hidden" name="sort" value="'.e($result['sort']).'"><input type="hidden" name="dir" value="'.e($result['dir']).'"><button>جست‌وجو</button><span class="table-count">نمایش '.e(count($result['rows'])).' از '.e($result['total']).' رکورد؛ صفحه '.e($result['page']).' از '.e($result['pages']).'</span></form>';
}
function tableHeader(array $labels, array $result): void {
    echo '<thead><tr>';
    foreach ($labels as $key => $label) {
        $next = $result['sort'] === (string)$key && $result['dir'] === 'asc' ? 'desc' : 'asc';
        $params = $_GET; $params['sort'] = $key; $params['dir'] = $next; $params['page'] = 1;
        echo '<th><a class="sort-link" href="?'.e(http_build_query($params)).'">'.e($label).($result['sort'] === (string)$key ? ($result['dir'] === 'asc' ? ' ▲' : ' ▼') : '').'</a></th>';
    }
    echo '</tr></thead>';
}
function tablePagination(array $result): void {
    if ($result['pages'] <= 1) return;
    echo '<nav class="pagination" aria-label="صفحه‌بندی">';
    foreach (array_unique([1, max(1, $result['page'] - 1), $result['page'], min($result['pages'], $result['page'] + 1), $result['pages']]) as $page) {
        $params = $_GET; $params['page'] = $page;
        echo '<a class="'.($page === $result['page'] ? 'current' : '').'" href="?'.e(http_build_query($params)).'">'.e($page).'</a>';
    }
    echo '</nav>';
}
function pageStart(string $title, string $active): void {
    global $config;
    $name = $config['app']['name'] ?? 'Kerio Report';
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' | '.e($name).'</title><link rel="stylesheet" href="assets/app.css"></head><body><aside class="sidebar"><div class="brand">Kerio <b>Report</b></div><nav>';
    $links = ['index.php'=>'داشبورد کلی','range.php'=>'گزارش بازه زمانی','systems.php'=>'گزارش سیستم‌ها','realtime.php'=>'وضعیت لحظه‌ای','connections.php'=>'اتصالات شبکه','websites.php'=>'وب‌سایت‌ها','system-websites.php'=>'لینک‌های هر سیستم'];
    foreach ($links as $href=>$label) echo '<a class="'.($active===$href?'active':'').'" href="'.$href.'">'.e($label).'</a>';
    echo '</nav><div class="side-note">گزارش‌گیری از داده‌های ذخیره‌شده در Kerio Report</div></aside><main class="main"><header><div><h1>'.e($title).'</h1></div><span class="live-dot">ذخیره‌سازی محلی</span></header>';
}
function pageEnd(): void { echo '</main><script src="assets/app.js"></script></body></html>'; }
function formatBytes(mixed $bytes): string {
    $n=max(0,(float)$bytes); $units=['B','KB','MB','GB','TB']; $i=0;
    while($n>=1024 && $i<count($units)-1){$n/=1024;$i++;}
    return number_format($n,$i===0?0:2).' '.$units[$i];
}
function statCard(string $label, mixed $value, string $hint=''): void {
    echo '<section class="stat"><span>'.e($label).'</span><strong>'.e(number_format((float)$value, 0)).'</strong><small>'.e($hint).'</small></section>';
}
function chart(string $id, string $type, array $labels, array $datasets): void {
    $width=900; $height=280; $left=58; $right=18; $top=18; $bottom=48;
    $values=[]; foreach($datasets as $ds) foreach(($ds['data']??[]) as $v) $values[]=max(0,(float)$v);
    $max=max($values?:[1]); if($max<=0)$max=1;
    $plotW=$width-$left-$right; $plotH=$height-$top-$bottom;
    $svg='<svg class="report-chart" viewBox="0 0 '.$width.' '.$height.'" role="img" aria-label="'.e($id).'">';
    for($i=0;$i<=4;$i++){ $y=$top+$plotH*$i/4; $val=$max*(4-$i)/4; $svg.='<line x1="'.$left.'" y1="'.$y.'" x2="'.($width-$right).'" y2="'.$y.'" stroke="#2b3d55"/><text x="'.($left-8).'" y="'.($y+4).'" text-anchor="end" fill="#9aacc4" font-size="11">'.e(number_format($val,1)).'</text>'; }
    $colors=['#54d6b0','#65a9ff','#f2bd65','#d88aff']; $n=max(1,count($labels)); $step=$plotW/$n;
    foreach($datasets as $di=>$ds){$points=[];$data=$ds['data']??[];foreach($data as $i=>$v){$x=$left+$step*($i+.5);$y=$top+$plotH-((float)$v/$max)*$plotH;if($type==='bar'){$bw=max(3,min(24,$step/(count($datasets)+1)));$x+=($di-(count($datasets)-1)/2)*$bw;$svg.='<rect x="'.($x-$bw/2).'" y="'.$y.'" width="'.$bw.'" height="'.max(0,$top+$plotH-$y).'" rx="2" fill="'.$colors[$di%4].'"><title>'.e($ds['label']??'').': '.e($v).'</title></rect>';}else{$points[]=$x.','.$y;}}if($type!=='bar'&&$points){foreach($points as $point){[$cx,$cy]=array_map('floatval',explode(',',$point));$svg.='<circle cx="'.$cx.'" cy="'.$cy.'" r="3" fill="'.$colors[$di%4].'"/>';}if(count($points)>1)$svg.='<polyline fill="none" stroke="'.$colors[$di%4].'" stroke-width="2.5" points="'.implode(' ',$points).'"/>';}}
    foreach($labels as $i=>$label){if($n>14&&$i%ceil($n/14)!==0)continue;$x=$left+$step*($i+.5);$short=function_exists('mb_substr')?mb_substr((string)$label,0,12):substr((string)$label,0,12);$svg.='<text x="'.$x.'" y="'.($height-16).'" text-anchor="middle" fill="#9aacc4" font-size="10">'.e($short).'</text>';}
    $svg.='</svg>'; $legend='<div class="chart-legend" aria-label="راهنمای نمودار"><strong>راهنمای نمودار:</strong>';foreach($datasets as $i=>$ds)$legend.='<span class="chart-legend-item"><i aria-hidden="true" style="background:'.$colors[$i%4].'"></i>'.e($ds['label']??'').'</span>';$legend.='</div>';
    echo '<section class="panel chart-panel">'.$svg.$legend.'</section>';
}
function rangeForm(string $from, string $to): void {
    echo '<form class="filters" method="get"><label>از تاریخ <input type="date" name="from" value="'.e($from).'"></label><label>تا تاریخ <input type="date" name="to" value="'.e($to).'"></label><button>اعمال بازه</button></form>';
}
