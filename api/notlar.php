<?php
/**
 * kulturvarliklari.com.tr — ziyaretçi notları ve soruları (ön onaylı)
 *  GET  ?sid=EFS01            → o yerin ONAYLANMIŞ notları (herkese açık JSON)
 *  POST (JSON) {sid, ad, metin, tur:"not|soru", web(boş), onay:true}
 *                             → web kökünün dışındaki kv_cache/notlar/bekleyen.jsonl dosyasına eklenir; yayımlanmaz.
 *  GET  ?yonet=PAROLA         → site sahibinin onay sayfası (bekleyen notlar; Onayla / Sil / Cevapla)
 *  POST (form) islem=onayla|sil, id, parola[, cevap] → not onaylanır (kv_cache/notlar/onayli.json) ya da silinir.
 *  Parola: kv_config.php içindeki NOTLAR_ANAHTARI (yoksa ERISIM_RAPOR_ANAHTARI, o da yoksa REKLAM_RAPOR_ANAHTARI).
 */
declare(strict_types=1);
require_once __DIR__ . '/_ip.php';
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
function jout(int $code, array $data): void { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }

$CONFIG = [];
foreach ([dirname(__DIR__, 2) . '/kv_config.php', __DIR__ . '/config.php'] as $f) {
    if (is_file($f)) { $c = include $f; if (is_array($c)) { $CONFIG = $c; break; } }
}
$KEY = (string)($CONFIG['NOTLAR_ANAHTARI'] ?? ($CONFIG['ERISIM_RAPOR_ANAHTARI'] ?? ($CONFIG['REKLAM_RAPOR_ANAHTARI'] ?? '')));
$cacheRoot = dirname(__DIR__, 2) . '/kv_cache';
if (!is_dir($cacheRoot) && !@mkdir($cacheRoot, 0755, true)) $cacheRoot = __DIR__ . '/cache';
$dir = $cacheRoot . '/notlar';
if (!is_dir($dir)) @mkdir($dir, 0755, true);
$pending = $dir . '/bekleyen.jsonl';
$approved = $dir . '/onayli.json';
$texts = json_decode((string)@file_get_contents(__DIR__ . '/texts.json'), true) ?: [];
$clean = fn($s, $n) => mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)$s)), 0, $n);
$readPending = function () use ($pending): array {
    return is_file($pending) ? array_values(array_filter(array_map(fn($l) => json_decode($l, true), file($pending, FILE_IGNORE_NEW_LINES)))) : [];
};
$writePending = function (array $rows) use ($pending): void {
    @file_put_contents($pending, implode('', array_map(fn($r) => json_encode($r, JSON_UNESCAPED_UNICODE) . "\n", $rows)), LOCK_EX);
};
$readApproved = function () use ($approved): array { return is_file($approved) ? (json_decode((string)file_get_contents($approved), true) ?: []) : []; };
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

// ---------- herkese açık: onaylı notlar ----------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['sid'])) {
    $sid = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$_GET['sid']));
    $all = $readApproved();
    header('Cache-Control: public, max-age=300');
    jout(200, ['notlar' => array_values($all[$sid] ?? [])]);
}

// ---------- site sahibi: onay sayfası ----------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['yonet'])) {
    if ($KEY === '' || !hash_equals($KEY, (string)$_GET['yonet'])) { http_response_code(403); exit('Parola hatalı ya da tanımlı değil.'); }
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex');
    $rows = $readPending();
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Ziyaretçi notları — onay</title>';
    echo '<style>body{font:15px/1.5 system-ui,sans-serif;max-width:820px;margin:20px auto;padding:0 14px}.n{border:1px solid #ddd;border-radius:10px;padding:12px 14px;margin:12px 0}.m{color:#666;font-size:13px}textarea{width:100%;box-sizing:border-box}button{padding:6px 14px;margin-right:6px;cursor:pointer}.ok{background:#1f7a4d;color:#fff;border:0;border-radius:6px}.no{background:#b3261e;color:#fff;border:0;border-radius:6px}</style>';
    echo '<h1>Bekleyen ziyaretçi notları (' . count($rows) . ')</h1><p class="m">Onaylanan notlar birkaç dakika içinde ilgili yer sayfasında görünür. Kişisel bilgi, hakaret, reklam ya da yanlış bilgi içeren notları silin.</p>';
    if (!$rows) echo '<p>Bekleyen not yok.</p>';
    foreach ($rows as $r) {
        $yer = $texts[$r['sid']]['name'] ?? $r['sid'];
        echo '<div class="n"><div class="m">' . $h($r['zaman']) . ' · <b>' . $h($yer) . '</b> (' . $h($r['sid']) . ') · ' . ($r['tur'] === 'soru' ? 'Soru' : 'Not') . ' · ' . $h($r['ad']) . '</div>';
        echo '<p>' . nl2br($h($r['metin'])) . '</p>';
        echo '<form method="post"><input type="hidden" name="parola" value="' . $h($_GET['yonet']) . '"><input type="hidden" name="id" value="' . $h($r['id']) . '">';
        echo '<textarea name="cevap" rows="2" placeholder="İsteğe bağlı: Kültür Varlıkları ekibinin cevabı (soru ise)"></textarea><br>';
        echo '<button class="ok" name="islem" value="onayla">Onayla ve yayımla</button><button class="no" name="islem" value="sil">Sil</button></form></div>';
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jout(405, ['error' => 'method']);

// ---------- site sahibi: onayla / sil ----------
if (isset($_POST['islem'])) {
    if ($KEY === '' || !hash_equals($KEY, (string)($_POST['parola'] ?? ''))) { http_response_code(403); exit('Parola hatalı.'); }
    $id = (string)($_POST['id'] ?? '');
    $rows = $readPending(); $keep = []; $hit = null;
    foreach ($rows as $r) { if (($r['id'] ?? '') === $id) $hit = $r; else $keep[] = $r; }
    if ($hit && $_POST['islem'] === 'onayla') {
        $all = $readApproved();
        $pub = ['id' => $hit['id'], 'tarih' => substr($hit['zaman'], 0, 10), 'ad' => $hit['ad'], 'tur' => $hit['tur'], 'metin' => $hit['metin'], 'dil' => $hit['dil'] ?? 'tr'];
        $cv = $clean($_POST['cevap'] ?? '', 1500);
        if ($cv !== '') $pub['cevap'] = $cv;
        $all[$hit['sid']] = array_merge([$pub], $all[$hit['sid']] ?? []);
        @file_put_contents($approved, json_encode($all, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
    if ($hit) $writePending($keep);
    header('Location: ?yonet=' . rawurlencode((string)$_POST['parola']), true, 303);
    exit;
}

// ---------- ziyaretçi: yeni not ----------
$host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
$origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
if ($origin === '' || parse_url($origin, PHP_URL_HOST) !== $host) jout(403, ['error' => 'origin']);
$in = json_decode((string)file_get_contents('php://input', false, null, 0, 8192), true);
if (!is_array($in)) jout(400, ['error' => 'json']);
if (!empty($in['web'])) jout(200, ['ok' => true]);            // bot tuzağı
if (empty($in['onay'])) jout(400, ['error' => 'onay']);
$sid = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($in['sid'] ?? '')));
if (!isset($texts[$sid])) jout(400, ['error' => 'sid']);
$metin = $clean($in['metin'] ?? '', 1000);
$ad = $clean($in['ad'] ?? '', 40);
if (mb_strlen($metin) < 15) jout(400, ['error' => 'kisa']);
if (preg_match_all('~https?://|www\.~i', $metin) > 1) jout(400, ['error' => 'link']);
$tur = ($in['tur'] ?? '') === 'soru' ? 'soru' : 'not';
$dil = preg_match('/^[a-z]{2}$/', (string)($in['dil'] ?? '')) ? (string)$in['dil'] : 'tr';

$tmp = sys_get_temp_dir() . '/kv_rehber';
if (!is_dir($tmp)) @mkdir($tmp, 0700, true);
$ipFile = $tmp . '/not_' . hash('sha256', kv_client_ip() . 'kv') . '.json';
$hits = is_file($ipFile) ? (json_decode((string)file_get_contents($ipFile), true) ?: []) : [];
$hits = array_values(array_filter($hits, fn($x) => $x > time() - 3600));
if (count($hits) >= 5) jout(429, ['error' => 'rate']);
$hits[] = time(); @file_put_contents($ipFile, json_encode($hits), LOCK_EX);
if (is_file($pending) && filesize($pending) > 3 * 1024 * 1024) jout(507, ['error' => 'dolu']);

$rec = ['id' => bin2hex(random_bytes(6)), 'zaman' => date('c'), 'sid' => $sid, 'ad' => $ad !== '' ? $ad : 'Ziyaretçi', 'tur' => $tur, 'metin' => $metin, 'dil' => $dil];
if (@file_put_contents($pending, json_encode($rec, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX) === false) jout(500, ['error' => 'yazma']);
jout(200, ['ok' => true]);
