<?php
/**
 * Reklam tıklama/görüntülenme sayacı.
 *  - Tıklama:  reklam.php?id=ID&t=web|tel|yol&sid=EFS01&u=HEDEF  → sayar ve hedefe yönlendirir
 *  - Görüntülenme (sendBeacon): reklam.php?gor=ID1,ID2&sid=EFS01
 *  - Rapor:    reklam.php?rapor=ANAHTAR  (anahtar kv_config.php içindeki REKLAM_RAPOR_ANAHTARI)
 * Kişisel veri tutulmaz; yalnızca reklam kimliği, gün ve sayılar saklanır.
 */
declare(strict_types=1);
$CONFIG = [];
foreach ([dirname(__DIR__, 2) . '/kv_config.php', __DIR__ . '/config.php'] as $f) {
    if (is_file($f)) { $c = include $f; if (is_array($c)) { $CONFIG = $c; break; } }
}
$root = dirname(__DIR__, 2) . '/kv_cache';
if (!is_dir($root) && !@mkdir($root, 0755, true)) $root = __DIR__ . '/cache';
if (!is_dir($root)) @mkdir($root, 0755, true);
$statFile = $root . '/reklam_istatistik.json';
$ads = json_decode((string)@file_get_contents(dirname(__DIR__) . '/reklamlar.json'), true) ?: [];
$byId = []; foreach ($ads as $a) if (isset($a['id'])) $byId[(string)$a['id']] = $a;

function bump(string $file, array $keys): void {
    $fp = @fopen($file, 'c+'); if (!$fp) return;
    flock($fp, LOCK_EX);
    $d = json_decode((string)stream_get_contents($fp), true) ?: [];
    foreach ($keys as $k) { [$id, $kind] = $k; $m = date('Y-m'); $d[$id][$m][$kind] = ($d[$id][$m][$kind] ?? 0) + 1; }
    ftruncate($fp, 0); rewind($fp); fwrite($fp, json_encode($d, JSON_UNESCAPED_UNICODE)); fflush($fp); flock($fp, LOCK_UN); fclose($fp);
}

// Rapor
if (isset($_GET['rapor'])) {
    header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store');
    $key = (string)($CONFIG['REKLAM_RAPOR_ANAHTARI'] ?? '');
    if ($key === '' || !hash_equals($key, (string)$_GET['rapor'])) { http_response_code(403); exit('Yetkisiz'); }
    $d = json_decode((string)@file_get_contents($statFile), true) ?: [];
    echo '<meta name="robots" content="noindex"><style>body{font:14px system-ui;padding:20px}td,th{border:1px solid #ccc;padding:6px 10px}</style><h1>Reklam istatistikleri</h1><table><tr><th>Reklam</th><th>Ay</th><th>Görüntülenme</th><th>Web</th><th>Telefon</th><th>Yol tarifi</th></tr>';
    foreach ($d as $id => $months) foreach ($months as $m => $c)
        echo '<tr><td>' . htmlspecialchars(($byId[$id]['ad'] ?? $id)) . '</td><td>' . htmlspecialchars($m) . '</td><td>' . ($c['gor'] ?? 0) . '</td><td>' . ($c['web'] ?? 0) . '</td><td>' . ($c['tel'] ?? 0) . '</td><td>' . ($c['yol'] ?? 0) . '</td></tr>';
    echo '</table>'; exit;
}
// Görüntülenme
if (isset($_GET['gor'])) {
    $keys = [];
    foreach (array_slice(explode(',', (string)$_GET['gor']), 0, 6) as $id) if (isset($byId[$id])) $keys[] = [$id, 'gor'];
    if ($keys) bump($statFile, $keys);
    http_response_code(204); exit;
}
// Tıklama → yönlendirme (yalnızca reklamın kendi adreslerine)
$id = (string)($_GET['id'] ?? ''); $t = (string)($_GET['t'] ?? ''); $u = (string)($_GET['u'] ?? '');
if (!isset($byId[$id]) || !in_array($t, ['web', 'tel', 'yol'], true)) { http_response_code(404); exit; }
$a = $byId[$id];
$allowed = false;
if ($t === 'web' && isset($a['web']) && $u === $a['web']) $allowed = true;
if ($t === 'tel' && isset($a['telefon']) && $u === 'tel:' . preg_replace('/[^+\d]/', '', $a['telefon'])) $allowed = true;
if ($t === 'yol' && isset($a['lat'], $a['lng']) && $u === 'https://www.google.com/maps/dir/?api=1&destination=' . $a['lat'] . ',' . $a['lng']) $allowed = true;
if (!$allowed) { http_response_code(400); exit; }
bump($statFile, [[$id, $t]]);
header('Cache-Control: no-store');
header('Location: ' . $u, true, 302);
