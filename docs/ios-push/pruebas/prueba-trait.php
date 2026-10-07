<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }   // solo por línea de comandos
// Prueba de ApnsTrait.php y schema.sql contra MariaDB/MySQL, y del SQL de
// ApnsSender en ese dialecto. La corre correr.sh si hay base de datos de prueba.
// Uso: php prueba-trait.php <carpeta de los .php> <carpeta de trabajo con llaves>
// Base: PRUEBA_MYSQL_DSN, PRUEBA_MYSQL_USUARIO, PRUEBA_MYSQL_CLAVE. ¡Usa una base
// DESECHABLE: la prueba borra y crea las tablas settings y portal_push_apns!
$dirDocs = $argv[1];
$dir = $argv[2];
$fallos = 0;
function comprobar(bool $cond, string $que): void {
    global $fallos;
    echo ($cond ? '  ✓ ' : '  ✗ ') . $que . "\n";
    if (!$cond) $fallos++;
}

// Doble de la API interna: Response::success/error terminan la petición (como exit).
final class FinRespuesta extends Exception { public $codigo; public $datos; }
final class Response {
    public static function success($datos = null): void { $e = new FinRespuesta('ok'); $e->codigo = 200; $e->datos = $datos; throw $e; }
    public static function error(string $msg, int $codigo = 400): void { $e = new FinRespuesta($msg); $e->codigo = $codigo; throw $e; }
}
require "$dirDocs/ApnsTrait.php";
require "$dirDocs/ApnsSender.php";

final class PortalControllerDePrueba {
    use ApnsTrait;
    public $db; public $patient; public $entrada = []; public $auditoria = [];
    public function __construct(PDO $db) { $this->db = $db; }
    public function body(): array { return $this->entrada; }
    public function logAudit(string $accion, string $detalle, $pid = null): void { $this->auditoria[] = $accion; }
    public function llamar(string $metodo, int $pid, array $entrada): FinRespuesta {
        $this->patient = ['patient_id' => $pid];
        $this->entrada = $entrada;
        try { $this->$metodo(); } catch (FinRespuesta $f) { return $f; }
        throw new RuntimeException("$metodo no respondió");
    }
}

$db = new PDO(getenv('PRUEBA_MYSQL_DSN'), getenv('PRUEBA_MYSQL_USUARIO') ?: 'root', getenv('PRUEBA_MYSQL_CLAVE') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,   // como la API interna
]);
$db->exec('DROP TABLE IF EXISTS portal_push_apns');
$db->exec('DROP TABLE IF EXISTS settings');
$db->exec('CREATE TABLE settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT)');

echo "1) schema.sql\n";
$schema = file_get_contents("$dirDocs/schema.sql");
foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', preg_replace('/^--.*$/m', '', $schema)))) as $sql) $db->exec($sql);
comprobar((string) $db->query("SELECT setting_value FROM settings WHERE setting_key='patient_push_apns'")->fetchColumn() === '0', 'interruptor creado APAGADO');
$db->exec("UPDATE settings SET setting_value='1' WHERE setting_key='patient_push_apns'");
foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', preg_replace('/^--.*$/m', '', $schema)))) as $sql) $db->exec($sql);
comprobar((string) $db->query("SELECT setting_value FROM settings WHERE setting_key='patient_push_apns'")->fetchColumn() === '1', 'volver a aplicarlo no apaga un interruptor ya encendido (idempotente)');

echo "2) apnsSubscribe / apnsUnsubscribe\n";
$c = new PortalControllerDePrueba($db);
$tokA = str_repeat('ab', 32);
$base = ['token' => strtoupper($tokA), 'entorno' => 'sandbox', 'bundle_id' => 'com.colinashospital.paciente',
         'app_version' => '1.0.0', 'os_version' => '18.6', 'dispositivo' => 'iPhone'];

$r = $c->llamar('apnsSubscribe', 7, $base);
comprobar($r->codigo === 200 && $r->datos === ['subscribed' => true], 'registro: 200 {subscribed: true}');
$fila = $db->query('SELECT * FROM portal_push_apns')->fetch(PDO::FETCH_ASSOC);
comprobar($fila['token'] === $tokA, 'el token se guarda en minúsculas');
comprobar($fila['patient_id'] == 7 && $fila['environment'] === 'sandbox' && $fila['device'] === 'iPhone' && $fila['os_version'] === '18.6', 'paciente, entorno y dispositivo guardados');
comprobar($c->auditoria === ['push_apns'], 'queda en la bitácora de auditoría');

$db->exec("UPDATE portal_push_apns SET updated_at = '2026-01-01 00:00:00', last_error = 'BadDeviceToken'");
$r = $c->llamar('apnsSubscribe', 7, $base);
$fila = $db->query('SELECT COUNT(*) n, MAX(updated_at) u, MAX(last_error) e FROM portal_push_apns')->fetch(PDO::FETCH_ASSOC);
comprobar($r->codigo === 200 && $fila['n'] == 1, 'registrar otra vez el mismo token no lo duplica');
comprobar($fila['u'] > '2026-01-01 00:00:00' && $fila['e'] === null, 're-registro renueva updated_at y limpia last_error');

$r = $c->llamar('apnsSubscribe', 8, $base);
$pid = $db->query('SELECT patient_id FROM portal_push_apns')->fetchColumn();
comprobar($r->codigo === 200 && $pid == 8, 'otro paciente en el mismo iPhone: el token pasa a ser suyo');

$r = $c->llamar('apnsUnsubscribe', 7, ['token' => $tokA]);
comprobar($r->codigo === 200 && $r->datos === ['removed' => false], 'un paciente no puede dar de baja el token de otro');
comprobar($db->query('SELECT COUNT(*) FROM portal_push_apns')->fetchColumn() == 1, '…y el token sigue ahí');
$r = $c->llamar('apnsUnsubscribe', 8, ['token' => $tokA]);
comprobar($r->codigo === 200 && $r->datos === ['removed' => true], 'el dueño sí lo da de baja');

foreach ([['token' => 'xyz'], ['token' => str_repeat('a', 63)], ['token' => str_repeat('g', 64)], ['token' => null]] as $malo) {
    $r = $c->llamar('apnsSubscribe', 7, array_merge($base, $malo));
    comprobar($r->codigo === 422, 'token inválido → 422 (' . json_encode($malo['token']) . ')');
}
$r = $c->llamar('apnsSubscribe', 7, array_merge($base, ['bundle_id' => "com.x'; DROP TABLE settings; --"]));
comprobar($r->codigo === 422, 'bundle_id con caracteres raros → 422');
$r = $c->llamar('apnsSubscribe', 7, array_merge($base, ['entorno' => 'otra-cosa']));
comprobar($r->codigo === 200 && $db->query("SELECT environment FROM portal_push_apns WHERE patient_id=7")->fetchColumn() === 'production', 'entorno desconocido → production');

echo "3) Tope de 10 dispositivos por paciente\n";
$db->exec('DELETE FROM portal_push_apns');
for ($i = 1; $i <= 12; $i++) {
    $c->llamar('apnsSubscribe', 9, array_merge($base, ['token' => str_pad(dechex($i), 64, '0', STR_PAD_LEFT)]));
    $db->exec("UPDATE portal_push_apns SET updated_at = '2026-01-01 00:00:" . sprintf('%02d', $i) . "' WHERE token = '" . str_pad(dechex($i), 64, '0', STR_PAD_LEFT) . "'");
}
$c->llamar('apnsSubscribe', 9, array_merge($base, ['token' => str_pad('d', 64, '0', STR_PAD_LEFT)]));   // el 13º, el más reciente
$tokens = $db->query('SELECT token FROM portal_push_apns WHERE patient_id = 9')->fetchAll(PDO::FETCH_COLUMN);
$quedan = array_map(function ($t) { return hexdec(ltrim($t, '0')); }, $tokens);
sort($quedan);
comprobar(count($tokens) === 10, 'quedan 10 dispositivos (' . count($tokens) . ')');
comprobar(!in_array(1, $quedan) && !in_array(2, $quedan) && !in_array(3, $quedan) && in_array(13, $quedan), 'se descartaron los 3 menos recientes y quedó el nuevo');

echo "4) SQL de ApnsSender en MariaDB (EMULATE_PREPARES=false)\n";
$db->exec('DELETE FROM portal_push_apns');
$c->llamar('apnsSubscribe', 7, array_merge($base, ['token' => str_repeat('a', 64), 'entorno' => 'production']));
$c->llamar('apnsSubscribe', 7, array_merge($base, ['token' => str_repeat('b', 64), 'entorno' => 'production']));
$c->llamar('apnsSubscribe', 7, array_merge($base, ['token' => str_repeat('c', 64), 'entorno' => 'sandbox']));
$cfg = ['key_path' => "$dir/AuthKey_TEST123456.p8", 'key_id' => 'TEST123456', 'team_id' => 'TEAMID7890',
        'topic' => 'com.colinashospital.paciente', 'cache_dir' => "$dir/cache"];
$res = ApnsSender::aPaciente($db, 7, ['cuerpo' => 'Recordatorio: tienes una cita mañana.', 'url' => '/portal/mis-citas.php', 'caduca' => time() + 86400, 'colapsar' => 'cita-12'], [
    'config' => $cfg,
    'base'   => ['production' => 'https://127.0.0.1:18443', 'sandbox' => 'https://127.0.0.1:18444'],
    'curl'   => [CURLOPT_CAINFO => "$dir/tls-cert.pem"],
]);
comprobar($res['enviados'] === 1 && $res['borrados'] === 2, 'envío: 1 entregado, 2 tokens muertos borrados');
$fila = $db->query('SELECT token, last_sent_at FROM portal_push_apns')->fetchAll(PDO::FETCH_ASSOC);
comprobar(count($fila) === 1 && $fila[0]['token'] === str_repeat('a', 64) && $fila[0]['last_sent_at'] !== null, 'queda el token válido con last_sent_at');

exit($fallos ? 1 : 0);
