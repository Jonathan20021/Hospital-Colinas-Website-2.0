<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }   // solo por línea de comandos
// Prueba de ApnsSender.php: firma ES256, JWT, payload y envío por HTTP/2
// contra el APNs simulado (mock-apns.js). La corre correr.sh.
// Uso: php prueba-emisor.php <carpeta de ApnsSender.php> <carpeta de trabajo con llaves>
require $argv[1] . '/ApnsSender.php';

$dir = $argv[2];
$fallos = 0;
function comprobar(bool $cond, string $que): void {
    global $fallos;
    echo ($cond ? '  ✓ ' : '  ✗ ') . $que . "\n";
    if (!$cond) $fallos++;
}

$cfg = [
    'key_path'  => "$dir/AuthKey_TEST123456.p8",
    'key_id'    => 'TEST123456',
    'team_id'   => 'TEAMID7890',
    'topic'     => 'com.colinashospital.paciente',
    'cache_dir' => "$dir/cache",
];

echo "1) Firma ES256: DER → r||s (64 bytes) y de vuelta, 300 firmas\n";
$pub = openssl_pkey_get_public(file_get_contents("$dir/apns-pub.pem"));
$priv = openssl_pkey_get_private(file_get_contents($cfg['key_path']));
$bien = 0;
function crudoADer(string $crudo): string {
    $int = function (string $b): string {
        $b = ltrim($b, "\x00");
        if ($b === '' ) $b = "\x00";
        if (ord($b[0]) & 0x80) $b = "\x00" . $b;
        return "\x02" . chr(strlen($b)) . $b;
    };
    $seq = $int(substr($crudo, 0, 32)) . $int(substr($crudo, 32, 32));
    return "\x30" . chr(strlen($seq)) . $seq;
}
for ($i = 0; $i < 300; $i++) {
    $msg = random_bytes(40);
    openssl_sign($msg, $der, $priv, OPENSSL_ALGO_SHA256);
    $crudo = ApnsSender::derAFirmaCruda($der);
    if (strlen($crudo) === 64 && openssl_verify($msg, crudoADer($crudo), $pub, OPENSSL_ALGO_SHA256) === 1) $bien++;
}
comprobar($bien === 300, "las 300 firmas convertidas verifican ($bien/300)");

echo "2) Token de proveedor (JWT)\n";
@array_map('unlink', glob("$dir/cache/*"));
$jwt1 = ApnsSender::tokenDeProveedor($cfg);
[$c, $p, $f] = explode('.', $jwt1);
$cab = json_decode(base64_decode(strtr($c, '-_', '+/')), true);
$cla = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
comprobar($cab === ['alg' => 'ES256', 'kid' => 'TEST123456'], 'cabecera {alg: ES256, kid}');
comprobar($cla['iss'] === 'TEAMID7890' && abs($cla['iat'] - time()) < 5, 'claims {iss: team, iat: ahora}');
comprobar(strlen(base64_decode(strtr($f, '-_', '+/') . '==')) === 64, 'firma de 64 bytes');
$jwt2 = ApnsSender::tokenDeProveedor($cfg);
comprobar($jwt1 === $jwt2, 'se reutiliza desde la caché (Apple castiga renovarlo seguido)');
$cache = glob("$dir/cache/apns-jwt-*.json");
comprobar(count($cache) === 1 && (fileperms($cache[0]) & 0777) === 0600, 'caché con permisos 600');

echo "3) Payload\n";
$pl = json_decode(ApnsSender::payload(['cuerpo' => 'Tienes un mensaje nuevo de tu médico.', 'url' => '/portal/mensajes.php', 'grupo' => 'mensajes']), true);
comprobar($pl['aps']['alert']['body'] === 'Tienes un mensaje nuevo de tu médico.' && $pl['aps']['thread-id'] === 'mensajes', 'aps.alert + thread-id');
comprobar($pl['url'] === '/portal/mensajes.php', 'url del portal se conserva');
$pl2 = json_decode(ApnsSender::payload(['cuerpo' => 'x', 'url' => 'https://evil.example/']), true);
comprobar(!isset($pl2['url']), 'url externa se descarta');
$pl3 = json_decode(ApnsSender::payload(['cuerpo' => 'x', 'url' => '/portal//evil.example']), true);
comprobar(!isset($pl3['url']), 'url con // se descarta');
try { ApnsSender::payload(['cuerpo' => str_repeat('a', 5000)]); comprobar(false, 'rechaza >4 KB'); }
catch (InvalidArgumentException $e) { comprobar(true, 'rechaza >4 KB'); }
try { ApnsSender::payload(['cuerpo' => '  ']); comprobar(false, 'rechaza cuerpo vacío'); }
catch (InvalidArgumentException $e) { comprobar(true, 'rechaza cuerpo vacío'); }

echo "4) Envío contra el APNs simulado (HTTP/2)\n";
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE settings (setting_key TEXT PRIMARY KEY, setting_value TEXT)');
$db->exec('CREATE TABLE portal_push_apns (id INTEGER PRIMARY KEY AUTOINCREMENT, patient_id INT NOT NULL,
           token TEXT NOT NULL UNIQUE, environment TEXT NOT NULL, bundle_id TEXT, last_sent_at TEXT, last_error TEXT)');
$ins = $db->prepare('INSERT INTO portal_push_apns (patient_id, token, environment, bundle_id) VALUES (?, ?, ?, ?)');
$ins->execute([7, str_repeat('a', 64), 'production', $cfg['topic']]);
$ins->execute([7, str_repeat('b', 64), 'production', $cfg['topic']]);
$ins->execute([7, str_repeat('c', 64), 'sandbox', $cfg['topic']]);
$ins->execute([8, str_repeat('a1', 32), 'production', $cfg['topic']]);   // otro paciente

$opciones = [
    'config' => $cfg,
    'base'   => ['production' => 'https://127.0.0.1:18443', 'sandbox' => 'https://127.0.0.1:18444'],
    'curl'   => [CURLOPT_CAINFO => "$dir/tls-cert.pem"],
];
$aviso = ['cuerpo' => 'Tienes un mensaje nuevo de tu médico.', 'url' => '/portal/mensajes.php', 'grupo' => 'mensajes'];

$r0 = ApnsSender::aPaciente($db, 7, $aviso, $opciones);
comprobar(($r0['omitido'] ?? '') === 'interruptor_apagado', 'sin settings.patient_push_apns = 1 no envía nada');
$db->exec("INSERT INTO settings VALUES ('patient_push_apns', '1')");

$r = ApnsSender::aPaciente($db, 7, $aviso, $opciones);
comprobar($r['enviados'] === 1 && $r['fallidos'] === 2 && $r['borrados'] === 2, "1 enviado, 2 fallidos, 2 borrados (" . json_encode(array_diff_key($r, ['detalle' => 1])) . ")");
$filas = $db->query('SELECT patient_id, substr(token,1,2) t, last_sent_at, last_error FROM portal_push_apns ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
comprobar(count($filas) === 2, 'quedan 2 tokens (el válido y el del otro paciente)');
comprobar($filas[0]['t'] === 'aa' && $filas[0]['last_sent_at'] !== null, 'el token válido queda con last_sent_at');
comprobar($filas[1]['patient_id'] == 8 && $filas[1]['last_sent_at'] === null, 'el token del otro paciente no se tocó');

$r9 = ApnsSender::aPaciente($db, 99, $aviso, $opciones);
comprobar(($r9['omitido'] ?? '') === 'sin_dispositivos', 'paciente sin dispositivos: nada que enviar');

exit($fallos ? 1 : 0);
