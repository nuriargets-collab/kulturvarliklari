<?php
/**
 * kulturvarliklari.com.tr — "Rehbere sor" yapay zeka uç noktası
 * Tarayıcı sorusunu alır, Claude API'ye sunucu tarafından iletir.
 * API anahtarı tarayıcıya asla gönderilmez.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function out(int $code, array $data): void { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }

// ---------- Ayarlar ----------
$CONFIG = [];
foreach ([dirname(__DIR__, 2) . '/kv_config.php', __DIR__ . '/config.php'] as $f) {
    if (is_file($f)) { $c = include $f; if (is_array($c)) { $CONFIG = $c; break; } }
}
$API_KEY   = getenv('ANTHROPIC_API_KEY') ?: ($CONFIG['ANTHROPIC_API_KEY'] ?? '');
$MODEL     = $CONFIG['MODEL']          ?? 'claude-haiku-5-5';
$PER_IP    = (int)($CONFIG['LIMIT_PER_IP_10MIN'] ?? 15);   // IP başına 10 dakikada en fazla soru
$PER_DAY   = (int)($CONFIG['LIMIT_PER_DAY'] ?? 2000);      // tüm site için günlük üst sınır
$MAX_TOK   = (int)($CONFIG['MAX_TOKENS'] ?? 700);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['error' => 'method']);
if ($API_KEY === '' || str_starts_with($API_KEY, 'BURAYA')) out(503, ['error' => 'not_configured']);

// Yalnızca kendi sitemizden gelen istekleri kabul et
$host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
$origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== $host) out(403, ['error' => 'origin']);

// ---------- Girdi ----------
$in = json_decode(file_get_contents('php://input') ?: '', true);
$q = trim((string)($in['question'] ?? ''));
if ($q === '' || mb_strlen($q) > 500) out(400, ['error' => 'question']);
$history = [];
foreach (array_slice((array)($in['history'] ?? []), -6) as $m) {
    $role = ($m['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
    $txt = mb_substr(trim((string)($m['content'] ?? '')), 0, 1500);
    if ($txt !== '') $history[] = ['role' => $role, 'content' => $txt];
}
// Mesajlar user ile başlamalı ve sırayla değişmeli
while ($history && $history[0]['role'] !== 'user') array_shift($history);
$msgs = [];
foreach ($history as $m) { if ($msgs && end($msgs)['role'] === $m['role']) { $msgs[count($msgs)-1]['content'] .= "\n" . $m['content']; } else { $msgs[] = $m; } }
if ($msgs && end($msgs)['role'] === 'user') array_pop($msgs);
$msgs[] = ['role' => 'user', 'content' => $q];

// ---------- Kötüye kullanım sınırı (dosya tabanlı) ----------
$dir = sys_get_temp_dir() . '/kv_rehber';
if (!is_dir($dir)) @mkdir($dir, 0700, true);
$ip = $_SERVER['REMOTE_ADDR'] ?? '0';
$now = time();
$ipFile = $dir . '/ip_' . hash('sha256', $ip . 'kv') . '.json';
$hits = is_file($ipFile) ? (json_decode((string)file_get_contents($ipFile), true) ?: []) : [];
$hits = array_values(array_filter($hits, fn($t) => $t > $now - 600));
if (count($hits) >= $PER_IP) out(429, ['error' => 'rate_ip']);
$hits[] = $now; @file_put_contents($ipFile, json_encode($hits), LOCK_EX);
$dayFile = $dir . '/day_' . date('Ymd') . '.txt';
$day = (int)@file_get_contents($dayFile);
if ($day >= $PER_DAY) out(429, ['error' => 'rate_day']);
@file_put_contents($dayFile, (string)($day + 1), LOCK_EX);

// ---------- Bilgi kaynağı ----------
$catalog = @file_get_contents(__DIR__ . '/catalog.txt') ?: '';
$system = [
  ['type' => 'text', 'text' =>
"Sen kulturvarliklari.com.tr sitesinin rehberisin. Türkiye'deki müzeler ve ören yerleri hakkında ziyaretçilerin sorularını yanıtlarsın.

Kurallar:
- Soruyu hangi dilde sorduysa o dilde yanıt ver.
- Kısa ve net ol: çoğu yanıt 2-5 cümle. Liste istenirse en fazla 8 madde.
- Önce aşağıdaki KATALOG'daki bilgilere dayan. Genel olarak bilinen, iyi yerleşmiş tarih bilgisini ekleyebilirsin; emin olmadığın tarih, sayı veya ayrıntıyı uydurma, bilmiyorsan söyle.
- Ziyaret saatleri, bilet ücretleri, ulaşım gibi değişebilen bilgileri verme; bunlar için ziyaretçiyi resmî kaynaklara yönlendir.
- Katalogdaki bir yerden söz ettiğinde adını [[KOD|Ad]] biçiminde yaz (örnek: [[EFS01|Efes]]). Katalogda olmayan yer için bu biçimi kullanma.
- Konu Türkiye'nin kültür mirası, müzeleri, antik kentleri ve tarihiyle ilgili değilse kibarca bu konularda yardımcı olabileceğini söyle.
- Markdown başlık veya tablo kullanma; düz metin yaz.

KATALOG (KOD | Ad | İl | Bölge | Tür | Dönem | UNESCO | Açıklama):
" . $catalog,
   'cache_control' => ['type' => 'ephemeral']]
];

// ---------- Claude API çağrısı ----------
$payload = json_encode(['model' => $MODEL, 'max_tokens' => $MAX_TOK, 'system' => $system, 'messages' => $msgs], JSON_UNESCAPED_UNICODE);
$ch = curl_init($CONFIG['API_URL'] ?? 'https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 40,
    CURLOPT_HTTPHEADER => ['content-type: application/json', 'x-api-key: ' . $API_KEY, 'anthropic-version: 2023-06-01'],
    CURLOPT_POSTFIELDS => $payload,
]);
$res = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($res === false || $code >= 400) { error_log('kv_rehber API ' . $code . ' ' . substr((string)$res, 0, 300)); out(502, ['error' => 'upstream']); }
$j = json_decode($res, true);
$text = '';
foreach (($j['content'] ?? []) as $b) if (($b['type'] ?? '') === 'text') $text .= $b['text'];
if ($text === '') out(502, ['error' => 'empty']);
out(200, ['answer' => $text]);
