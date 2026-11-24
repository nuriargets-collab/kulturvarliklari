<?php
/**
 * kulturvarliklari.com.tr — e-posta bülteni aboneliği (çift onaylı)
 *
 *  POST {e: e-posta, l: dil, k: 1 (KVKK onayı)}  → onay e-postası gönderir
 *  GET  ?onay=JETON                              → aboneliği etkinleştirir
 *  GET  ?cik=JETON                               → abonelikten çıkarır
 *  GET  ?liste=PAROLA                            → etkin aboneleri CSV olarak verir (parola: kv_config.php > SAYAC_RAPOR_ANAHTARI)
 *  Veriler web kökünün dışında, kv_cache/bulten/aboneler.json dosyasında tutulur.
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
$DIR = $cacheRoot . '/bulten';
if (!is_dir($DIR)) @mkdir($DIR, 0755, true);
$DB = $DIR . '/aboneler.json';
$SITE = 'https://kulturvarliklari.com.tr';
$FROM = (string)($CONFIG['BULTEN_GONDEREN'] ?? 'bulten@kulturvarliklari.com.tr');

function load_db(): array { global $DB; $d = json_decode((string)@file_get_contents($DB), true); return is_array($d) ? $d : []; }
function save_db(array $d): void { global $DB; @file_put_contents($DB, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX); }
function page(string $title, string $msg): void {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . htmlspecialchars($title) . '</title>'
       . '<body style="font:17px/1.5 system-ui,sans-serif;background:#f4ede1;color:#16120f;padding:48px 20px;text-align:center">'
       . '<h1 style="font-size:24px">' . htmlspecialchars($title) . '</h1><p>' . $msg . '</p><p><a href="/" style="color:#b5532f">kulturvarliklari.com.tr</a></p></body>';
    exit;
}

$T = [
 'tr' => ['Bültene kaydınızı onaylayın', "Kültür Varlıkları bültenine kaydolmak için aşağıdaki bağlantıya tıklayın:\n\n%s\n\nBu isteği siz yapmadıysanız bu e-postayı yok sayın; kaydınız oluşturulmaz.", 'Aboneliğiniz onaylandı', 'Teşekkürler! Haftalık "Kazılardan haberler" bültenimiz artık e-postanıza gelecek.', 'Abonelikten çıktınız', 'E-posta adresiniz listemizden silindi.', 'Bağlantı geçersiz', 'Bu bağlantı geçersiz ya da süresi dolmuş.'],
 'en' => ['Confirm your newsletter subscription', "To subscribe to the Kültür Varlıkları newsletter, click the link below:\n\n%s\n\nIf you did not request this, ignore this e-mail; no subscription will be created.", 'Subscription confirmed', 'Thank you! Our weekly "News from the digs" will now reach your inbox.', 'Unsubscribed', 'Your e-mail address has been removed from our list.', 'Invalid link', 'This link is invalid or has expired.'],
];
function tx(string $l, int $i): string { global $T; return ($T[$l] ?? $T['en'])[$i]; }

/* ---------- abone listesi (site sahibi) ---------- */
if (isset($_GET['liste'])) {
    $pw = (string)($CONFIG['SAYAC_RAPOR_ANAHTARI'] ?? '');
    if ($pw === '' || !hash_equals($pw, (string)$_GET['liste'])) { http_response_code(403); page('Yetkisiz', 'Parola gerekli.'); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bulten-aboneler.csv"');
    echo "eposta,dil,tarih\n";
    foreach (load_db() as $r) if (($r['d'] ?? '') === 'etkin') echo $r['e'] . ',' . $r['l'] . ',' . $r['t'] . "\n";
    exit;
}

/* ---------- onay / çıkış ---------- */
foreach (['onay', 'cik'] as $act) {
    if (!isset($_GET[$act])) continue;
    $tok = preg_replace('/[^a-f0-9]/', '', (string)$_GET[$act]);
    $db = load_db(); $found = null;
    foreach ($db as $i => $r) if (hash_equals((string)($r[$act === 'onay' ? 'on' : 'ck'] ?? ''), $tok) && $tok !== '') { $found = $i; break; }
    $l = $found !== null ? (string)$db[$found]['l'] : 'tr';
    if ($found === null) page(tx($l, 6), tx($l, 7));
    if ($act === 'onay') { $db[$found]['d'] = 'etkin'; $db[$found]['ot'] = date('c'); save_db($db); page(tx($l, 2), tx($l, 3)); }
    unset($db[$found]); save_db(array_values($db)); page(tx($l, 4), tx($l, 5));
}

/* ---------- kayıt isteği ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Content-Type: application/json'); echo '{"error":"method"}'; exit; }
header('Content-Type: application/json; charset=utf-8');
$host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
$origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
if ($origin === '' || parse_url($origin, PHP_URL_HOST) !== $host) { http_response_code(403); echo '{"error":"origin"}'; exit; }
$in = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
$email = strtolower(trim((string)($in['e'] ?? '')));
$lang = preg_replace('/[^a-z]/', '', (string)($in['l'] ?? 'tr'));
if (empty($in['k'])) { http_response_code(400); echo '{"error":"onay"}'; exit; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) { http_response_code(400); echo '{"error":"eposta"}'; exit; }

/* kötüye kullanım sınırı: IP başına günde 5 istek (IP özetlenerek tutulur) */
$ipf = $DIR . '/ip-' . date('Ymd') . '.json';
$ips = json_decode((string)@file_get_contents($ipf), true) ?: [];
$h = substr(hash('sha256', kv_client_ip() . date('Ymd')), 0, 16);
if (($ips[$h] ?? 0) >= 5) { http_response_code(429); echo '{"error":"sinir"}'; exit; }
$ips[$h] = ($ips[$h] ?? 0) + 1; @file_put_contents($ipf, json_encode($ips), LOCK_EX);
foreach (glob($DIR . '/ip-*.json') ?: [] as $old) if ($old !== $ipf) @unlink($old);

$db = load_db();
foreach ($db as $r) if ($r['e'] === $email && ($r['d'] ?? '') === 'etkin') { echo '{"ok":1}'; exit; }
$db = array_values(array_filter($db, function ($r) use ($email) { return $r['e'] !== $email; }));
$on = bin2hex(random_bytes(16)); $ck = bin2hex(random_bytes(16));
$db[] = ['e' => $email, 'l' => $lang, 't' => date('c'), 'd' => 'bekliyor', 'on' => $on, 'ck' => $ck];
save_db($db);
$link = $SITE . '/api/bulten.php?onay=' . $on;
$sub = '=?UTF-8?B?' . base64_encode(tx($lang, 0)) . '?=';
$headers = "From: =?UTF-8?B?" . base64_encode('Kültür Varlıkları') . "?= <$FROM>\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\nList-Unsubscribe: <$SITE/api/bulten.php?cik=$ck>";
$ok = @mail($email, $sub, sprintf(tx($lang, 1), $link), $headers, '-f' . $FROM);
echo $ok ? '{"ok":1}' : '{"error":"posta"}';
