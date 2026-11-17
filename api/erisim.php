<?php
/**
 * kulturvarliklari.com.tr — engelsiz erişim bildirimleri
 *  POST (JSON) {sid, tekerlekli:"var|kismen|yok", ..., not, tarih, eposta, web(boş), onay:true}
 *      → bildirim web kökünün dışındaki kv_cache/erisim/bildirimler.jsonl dosyasına eklenir.
 *  GET ?rapor=PAROLA  → bekleyen bildirimleri listeler (yalnızca site sahibi).
 *      Onaylanan bilgiler erisim.json dosyasına işlenerek yayımlanır.
 */
declare(strict_types=1);
require_once __DIR__ . '/_ip.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
function out(int $code, array $data): void { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); exit; }

$CONFIG = [];
foreach ([dirname(__DIR__, 2) . '/kv_config.php', __DIR__ . '/config.php'] as $f) {
    if (is_file($f)) { $c = include $f; if (is_array($c)) { $CONFIG = $c; break; } }
}
$cacheRoot = dirname(__DIR__, 2) . '/kv_cache';
if (!is_dir($cacheRoot) && !@mkdir($cacheRoot, 0755, true)) $cacheRoot = __DIR__ . '/cache';
$dir = $cacheRoot . '/erisim';
if (!is_dir($dir)) @mkdir($dir, 0755, true);
$file = $dir . '/bildirimler.jsonl';

// ---------- site sahibi raporu ----------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $key = (string)($CONFIG['ERISIM_RAPOR_ANAHTARI'] ?? ($CONFIG['REKLAM_RAPOR_ANAHTARI'] ?? ''));
    if ($key === '' || !hash_equals($key, (string)($_GET['rapor'] ?? ''))) out(403, ['error' => 'parola']);
    $rows = is_file($file) ? array_values(array_filter(array_map(fn($l) => json_decode($l, true), file($file, FILE_IGNORE_NEW_LINES)))) : [];
    out(200, ['adet' => count($rows), 'bildirimler' => $rows]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['error' => 'method']);

$host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
$origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
if ($origin === '' || parse_url($origin, PHP_URL_HOST) !== $host) out(403, ['error' => 'origin']);

$raw = (string)file_get_contents('php://input', false, null, 0, 8192);
$in = json_decode($raw, true);
if (!is_array($in)) out(400, ['error' => 'json']);
if (!empty($in['web'])) out(200, ['ok' => true]);           // bot tuzağı
if (empty($in['onay'])) out(400, ['error' => 'onay']);

$texts = json_decode((string)@file_get_contents(__DIR__ . '/texts.json'), true) ?: [];
$sid = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($in['sid'] ?? '')));
if (!isset($texts[$sid])) out(400, ['error' => 'sid']);

$FIELDS = ['tekerlekli', 'yol', 'asansor', 'wc', 'otopark', 'sandalye', 'dinlenme', 'dokunsal', 'sesli', 'isaret'];
$rec = ['zaman' => date('c'), 'sid' => $sid, 'yer' => $texts[$sid]['name'] ?? ''];
$any = false;
foreach ($FIELDS as $k) {
    $v = (string)($in[$k] ?? '');
    if (in_array($v, ['var', 'kismen', 'yok'], true)) { $rec[$k] = $v; $any = true; }
}
$clean = fn($s, $n) => mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)$s)), 0, $n);
$note = $clean($in['not'] ?? '', 1200);
if ($note !== '') { $rec['not'] = $note; $any = true; }
if (!$any) out(400, ['error' => 'bos']);
$t = (string)($in['tarih'] ?? '');
if (preg_match('/^\d{4}-\d{2}$/', $t)) $rec['tarih'] = $t;
$mail = $clean($in['eposta'] ?? '', 120);
if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) $rec['eposta'] = $mail;

// ---------- sınırlar ----------
$tmp = sys_get_temp_dir() . '/kv_rehber';
if (!is_dir($tmp)) @mkdir($tmp, 0700, true);
$ipFile = $tmp . '/acc_' . hash('sha256', (kv_client_ip()) . 'kv') . '.json';
$hits = is_file($ipFile) ? (json_decode((string)file_get_contents($ipFile), true) ?: []) : [];
$hits = array_values(array_filter($hits, fn($x) => $x > time() - 3600));
if (count($hits) >= 8) out(429, ['error' => 'rate']);
$hits[] = time(); @file_put_contents($ipFile, json_encode($hits), LOCK_EX);
if (is_file($file) && filesize($file) > 5 * 1024 * 1024) out(507, ['error' => 'dolu']);

if (@file_put_contents($file, json_encode($rec, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX) === false) out(500, ['error' => 'yazma']);
out(200, ['ok' => true]);
