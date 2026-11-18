<?php
/**
 * kulturvarliklari.com.tr — çerezsiz ziyaretçi istatistiği
 *
 *  POST (JSON, navigator.sendBeacon) {t:"pv"|"hb"|"ev", k, p, s, l, r, m, e}
 *     t  pv = sayfa açıldı, hb = sayfa hâlâ açık (15 sn'de bir), ev = tıklama (e: dinle, yol, liste, gezdim, konaklama, kart)
 *     k  sekmeye özgü rastgele oturum kimliği (sessionStorage; çerez değil)
 *  Ziyaretçiyi ayırt etmek için IP + tarayıcı bilgisi, her gün değişen gizli bir tuzla
 *  tek yönlü özetlenir; IP adresi hiçbir yere yazılmaz. Ertesi gün tuz silinir, eski kayıtlar
 *  kimseyle eşleştirilemez.
 *
 *  GET ?rapor=PAROLA[&gun=30]  → site sahibine özet rapor (HTML)
 */
declare(strict_types=1);
require_once __DIR__ . '/_ip.php';
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
date_default_timezone_set('Europe/Istanbul');

$CONFIG = [];
foreach ([dirname(__DIR__, 2) . '/kv_config.php', __DIR__ . '/config.php'] as $f) {
    if (is_file($f)) { $c = include $f; if (is_array($c)) { $CONFIG = $c; break; } }
}
$cacheRoot = dirname(__DIR__, 2) . '/kv_cache';
if (!is_dir($cacheRoot) && !@mkdir($cacheRoot, 0755, true)) $cacheRoot = __DIR__ . '/cache';
$DIR = $cacheRoot . '/sayac';
if (!is_dir($DIR)) @mkdir($DIR, 0755, true);

function bot(string $ua): bool {
    return $ua === '' || (bool)preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|embedly|quora|pinterest|whatsapp|telegram|headless|lighthouse|pagespeed|gtmetrix|python|curl|wget|httpclient|java\/|go-http|axios|node-fetch|preview|monitor|uptime/i', $ua);
}

/* ======================= kayıt ======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: text/plain');
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (bot($ua)) { http_response_code(204); exit; }
    $host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
    $origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== $host) { http_response_code(204); exit; }
    $in = json_decode((string)file_get_contents('php://input', false, null, 0, 2048), true);
    if (!is_array($in)) { http_response_code(204); exit; }
    $t = (string)($in['t'] ?? '');
    if (!in_array($t, ['pv', 'hb', 'ev'], true)) { http_response_code(204); exit; }

    $day = date('Y-m-d');
    $file = "$DIR/$day.jsonl";
    if (is_file($file) && filesize($file) > 80 * 1024 * 1024) { http_response_code(204); exit; } // kötüye kullanıma karşı tavan

    // günlük tuz (gizli, ertesi gün silinir)
    $saltF = "$DIR/.tuz-$day";
    if (!is_file($saltF)) {
        @file_put_contents($saltF, bin2hex(random_bytes(32)), LOCK_EX);
        foreach (glob("$DIR/.tuz-*") ?: [] as $old) if ($old !== $saltF) @unlink($old);
    }
    $salt = (string)@file_get_contents($saltF);
    $v = substr(hash_hmac('sha256', kv_client_ip() . '|' . $ua, $salt), 0, 16);

    $clean = fn($s, $n, $re = '/[^\p{L}\p{N}\/\.\-_#]/u') => mb_substr(preg_replace($re, '', (string)$s), 0, $n);
    $rec = ['z' => time(), 't' => $t, 'v' => $v, 'k' => substr(hash('sha256', $salt . (string)($in['k'] ?? '')), 0, 12),
            'p' => $clean($in['p'] ?? '', 160)];
    $s = strtoupper($clean($in['s'] ?? '', 8, '/[^A-Za-z0-9]/'));
    if ($s !== '') $rec['s'] = $s;
    if ($t === 'pv') {
        $rec['l'] = $clean($in['l'] ?? '', 5, '/[^a-z]/');
        $rec['m'] = !empty($in['m']) ? 1 : 0;
        $r = strtolower($clean($in['r'] ?? '', 80, '/[^A-Za-z0-9\.\-]/'));
        if ($r !== '' && $r !== $host && $r !== 'www.' . $host) $rec['r'] = $r;
    }
    if ($t === 'ev') {
        $e = $clean($in['e'] ?? '', 16, '/[^a-z]/');
        if (!in_array($e, ['dinle', 'yol', 'liste', 'gezdim', 'konaklama', 'kart', 'harita', 'rehber'], true)) { http_response_code(204); exit; }
        $rec['e'] = $e;
    }
    @file_put_contents($file, json_encode($rec, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    http_response_code(204);
    exit;
}

/* ======================= rapor ======================= */
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');
$key = (string)($CONFIG['SAYAC_RAPOR_ANAHTARI'] ?? ($CONFIG['ERISIM_RAPOR_ANAHTARI'] ?? ($CONFIG['REKLAM_RAPOR_ANAHTARI'] ?? '')));
if ($key === '' || !hash_equals($key, (string)($_GET['rapor'] ?? ''))) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><title>Yetkisiz</title><p style="font:16px system-ui;padding:40px">Bu sayfa için parola gerekli.</p>';
    exit;
}

/** Bir günün kayıtlarını özetler. Geçmiş günlerin özeti bir kez hesaplanıp saklanır. */
function ozet(string $DIR, string $day): array {
    $cache = "$DIR/ozet-$day.json";
    $today = date('Y-m-d');
    if ($day !== $today && is_file($cache)) return json_decode((string)file_get_contents($cache), true) ?: [];
    $f = "$DIR/$day.jsonl";
    $O = ['ziyaretci' => 0, 'ziyaret' => 0, 'sayfa' => 0, 'sure' => 0, 'hemen' => 0, 'uzun' => 0,
          'yer' => [], 'olay' => [], 'kaynak' => [], 'dil' => [], 'cihaz' => ['mobil' => 0, 'masaustu' => 0], 'sayfalar' => []];
    if (!is_file($f)) return $O;
    $V = []; $S = []; $P = [];
    $h = fopen($f, 'r');
    while (($line = fgets($h)) !== false) {
        $x = json_decode($line, true); if (!$x) continue;
        $k = $x['k']; $z = (int)$x['z'];
        $V[$x['v']] = 1;
        if (!isset($S[$k])) $S[$k] = ['a' => $z, 'b' => $z, 'pv' => 0];
        $S[$k]['a'] = min($S[$k]['a'], $z); $S[$k]['b'] = max($S[$k]['b'], $z);
        $pk = $k . '|' . $x['p'];
        if (!isset($P[$pk])) $P[$pk] = ['a' => $z, 'b' => $z, 's' => $x['s'] ?? '', 'v' => $x['v']];
        $P[$pk]['a'] = min($P[$pk]['a'], $z); $P[$pk]['b'] = max($P[$pk]['b'], $z);
        if ($x['t'] === 'pv') {
            $O['sayfa']++; $S[$k]['pv']++;
            $O['sayfalar'][$x['p']] = ($O['sayfalar'][$x['p']] ?? 0) + 1;
            if (!empty($x['s'])) { $O['yer'][$x['s']]['g'] = ($O['yer'][$x['s']]['g'] ?? 0) + 1; $O['yer'][$x['s']]['_v'][$x['v']] = 1; }
            $l = $x['l'] ?? ''; if ($l !== '') $O['dil'][$l] = ($O['dil'][$l] ?? 0) + 1;
            if (!isset($S[$k]['ilk'])) { $S[$k]['ilk'] = 1; $O['cihaz'][!empty($x['m']) ? 'mobil' : 'masaustu']++;
                $src = $x['r'] ?? 'doğrudan';
                foreach (['google' => 'Google', 'yandex' => 'Yandex', 'bing' => 'Bing', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'fb.' => 'Facebook', 't.co' => 'X (Twitter)', 'twitter' => 'X (Twitter)', 'whatsapp' => 'WhatsApp', 'duckduckgo' => 'DuckDuckGo', 'chatgpt' => 'ChatGPT', 'claude' => 'Claude'] as $n => $lab)
                    if (strpos($src, $n) !== false) { $src = $lab; break; }
                $O['kaynak'][$src] = ($O['kaynak'][$src] ?? 0) + 1; }
        } elseif ($x['t'] === 'ev') {
            $O['olay'][$x['e']] = ($O['olay'][$x['e']] ?? 0) + 1;
            if (!empty($x['s'])) $O['yer'][$x['s']]['e'][$x['e']] = ($O['yer'][$x['s']]['e'][$x['e']] ?? 0) + 1;
        }
    }
    fclose($h);
    $O['ziyaretci'] = count($V);
    foreach ($S as $s) {
        if ($s['pv'] < 1) continue;
        $O['ziyaret']++;
        $d = min($s['b'] - $s['a'], 3600);
        $O['sure'] += $d;
        if ($s['pv'] <= 1 && $d < 10) $O['hemen']++;
        if ($d >= 300) $O['uzun']++;
    }
    foreach ($P as $p) if ($p['s'] !== '') $O['yer'][$p['s']]['t'] = ($O['yer'][$p['s']]['t'] ?? 0) + min($p['b'] - $p['a'], 1800);
    foreach ($O['yer'] as $sid => $y) { $O['yer'][$sid]['k'] = count($y['_v'] ?? []); unset($O['yer'][$sid]['_v']); }
    arsort($O['sayfalar']); $O['sayfalar'] = array_slice($O['sayfalar'], 0, 40, true);
    if ($day !== $today) @file_put_contents($cache, json_encode($O, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $O;
}

$gun = max(1, min(365, (int)($_GET['gun'] ?? 30)));
$days = []; for ($i = $gun - 1; $i >= 0; $i--) $days[] = date('Y-m-d', strtotime("-$i day"));
$D = []; foreach ($days as $d) $D[$d] = ozet($DIR, $d);
$names = [];
$tx = json_decode((string)@file_get_contents(__DIR__ . '/texts.json'), true) ?: [];
foreach ($tx as $sid => $v) $names[$sid] = $v['name'] ?? $sid;

$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$sure = function (int $sn): string { if ($sn < 60) return $sn . ' sn'; return intdiv($sn, 60) . ' dk ' . str_pad((string)($sn % 60), 2, '0', STR_PAD_LEFT) . ' sn'; };
$sum = fn(string $k) => array_sum(array_map(fn($o) => $o[$k] ?? 0, $D));
$merge = function (string $k) use ($D) { $m = []; foreach ($D as $o) foreach (($o[$k] ?? []) as $a => $b) $m[$a] = ($m[$a] ?? 0) + $b; arsort($m); return $m; };
$today = $D[end($days)];
$ziy = $sum('ziyaret');
$YER = [];
foreach ($D as $o) foreach (($o['yer'] ?? []) as $sid => $y) {
    $YER[$sid]['g'] = ($YER[$sid]['g'] ?? 0) + ($y['g'] ?? 0);
    $YER[$sid]['k'] = ($YER[$sid]['k'] ?? 0) + ($y['k'] ?? 0);
    $YER[$sid]['t'] = ($YER[$sid]['t'] ?? 0) + ($y['t'] ?? 0);
    foreach (($y['e'] ?? []) as $a => $b) $YER[$sid]['e'][$a] = ($YER[$sid]['e'][$a] ?? 0) + $b;
}
uasort($YER, fn($a, $b) => ($b['g'] ?? 0) <=> ($a['g'] ?? 0));
$OLAY = ['dinle' => 'Dinle', 'yol' => 'Yol tarifi', 'liste' => 'Listeme ekle', 'gezdim' => 'Gezdim', 'konaklama' => 'Yakında konaklama', 'kart' => 'Ana sayfada kart açma', 'harita' => 'Haritada aç', 'rehber' => 'Rehbere sor'];
$DIL = ['tr' => 'Türkçe', 'en' => 'English', 'de' => 'Deutsch', 'zh' => '中文', 'hi' => 'हिन्दी', 'es' => 'Español', 'ar' => 'العربية'];
$maxZ = max(1, max(array_map(fn($o) => $o['ziyaretci'], $D)));
$q = '?rapor=' . rawurlencode((string)$_GET['rapor']);
?><!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex"><title>Ziyaretçi istatistikleri | Kültür Varlıkları</title>
<style>
body{font:15px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif;margin:0;background:#f4ede1;color:#16120f}
main{max-width:1100px;margin:0 auto;padding:24px 18px 60px}h1{font-size:26px;margin:0 0 4px}h2{font-size:18px;margin:34px 0 10px}
.sub{color:#6b5d4c;margin-bottom:18px}.sub a{color:#b5532f;font-weight:600;margin-right:10px}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px}
.kpi{background:#fffdf9;border:1px solid #ddd0b8;border-radius:12px;padding:14px}.kpi b{display:block;font-size:26px}.kpi span{color:#6b5d4c;font-size:13px}
.chart{display:flex;align-items:flex-end;gap:3px;height:150px;background:#fffdf9;border:1px solid #ddd0b8;border-radius:12px;padding:12px 10px 26px;position:relative;overflow-x:auto}
.bar{flex:1;min-width:8px;background:#b5532f;border-radius:3px 3px 0 0;position:relative}.bar i{position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);font-size:10px;color:#6b5d4c;font-style:normal;white-space:nowrap}
.bar:hover{background:#16120f}
table{width:100%;border-collapse:collapse;background:#fffdf9;border:1px solid #ddd0b8;border-radius:12px;overflow:hidden}
th,td{padding:8px 10px;border-bottom:1px solid #eee3d0;text-align:left;font-size:14px}th{background:#e8dcc6;font-weight:700}td.n,th.n{text-align:right;font-variant-numeric:tabular-nums}
.two{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px}.note{color:#6b5d4c;font-size:13px;margin-top:20px}
</style></head><body><main>
<h1>Ziyaretçi istatistikleri</h1>
<div class="sub">Son <?= $gun ?> gün · <?= $e(date('d.m.Y H:i')) ?> ·
<a href="<?= $e($q) ?>&gun=7">7 gün</a><a href="<?= $e($q) ?>&gun=30">30 gün</a><a href="<?= $e($q) ?>&gun=90">90 gün</a><a href="<?= $e($q) ?>&gun=365">1 yıl</a></div>

<h2>Bugün</h2>
<div class="kpis">
<div class="kpi"><b><?= $today['ziyaretci'] ?></b><span>ziyaretçi (kişi)</span></div>
<div class="kpi"><b><?= $today['ziyaret'] ?></b><span>ziyaret</span></div>
<div class="kpi"><b><?= $today['sayfa'] ?></b><span>sayfa görüntüleme</span></div>
<div class="kpi"><b><?= $today['ziyaret'] ? $sure(intdiv($today['sure'], $today['ziyaret'])) : '—' ?></b><span>ortalama kalma süresi</span></div>
</div>

<h2>Son <?= $gun ?> gün</h2>
<div class="kpis">
<div class="kpi"><b><?= number_format($sum('ziyaretci'), 0, ',', '.') ?></b><span>ziyaretçi (günlük toplam)</span></div>
<div class="kpi"><b><?= number_format($ziy, 0, ',', '.') ?></b><span>ziyaret</span></div>
<div class="kpi"><b><?= number_format($sum('sayfa'), 0, ',', '.') ?></b><span>sayfa görüntüleme</span></div>
<div class="kpi"><b><?= $ziy ? $sure(intdiv($sum('sure'), $ziy)) : '—' ?></b><span>ortalama kalma süresi</span></div>
<div class="kpi"><b><?= $ziy ? round($sum('sayfa') / $ziy, 1) : '—' ?></b><span>ziyaret başına sayfa</span></div>
<div class="kpi"><b><?= $ziy ? round(100 * $sum('hemen') / $ziy) . '%' : '—' ?></b><span>hemen çıkan (10 sn altı)</span></div>
<div class="kpi"><b><?= $ziy ? round(100 * $sum('uzun') / $ziy) . '%' : '—' ?></b><span>5 dakikadan uzun kalan</span></div>
</div>

<h2>Günlük ziyaretçi</h2>
<div class="chart"><?php foreach ($D as $d => $o): ?><div class="bar" style="height:<?= max(1, round(100 * $o['ziyaretci'] / $maxZ)) ?>%" title="<?= $e(date('d.m.Y', strtotime($d))) ?>: <?= $o['ziyaretci'] ?> ziyaretçi, <?= $o['ziyaret'] ?> ziyaret, <?= $o['sayfa'] ?> sayfa"><?php if ($gun <= 31 || substr($d, 8) === '01'): ?><i><?= $e(substr($d, 8) . '.' . substr($d, 5, 2)) ?></i><?php endif ?></div><?php endforeach ?></div>

<h2>Müze ve ören yerleri</h2>
<table><tr><th>#</th><th>Yer</th><th class="n">Görüntülenme</th><th class="n">Kişi</th><th class="n">Ort. süre</th><th class="n">Dinle</th><th class="n">Yol tarifi</th><th class="n">Listeme ekle</th></tr>
<?php $i = 0; foreach ($YER as $sid => $y): if (++$i > 100) break; ?>
<tr><td><?= $i ?></td><td><?= $e($names[$sid] ?? $sid) ?></td><td class="n"><?= $y['g'] ?? 0 ?></td><td class="n"><?= $y['k'] ?? 0 ?></td><td class="n"><?= !empty($y['g']) ? $sure(intdiv($y['t'] ?? 0, max(1, $y['g']))) : '—' ?></td><td class="n"><?= $y['e']['dinle'] ?? 0 ?></td><td class="n"><?= $y['e']['yol'] ?? 0 ?></td><td class="n"><?= $y['e']['liste'] ?? 0 ?></td></tr>
<?php endforeach; if (!$YER): ?><tr><td colspan="8">Henüz kayıt yok.</td></tr><?php endif ?></table>

<div class="two">
<div><h2>Nereden geldiler</h2><table><tr><th>Kaynak</th><th class="n">Ziyaret</th></tr>
<?php foreach (array_slice($merge('kaynak'), 0, 15, true) as $a => $b): ?><tr><td><?= $e($a) ?></td><td class="n"><?= $b ?></td></tr><?php endforeach ?></table></div>
<div><h2>Dil</h2><table><tr><th>Dil</th><th class="n">Sayfa</th></tr>
<?php foreach ($merge('dil') as $a => $b): ?><tr><td><?= $e($DIL[$a] ?? $a) ?></td><td class="n"><?= $b ?></td></tr><?php endforeach ?></table>
<h2>Cihaz</h2><table><?php foreach ($merge('cihaz') as $a => $b): ?><tr><td><?= $a === 'mobil' ? 'Telefon / tablet' : 'Bilgisayar' ?></td><td class="n"><?= $b ?></td></tr><?php endforeach ?></table></div>
<div><h2>Tıklamalar</h2><table><tr><th>Düğme</th><th class="n">Tıklama</th></tr>
<?php foreach ($merge('olay') as $a => $b): ?><tr><td><?= $e($OLAY[$a] ?? $a) ?></td><td class="n"><?= $b ?></td></tr><?php endforeach ?></table></div>
</div>

<h2>En çok açılan sayfalar</h2>
<table><tr><th>Sayfa</th><th class="n">Görüntülenme</th></tr>
<?php foreach (array_slice($merge('sayfalar'), 0, 30, true) as $a => $b): ?><tr><td><?= $e($a) ?></td><td class="n"><?= $b ?></td></tr><?php endforeach ?></table>

<p class="note">"Kişi" sayısı her gün ayrı hesaplanır; aynı kişi iki farklı günde gelirse iki kez sayılır. Kalma süresi, sayfa açıkken 15 saniyede bir gönderilen sinyalle ölçülür; çerez kullanılmaz, IP adresi saklanmaz. Arama motoru botları sayılmaz.</p>
</main></body></html>
