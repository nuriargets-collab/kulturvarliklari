<?php
/**
 * Ziyaretçinin gerçek IP adresi. Site Cloudflare arkasındayken sunucu yalnızca Cloudflare'in
 * adresini görür; bu durumda Cloudflare'in eklediği CF-Connecting-IP başlığı kullanılır.
 * Başlığa yalnızca istek gerçekten Cloudflare adreslerinden geldiyse güvenilir (taklit edilemez).
 */
declare(strict_types=1);
function kv_client_ip(): string {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0';
    $cf = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
    if ($cf === '' || !filter_var($cf, FILTER_VALIDATE_IP)) return $remote;
    static $ranges = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
        '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
        '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
        '2a06:98c0::/29', '2c0f:f248::/32',
    ];
    $bin = @inet_pton($remote);
    if ($bin === false) return $remote;
    foreach ($ranges as $r) {
        [$net, $bits] = explode('/', $r);
        $nb = @inet_pton($net);
        if ($nb === false || strlen($nb) !== strlen($bin)) continue;
        $bits = (int)$bits; $bytes = intdiv($bits, 8); $rem = $bits % 8;
        if (substr($bin, 0, $bytes) !== substr($nb, 0, $bytes)) continue;
        if ($rem && ((ord($bin[$bytes]) ^ ord($nb[$bytes])) & (0xFF << (8 - $rem)) & 0xFF)) continue;
        return $cf;
    }
    return $remote;
}
