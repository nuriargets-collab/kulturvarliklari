<?php
/**
 * kulturvarliklari.com.tr — ek dillerde sesli anlatım için çeviri uç noktası
 * GET ?sid=EFS01&lang=ru  →  {"text": "..."}
 * Her metin yalnızca BİR KEZ çevrilir ve sunucuda saklanır; sonraki istekler API'ye gitmez.
 */
declare(strict_types=1);
require_once __DIR__ . '/_ip.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
function out(int $code, array $data): void { http_response_code($code); if ($code === 200) header('Cache-Control: public, max-age=604800'); else header('Cache-Control: no-store'); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }

$LANGS = ['ru' => 'Russian', 'fr' => 'French', 'it' => 'Italian', 'ja' => 'Japanese'];
$sid = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_GET['sid'] ?? '')));
$lang = (string)($_GET['lang'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') out(405, ['error' => 'method']);
if (!isset($LANGS[$lang]) || strlen($sid) < 4 || strlen($sid) > 6) out(400, ['error' => 'param']);

$texts = json_decode((string)@file_get_contents(__DIR__ . '/texts.json'), true) ?: [];
if (!isset($texts[$sid])) out(404, ['error' => 'sid']);

// ---------- önbellek (tercihen web kökünün dışında) ----------
$cacheRoot = dirname(__DIR__, 2) . '/kv_cache';
if (!is_dir($cacheRoot) && !@mkdir($cacheRoot, 0755, true)) $cacheRoot = __DIR__ . '/cache';
$dir = $cacheRoot . '/' . $lang;
if (!is_dir($dir)) @mkdir($dir, 0755, true);
$file = $dir . '/' . $sid . '.txt';
if (is_file($file)) out(200, ['text' => (string)file_get_contents($file), 'cached' => true]);

// ---------- yalnızca kendi sitemizden ----------
$host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
$origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
if ($origin === '' || parse_url($origin, PHP_URL_HOST) !== $host) out(403, ['error' => 'origin']);

// ---------- ayarlar ----------
$CONFIG = [];
foreach ([dirname(__DIR__, 2) . '/kv_config.php', __DIR__ . '/config.php'] as $f) {
    if (is_file($f)) { $c = include $f; if (is_array($c)) { $CONFIG = $c; break; } }
}
$API_KEY = getenv('ANTHROPIC_API_KEY') ?: ($CONFIG['ANTHROPIC_API_KEY'] ?? '');
$MODEL   = $CONFIG['MODEL'] ?? 'claude-haiku-5-5';
$PER_DAY = (int)($CONFIG['TRANSLATE_PER_DAY'] ?? 400);
if ($API_KEY === '' || str_starts_with($API_KEY, 'BURAYA')) out(503, ['error' => 'not_configured']);

// ---------- sınırlar (yalnızca yeni çeviriler sayılır) ----------
$tmp = sys_get_temp_dir() . '/kv_rehber';
if (!is_dir($tmp)) @mkdir($tmp, 0700, true);
$now = time();
$ipFile = $tmp . '/tr_' . hash('sha256', (kv_client_ip()) . 'kv') . '.json';
$hits = is_file($ipFile) ? (json_decode((string)file_get_contents($ipFile), true) ?: []) : [];
$hits = array_values(array_filter($hits, fn($t) => $t > $now - 600));
if (count($hits) >= 12) out(429, ['error' => 'rate_ip']);
$hits[] = $now; @file_put_contents($ipFile, json_encode($hits), LOCK_EX);
$dayFile = $tmp . '/trday_' . date('Ymd') . '.txt';
$day = (int)@file_get_contents($dayFile);
if ($day >= $PER_DAY) out(429, ['error' => 'rate_day']);
@file_put_contents($dayFile, (string)($day + 1), LOCK_EX);

// ---------- çeviri ----------
$src = $texts[$sid]['tr'];
$payload = json_encode([
    'model' => $MODEL, 'max_tokens' => 1500,
    'system' => "You translate short museum and archaeological-site descriptions from Turkish into {$LANGS[$lang]} for a cultural heritage website. Keep proper names in their usual {$LANGS[$lang]} form, keep all facts exactly as given, do not add anything. The text will be read aloud by a speech synthesizer, so write numbers and dates in a natural readable form. Output only the translation.",
    'messages' => [['role' => 'user', 'content' => $src]],
], JSON_UNESCAPED_UNICODE);
$ch = curl_init($CONFIG['API_URL'] ?? 'https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 45,
    CURLOPT_HTTPHEADER => ['content-type: application/json', 'x-api-key: ' . $API_KEY, 'anthropic-version: 2023-06-01'],
    CURLOPT_POSTFIELDS => $payload,
]);
$res = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($res === false || $code >= 400) { error_log('kv_ceviri API ' . $code . ' ' . substr((string)$res, 0, 300)); out(502, ['error' => 'upstream']); }
$j = json_decode($res, true);
$text = '';
foreach (($j['content'] ?? []) as $b) if (($b['type'] ?? '') === 'text') $text .= $b['text'];
$text = trim($text);
if ($text === '' || ($j['stop_reason'] ?? '') === 'max_tokens') out(502, ['error' => 'empty']);
@file_put_contents($file, $text, LOCK_EX);
out(200, ['text' => $text, 'cached' => false]);
