<?php
/**
 * kulturvarliklari.com.tr — Turlar ve Rehberler ilanları
 *
 *  GET  ?liste=1                    → onaylı ilanlar (herkese açık alanlar, JSON)
 *  POST {a:"ilan", ...}             → yeni ilan (onay bekler; koşul onayı zorunlu)
 *  POST {a:"talep", id, ...}        → ziyaretçi talebini ilan verene e-postayla iletir
 *  GET  ?yonet=PAROLA[&onayla=ID|&gizle=ID|&sil=ID]  → yönetim (parola: kv_config.php > SAYAC_RAPOR_ANAHTARI)
 *  Veriler web kökünün dışında, kv_cache/turlar/ altında tutulur.
 */
declare(strict_types=1);
require_once __DIR__ . '/_ip.php';
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
date_default_timezone_set('Europe/Istanbul');

const KOSUL_SURUM = '2026-11-25';
$CONFIG = [];
foreach ([dirname(__DIR__, 2) . '/kv_config.php', __DIR__ . '/config.php'] as $f) {
    if (is_file($f)) { $c = include $f; if (is_array($c)) { $CONFIG = $c; break; } }
}
$cacheRoot = dirname(__DIR__, 2) . '/kv_cache';
if (!is_dir($cacheRoot) && !@mkdir($cacheRoot, 0755, true)) $cacheRoot = __DIR__ . '/cache';
$DIR = $cacheRoot . '/turlar';
if (!is_dir($DIR)) @mkdir($DIR, 0755, true);
$DB = $DIR . '/ilanlar.json';
$SITE = 'https://kulturvarliklari.com.tr';
$FROM = (string)($CONFIG['ILAN_GONDEREN'] ?? ($CONFIG['BULTEN_GONDEREN'] ?? 'bulten@kulturvarliklari.com.tr'));
$ADMIN = (string)($CONFIG['YONETICI_EPOSTA'] ?? '');

function load_db(): array { global $DB; $d = json_decode((string)@file_get_contents($DB), true); return is_array($d) ? $d : []; }
function save_db(array $d): void { global $DB; @file_put_contents($DB, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX); }
function out(int $code, array $d): void { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function s($v, int $max): string { $v = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$v))); return mb_substr($v, 0, $max); }
function ml($v, int $max): string { $v = trim(strip_tags(str_replace("\r", '', (string)$v))); $v = preg_replace("/\n{3,}/", "\n\n", $v); return mb_substr($v, 0, $max); }
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function send_mail(string $to, string $subj, string $body, string $replyTo = ''): bool {
    global $FROM;
    $hd = "From: =?UTF-8?B?" . base64_encode('Kültür Varlıkları') . "?= <$FROM>\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit";
    if ($replyTo !== '') $hd .= "\r\nReply-To: $replyTo";
    return @mail($to, '=?UTF-8?B?' . base64_encode($subj) . '?=', $body, $hd, '-f' . $FROM);
}
function rate(string $kind, int $max): void {
    global $DIR;
    $f = $DIR . '/ip-' . $kind . '-' . date('Ymd') . '.json';
    $ips = json_decode((string)@file_get_contents($f), true) ?: [];
    $k = substr(hash('sha256', kv_client_ip() . date('Ymd')), 0, 16);
    if (($ips[$k] ?? 0) >= $max) out(429, ['error' => 'sinir']);
    $ips[$k] = ($ips[$k] ?? 0) + 1; @file_put_contents($f, json_encode($ips), LOCK_EX);
    foreach (glob($DIR . '/ip-' . $kind . '-*.json') ?: [] as $o) if ($o !== $f) @unlink($o);
}
function is_live(array $r): bool {
    if (($r['d'] ?? '') !== 'onayli') return false;
    $end = (string)($r['bitis'] ?? '');
    return $end === '' || $end >= date('Y-m-d');
}

/* ---------- herkese açık liste ---------- */
if (isset($_GET['liste'])) {
    $pub = [];
    foreach (load_db() as $r) if (is_live($r)) {
        $pub[] = ['id' => $r['id'], 'tur' => $r['tur'], 'kat' => $r['kat'], 'baslik' => $r['baslik'], 'ozet' => $r['ozet'], 'aciklama' => $r['aciklama'],
            'il' => $r['il'], 'yer' => $r['yer'], 'tarih' => $r['tarih'], 'bitis' => $r['bitis'], 'fiyat' => $r['fiyat'], 'sure' => $r['sure'],
            'firma' => $r['firma'], 'belge' => $r['belge'], 'web' => $r['web'], 'tel' => $r['tel_goster'] ? $r['tel'] : '', 'ot' => $r['ot'] ?? $r['t']];
    }
    usort($pub, function ($a, $b) { return strcmp((string)$b['ot'], (string)$a['ot']); });
    header('Cache-Control: public, max-age=300');
    out(200, ['ilanlar' => $pub]);
}

/* ---------- yönetim ---------- */
if (isset($_GET['yonet'])) {
    $pw = (string)($CONFIG['SAYAC_RAPOR_ANAHTARI'] ?? '');
    if ($pw === '' || !hash_equals($pw, (string)$_GET['yonet'])) { http_response_code(403); exit('Yetkisiz'); }
    $db = load_db(); $msg = '';
    foreach (['onayla' => 'onayli', 'gizle' => 'gizli', 'sil' => null] as $act => $state) {
        if (!isset($_GET[$act])) continue;
        $id = preg_replace('/[^a-f0-9]/', '', (string)$_GET[$act]);
        foreach ($db as $i => $r) if ($r['id'] === $id) {
            if ($state === null) { unset($db[$i]); $msg = 'Silindi.'; }
            else {
                $db[$i]['d'] = $state; if ($state === 'onayli') $db[$i]['ot'] = date('c'); $msg = $state === 'onayli' ? 'Onaylandı.' : 'Gizlendi.';
                if ($state === 'onayli' && empty($r['bildirildi'])) {
                    send_mail($r['eposta'], 'İlanınız yayında: ' . $r['baslik'], "Merhaba,\n\n\"{$r['baslik']}\" ilanınız Kültür Varlıkları'nda yayınlandı:\n$SITE/turlar.html#t-{$r['id']}\n\nZiyaretçi talepleri bu e-posta adresine iletilecektir.\n\nİlanı kaldırmak veya değiştirmek için bu e-postayı yanıtlayabilirsiniz.");
                    $db[$i]['bildirildi'] = 1;
                }
            }
        }
        $db = array_values($db); save_db($db);
    }
    header('Content-Type: text/html; charset=utf-8');
    $k = rawurlencode((string)$_GET['yonet']);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>İlan yönetimi</title><body style="font:15px/1.5 system-ui,sans-serif;max-width:980px;margin:24px auto;padding:0 14px">';
    echo '<h1>Tur ilanları</h1>' . ($msg ? '<p style="background:#e7f5e7;padding:8px">' . h($msg) . '</p>' : '');
    usort($db, function ($a, $b) { return [$a['d'] !== 'bekliyor', $b['t']] <=> [$b['d'] !== 'bekliyor', $a['t']]; });
    foreach ($db as $r) {
        $col = ['bekliyor' => '#fff4d6', 'onayli' => '#e7f5e7', 'gizli' => '#eee'][$r['d']] ?? '#fff';
        echo '<div style="border:1px solid #ccc;border-radius:8px;padding:12px;margin:12px 0;background:' . $col . '"><b>' . h($r['baslik']) . '</b> — ' . h($r['d']) . ' · ' . h($r['t']) . '<br>'
           . h($r['tur']) . ' · ' . h($r['kat']) . ' · ' . h($r['il']) . ' · ' . h($r['tarih']) . ($r['bitis'] ? ' → ' . h($r['bitis']) : '') . ' · ' . h($r['fiyat']) . '<br>'
           . '<b>' . h($r['firma']) . '</b> · Belge: <b>' . h($r['belge']) . '</b> · ' . h($r['yetkili']) . ' · ' . h($r['eposta']) . ' · ' . h($r['tel']) . ' · ' . h($r['web']) . '<br>'
           . '<i>' . nl2br(h($r['aciklama'])) . '</i><br><small>Koşul onayı: sürüm ' . h($r['kosul']['v'] ?? '') . ', ' . h($r['kosul']['t'] ?? '') . '</small><br>'
           . '<a href="?yonet=' . $k . '&onayla=' . $r['id'] . '">✅ Onayla</a> · <a href="?yonet=' . $k . '&gizle=' . $r['id'] . '">🙈 Gizle</a> · <a href="?yonet=' . $k . '&sil=' . $r['id'] . '" onclick="return confirm(\'Kalıcı olarak silinsin mi?\')">🗑 Sil</a>'
           . ' · Talep sayısı: ' . (int)($r['talep'] ?? 0) . '</div>';
    }
    if (!$db) echo '<p>Henüz ilan yok.</p>';
    echo '<p style="color:#666">Belge kontrolü: acenteler için <a href="https://www.tursab.org.tr" target="_blank" rel="noopener">TÜRSAB acente arama</a>.</p></body>';
    exit;
}

/* ---------- POST işlemleri ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['error' => 'method']);
$host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
$origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
if ($origin === '' || parse_url($origin, PHP_URL_HOST) !== $host) out(403, ['error' => 'origin']);
$in = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
if (!empty($in['web2'])) out(200, ['ok' => 1]); // bal tuzağı (bot)

if (($in['a'] ?? '') === 'ilan') {
    if (empty($in['kosul']) || ($in['kosul_v'] ?? '') !== KOSUL_SURUM) out(400, ['error' => 'kosul']);
    if (empty($in['beyan']) || empty($in['kvkk'])) out(400, ['error' => 'onay']);
    $tur = in_array($in['tur'] ?? '', ['acente', 'rehber', 'dernek'], true) ? $in['tur'] : '';
    $r = ['id' => bin2hex(random_bytes(6)), 't' => date('c'), 'd' => 'bekliyor', 'tur' => $tur,
        'kat' => s($in['kat'] ?? '', 40), 'baslik' => s($in['baslik'] ?? '', 120), 'ozet' => s($in['ozet'] ?? '', 200), 'aciklama' => ml($in['aciklama'] ?? '', 3000),
        'il' => s($in['il'] ?? '', 40), 'yer' => s($in['yer'] ?? '', 120), 'tarih' => s($in['tarih'] ?? '', 80), 'bitis' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($in['bitis'] ?? '')) ? $in['bitis'] : '',
        'fiyat' => s($in['fiyat'] ?? '', 60), 'sure' => s($in['sure'] ?? '', 40), 'firma' => s($in['firma'] ?? '', 120), 'belge' => s($in['belge'] ?? '', 60),
        'yetkili' => s($in['yetkili'] ?? '', 80), 'eposta' => strtolower(s($in['eposta'] ?? '', 120)), 'tel' => s($in['tel'] ?? '', 30), 'tel_goster' => !empty($in['tel_goster']),
        'web' => filter_var((string)($in['web'] ?? ''), FILTER_VALIDATE_URL) && preg_match('#^https?://#', (string)$in['web']) ? s($in['web'], 200) : '',
        'kosul' => ['v' => KOSUL_SURUM, 't' => date('c'), 'ip' => substr(hash('sha256', kv_client_ip()), 0, 16)], 'talep' => 0];
    if ($tur === '' || mb_strlen($r['baslik']) < 5 || mb_strlen($r['aciklama']) < 30 || $r['firma'] === '' || $r['belge'] === '' || $r['il'] === '') out(400, ['error' => 'eksik']);
    if (!filter_var($r['eposta'], FILTER_VALIDATE_EMAIL)) out(400, ['error' => 'eposta']);
    rate('ilan', 5);
    $db = load_db();
    if (count(array_filter($db, function ($x) { return $x['d'] === 'bekliyor'; })) > 300) out(503, ['error' => 'dolu']);
    $db[] = $r; save_db($db);
    send_mail($r['eposta'], 'İlanınız alındı: ' . $r['baslik'], "Merhaba {$r['yetkili']},\n\n\"{$r['baslik']}\" ilanınız Kültür Varlıkları'na ulaştı. İnceleme sonrası yayınlandığında size bilgi vereceğiz.\n\nKabul ettiğiniz ilan koşulları (sürüm " . KOSUL_SURUM . "): $SITE/ilan-kosullari.html\n\nBu başvuruyu siz yapmadıysanız bu e-postayı yanıtlayarak bildirin.");
    if ($ADMIN !== '') send_mail($ADMIN, 'Yeni tur ilanı: ' . $r['baslik'], "Yeni ilan onay bekliyor.\nFirma: {$r['firma']}\nBelge: {$r['belge']}\nE-posta: {$r['eposta']}\n\nYönetim: $SITE/api/turlar.php?yonet=PAROLANIZ");
    out(200, ['ok' => 1]);
}

if (($in['a'] ?? '') === 'talep') {
    if (empty($in['kvkk'])) out(400, ['error' => 'onay']);
    $id = preg_replace('/[^a-f0-9]/', '', (string)($in['id'] ?? ''));
    $ad = s($in['ad'] ?? '', 80); $ep = strtolower(s($in['eposta'] ?? '', 120)); $tel = s($in['tel'] ?? '', 30);
    $kisi = max(1, min(99, (int)($in['kisi'] ?? 1))); $tarih = s($in['tarih'] ?? '', 40); $mesaj = ml($in['mesaj'] ?? '', 1500);
    if ($ad === '' || !filter_var($ep, FILTER_VALIDATE_EMAIL)) out(400, ['error' => 'eksik']);
    $db = load_db(); $ix = null;
    foreach ($db as $i => $r) if ($r['id'] === $id && is_live($r)) { $ix = $i; break; }
    if ($ix === null) out(404, ['error' => 'ilan']);
    rate('talep', 8);
    $r = $db[$ix];
    $body = "Merhaba,\n\nKültür Varlıkları'ndaki \"{$r['baslik']}\" ilanınız için yeni bir talep var:\n\nAd: $ad\nE-posta: $ep\nTelefon: $tel\nKişi sayısı: $kisi\nTercih edilen tarih: $tarih\n\nMesaj:\n$mesaj\n\n"
          . "Bu e-postayı yanıtlayarak doğrudan katılımcıya ulaşabilirsiniz.\nHatırlatma: Bu kişisel verileri yalnızca bu talebe yanıt vermek için kullanabilirsiniz (KVKK). Kültür Varlıkları sözleşmenin tarafı değildir.";
    if (!send_mail($r['eposta'], 'Yeni talep: ' . $r['baslik'], $body, $ep)) out(500, ['error' => 'posta']);
    send_mail($ep, 'Talebiniz iletildi: ' . $r['baslik'], "Merhaba $ad,\n\nTalebiniz \"{$r['firma']}\" firmasına iletildi. Yanıt doğrudan bu firmadan gelecektir.\n\nHatırlatma: Kültür Varlıkları bir seyahat acentası değildir; tur, ödeme ve iptal konularında doğrudan ilan verenle anlaşırsınız. Katılmadan önce belge numarasını (TÜRSAB / rehber kartı) teyit etmenizi öneririz.");
    $db[$ix]['talep'] = (int)($r['talep'] ?? 0) + 1; save_db($db);
    out(200, ['ok' => 1]);
}
out(400, ['error' => 'islem']);
