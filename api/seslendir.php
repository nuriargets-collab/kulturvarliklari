<?php
/**
 * kulturvarliklari.com.tr — gerçek sesli anlatım (MP3)
 *
 *  GET ?sid=EFS01&lang=tr        → {"url": "ses/tr/EFS01-1a2b3c4d.mp3"}
 *      Dosya yoksa bir kez üretilir, /ses/<dil>/<SID>-<metin özeti>.mp3 olarak kaydedilir ve
 *      bundan sonra sunucu (ve Cloudflare) onu sıradan bir dosya gibi verir. Metin düzeltilirse
 *      özet değişir ve yeni ses kendiliğinden üretilir.
 *  GET ?toplu=PAROLA[&lang=tr]   → eksik dosyaları ~25 saniye boyunca üretir, kalan sayısını yazar.
 *      Sayfayı "kalan: 0" görünene kadar yenileyin. Bütün site bir kerelik iştir.
 *
 *  Sağlayıcı ve anahtar kv_config.php içindedir (asla depoya girmez):
 *      'TTS_PROVIDER' => 'google' | 'elevenlabs'
 *      'GOOGLE_TTS_KEY' / 'ELEVENLABS_KEY'
 */
declare(strict_types=1);
require_once __DIR__ . '/_ip.php';
header('X-Content-Type-Options: nosniff');
@set_time_limit(60);

function out(int $code, array $data): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- ayarlar ----------
$CONFIG = [];
foreach ([dirname(__DIR__, 2) . '/kv_config.php', __DIR__ . '/config.php'] as $f) {
    if (is_file($f)) { $c = include $f; if (is_array($c)) { $CONFIG = $c; break; } }
}
$PROVIDER = strtolower((string)($CONFIG['TTS_PROVIDER'] ?? 'google'));
$GKEY = (string)(getenv('GOOGLE_TTS_KEY') ?: ($CONFIG['GOOGLE_TTS_KEY'] ?? ''));
$EKEY = (string)(getenv('ELEVENLABS_KEY') ?: ($CONFIG['ELEVENLABS_KEY'] ?? ''));
$PER_DAY = (int)($CONFIG['TTS_PER_DAY'] ?? 300);          // günde en fazla yeni dosya (bütçe koruması)
$ADMIN = (string)($CONFIG['TTS_TOPLU_PAROLA'] ?? '');

// Google Chirp 3 HD sesleri; ElevenLabs için ses kimliği ayarlardan değiştirilebilir
$GVOICE = $CONFIG['GOOGLE_TTS_VOICE'] ?? 'Charon';
$LANGS = [
    'tr' => 'tr-TR', 'en' => 'en-US', 'de' => 'de-DE', 'zh' => 'cmn-CN', 'hi' => 'hi-IN',
    'es' => 'es-ES', 'ar' => 'ar-XA', 'ru' => 'ru-RU', 'fr' => 'fr-FR', 'it' => 'it-IT', 'ja' => 'ja-JP',
];
$EVOICE = (string)($CONFIG['ELEVENLABS_VOICE_ID'] ?? '');
$EMODEL = (string)($CONFIG['ELEVENLABS_MODEL'] ?? 'eleven_multilingual_v2');

$texts = json_decode((string)@file_get_contents(__DIR__ . '/texts.json'), true) ?: [];
$sesRoot = dirname(__DIR__) . '/ses';
$cacheRoot = dirname(__DIR__, 2) . '/kv_cache';
if (!is_dir($cacheRoot)) $cacheRoot = __DIR__ . '/cache';

function configured(): bool {
    global $PROVIDER, $GKEY, $EKEY, $EVOICE;
    if ($PROVIDER === 'elevenlabs') return $EKEY !== '' && $EVOICE !== '' && !(strncmp($EKEY, 'BURAYA', 6) === 0);
    return $GKEY !== '' && !(strncmp($GKEY, 'BURAYA', 6) === 0);
}

/** Metin: tr/en/de sitenin kendi metni; ru/fr/es/ar daha önce yapılmış ve saklanmış çeviri. */
function text_for(string $sid, string $lang): ?string {
    global $texts, $cacheRoot;
    if (!isset($texts[$sid])) return null;
    if (isset($texts[$sid][$lang]) && $texts[$sid][$lang] !== '') return $texts[$sid][$lang];
    $f = $cacheRoot . '/' . $lang . '/' . $sid . '.txt';
    return is_file($f) ? (string)file_get_contents($f) : null;
}

/** Cümle sınırlarından, bayt sınırını aşmayan parçalara böl. */
function chunks(string $t, int $maxBytes): array {
    $parts = preg_split('/(?<=[.!?;؟。！？।])\s*/u', trim($t), -1, PREG_SPLIT_NO_EMPTY) ?: [$t];
    $out = []; $cur = '';
    foreach ($parts as $p) {
        if ($cur !== '' && strlen($cur) + 1 + strlen($p) > $maxBytes) { $out[] = $cur; $cur = $p; }
        else $cur = $cur === '' ? $p : $cur . ' ' . $p;
    }
    if ($cur !== '') $out[] = $cur;
    return $out;
}

function http_post(string $url, array $headers, string $body): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 50, CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, is_string($res) ? $res : ''];
}

function synth(string $text, string $lang): ?string {
    global $PROVIDER, $GKEY, $EKEY, $GVOICE, $LANGS, $EVOICE, $EMODEL;
    $mp3 = '';
    if ($PROVIDER === 'elevenlabs') {
        foreach (chunks($text, 9000) as $c) {
            [$code, $res] = http_post(
                'https://api.elevenlabs.io/v1/text-to-speech/' . rawurlencode($EVOICE) . '?output_format=mp3_44100_128',
                ['Content-Type: application/json', 'Accept: audio/mpeg', 'xi-api-key: ' . $EKEY],
                json_encode(['text' => $c, 'model_id' => $EMODEL, 'language_code' => $lang], JSON_UNESCAPED_UNICODE)
            );
            if ($code !== 200 || $res === '') return null;
            $mp3 .= $res;
        }
        return $mp3;
    }
    $lc = $LANGS[$lang];
    foreach (chunks($text, $lang === 'hi' || $lang === 'zh' ? 1500 : 3800) as $c) {
        [$code, $res] = http_post(
            'https://texttospeech.googleapis.com/v1/text:synthesize',
            ['Content-Type: application/json; charset=utf-8', 'X-Goog-Api-Key: ' . $GKEY],
            json_encode([
                'input' => ['text' => $c],
                'voice' => ['languageCode' => $lc, 'name' => $lc . '-Chirp3-HD-' . $GVOICE],
                'audioConfig' => ['audioEncoding' => 'MP3', 'speakingRate' => 0.97],
            ], JSON_UNESCAPED_UNICODE)
        );
        if ($code !== 200) return null;
        $j = json_decode($res, true);
        $bin = base64_decode((string)($j['audioContent'] ?? ''), true);
        if (!$bin) return null;
        $mp3 .= $bin;
    }
    return $mp3;
}

function day_ok(): bool {
    global $PER_DAY;
    $tmp = sys_get_temp_dir() . '/kv_rehber';
    if (!is_dir($tmp)) @mkdir($tmp, 0700, true);
    $f = $tmp . '/ttsday_' . date('Ymd') . '.txt';
    $n = (int)@file_get_contents($f);
    if ($n >= $PER_DAY) return false;
    @file_put_contents($f, (string)($n + 1), LOCK_EX);
    return true;
}

function name_for(string $sid, string $lang): ?string {
    $text = text_for($sid, $lang);
    return $text === null ? null : $sid . '-' . substr(sha1($text), 0, 8) . '.mp3';
}

function make(string $sid, string $lang): string {
    global $sesRoot;
    $text = text_for($sid, $lang);
    if ($text === null) throw new RuntimeException('text');
    $name = $sid . '-' . substr(sha1($text), 0, 8) . '.mp3';
    $rel = 'ses/' . $lang . '/' . $name;
    $file = $sesRoot . '/' . $lang . '/' . $name;
    if (is_file($file) && filesize($file) > 1000) return $rel;
    if (!is_dir(dirname($file)) && !@mkdir(dirname($file), 0755, true)) throw new RuntimeException('dir');
    $lock = fopen($file . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('busy');
    try {
        if (is_file($file) && filesize($file) > 1000) return $rel;
        if (!day_ok()) throw new RuntimeException('rate_day');
        $mp3 = synth($text, $lang);
        if (!$mp3) throw new RuntimeException('provider');
        $tmpf = $file . '.part';
        file_put_contents($tmpf, $mp3);
        rename($tmpf, $file);
        foreach (glob(dirname($file) . '/' . $sid . '-*.mp3') ?: [] as $old) if ($old !== $file) @unlink($old);
        return $rel;
    } finally { flock($lock, LOCK_UN); fclose($lock); @unlink($file . '.lock'); }
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') out(405, ['error' => 'method']);

// ---------- toplu üretim (yalnızca site sahibi) ----------
if (isset($_GET['toplu'])) {
    if ($ADMIN === '' || !hash_equals($ADMIN, (string)$_GET['toplu'])) out(403, ['error' => 'parola']);
    if (!configured()) out(503, ['error' => 'not_configured']);
    if (isset($_GET['oto'])) {   // kendi kendine devam eden ilerleme sayfası
        header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store'); header('X-Robots-Tag: noindex');
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Ses üretimi</title>'
           . '<body style="font:16px system-ui;max-width:560px;margin:40px auto;padding:0 16px"><h2>Sesli anlatım üretiliyor</h2>'
           . '<p id="d">Başlıyor…</p><p style="color:#777">Bu sekmeyi kapatmayın; iş bitince “Tamamlandı” yazar.</p><pre id="e" style="color:#b00;white-space:pre-wrap"></pre>'
           . '<script>var u=location.href.replace(/[&?]oto=1/,""),top=0;function go(){fetch(u,{cache:"no-store"}).then(function(r){return r.json()}).then(function(j){top+=j.uretilen||0;'
           . 'document.getElementById("d").textContent="Üretilen: "+top+" · Kalan: "+j.kalan;if(j.hatalar&&j.hatalar.length)document.getElementById("e").textContent=j.hatalar.join("\\n");'
           . 'if(j.kalan>0&&!(j.hatalar&&j.hatalar.length>5)&&(j.uretilen>0||!j.hatalar.length))setTimeout(go,1000);else document.getElementById("d").textContent+=(j.kalan?" · Durdu (hatalara bakın)":" · Tamamlandı ✔")})'
           . '.catch(function(){setTimeout(go,5000)})}go();</script>';
        exit;
    }
    $only = (string)($_GET['lang'] ?? '');
    $langs = $only !== '' && isset($LANGS[$only]) ? [$only] : array_values(array_filter(['tr', 'en', 'de', 'zh', 'hi', 'es', 'ar'], fn($l) => isset(reset($texts)[$l])));
    $t0 = microtime(true); $made = 0; $errors = []; $left = 0;
    foreach ($langs as $l) foreach (array_keys($texts) as $sid) {
        $n = name_for($sid, $l);
        if ($n === null || is_file("$sesRoot/$l/$n")) continue;
        if (microtime(true) - $t0 > 25) { $left++; continue; }
        try { make($sid, $l); $made++; } catch (Throwable $e) { $errors[] = "$l/$sid: " . $e->getMessage(); $left++; if (count($errors) > 5) break 2; }
    }
    out(200, ['uretilen' => $made, 'kalan' => $left, 'hatalar' => $errors]);
}

// ---------- ziyaretçi isteği ----------
$sid = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_GET['sid'] ?? '')));
$lang = (string)($_GET['lang'] ?? '');
if (!isset($LANGS[$lang]) || strlen($sid) < 4 || strlen($sid) > 6) out(400, ['error' => 'param']);
$n = name_for($sid, $lang);
if ($n === null) out(404, ['error' => 'text']);
if (is_file("$sesRoot/$lang/$n")) out(200, ['url' => "ses/$lang/$n"]);

$host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
$origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
if ($origin === '' || parse_url($origin, PHP_URL_HOST) !== $host) out(403, ['error' => 'origin']);
if (!configured()) out(503, ['error' => 'not_configured']);

// ziyaretçi başına sınır: 10 dakikada en fazla 6 yeni dosya
$tmp = sys_get_temp_dir() . '/kv_rehber';
if (!is_dir($tmp)) @mkdir($tmp, 0700, true);
$ipFile = $tmp . '/tts_' . hash('sha256', (kv_client_ip()) . 'kv') . '.json';
$hits = is_file($ipFile) ? (json_decode((string)file_get_contents($ipFile), true) ?: []) : [];
$hits = array_values(array_filter($hits, fn($t) => $t > time() - 600));
if (count($hits) >= 6) out(429, ['error' => 'rate_ip']);
$hits[] = time(); @file_put_contents($ipFile, json_encode($hits), LOCK_EX);

try { out(200, ['url' => make($sid, $lang)]); }
catch (Throwable $e) {
    $m = $e->getMessage();
    out($m === 'text' ? 404 : ($m === 'rate_day' ? 429 : ($m === 'busy' ? 409 : 502)), ['error' => $m]);
}
