<?php
/**
 * TEMPORAL — Verifica qué puertos outbound puede usar este hosting.
 * BÓRRALO inmediatamente después.
 */
header('Content-Type: text/plain; charset=utf-8');

$tests = [
    'VIP Fortinet HGLC :20443'   => ['186.149.243.228', 20443],
    'VIP Fortinet HGLC :443'     => ['186.149.243.228', 443],
    'VIP Fortinet HGLC :80'      => ['186.149.243.228', 80],
    'Google :443'                => ['142.250.190.78', 443],
    'Google :80'                 => ['142.250.190.78', 80],
    'Cloudflare 1.1.1.1 :443'    => ['1.1.1.1', 443],
    'Test puerto raro :20443'    => ['1.1.1.1', 20443],
    'Test puerto raro :8443'     => ['1.1.1.1', 8443],
];

echo "=== Test de conectividad outbound desde el cPanel ===\n";
echo "Si Google y Cloudflare funcionan en :443 pero :20443 falla → el hosting bloquea outbound a ese puerto.\n";
echo "Si todos los puertos altos fallan → CSF u otro firewall del hosting filtra puertos no estandar.\n\n";

foreach ($tests as $label => [$ip, $port]) {
    $start = microtime(true);
    $errno = 0; $errstr = '';
    $sock = @fsockopen($ip, $port, $errno, $errstr, 5);
    $ms = round((microtime(true) - $start) * 1000);
    if ($sock) {
        echo sprintf("✓ %-32s OPEN  (%4dms) %s:%d\n", $label, $ms, $ip, $port);
        fclose($sock);
    } else {
        echo sprintf("✗ %-32s FAIL  (%4dms) %s:%d → %s (errno %d)\n", $label, $ms, $ip, $port, $errstr, $errno);
    }
}

echo "\n=== IP saliente de este hosting ===\n";
$ip = @file_get_contents('https://api.ipify.org', false, stream_context_create(['http' => ['timeout' => 5]]));
echo trim($ip ?: '?') . "\n";
