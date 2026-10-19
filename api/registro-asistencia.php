<?php
/**
 * Recibe el registro de asistencia de /registro/{token} (mismo origen) y lo
 * relaya, por el puente de JENOFONTE, hasta el modulo de bienestar de HGLC
 * PEOPLE (`POST /portal/charlas/asistencia`).
 *
 * El tope por IP es HOLGADO a proposito: en una estacion de salud se registra
 * mucha gente desde la misma red del hospital. Corta un script, no una fila.
 */
declare(strict_types=1);

require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/portal_client.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function reg_out(array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    reg_out(['ok' => false, 'error' => 'Metodo no permitido.'], 405);
}

$token  = trim((string) ($_POST['token'] ?? ''));
$cedula = preg_replace('/\D/', '', (string) ($_POST['cedula'] ?? ''));

if ($token === '') {
    reg_out(['ok' => false, 'error' => 'Codigo no valido. Vuelve a escanear el cartel.'], 422);
}
if (strlen($cedula) < 8 || strlen($cedula) > 13) {
    reg_out(['ok' => false, 'error' => 'Escribe tu cedula completa, solo los numeros.'], 422);
}

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$rlDir = __DIR__ . '/../storage/cache/registro';
if (!is_dir($rlDir)) @mkdir($rlDir, 0775, true);
$rlFile = $rlDir . '/rl_' . preg_replace('/[^0-9a-f:.]/i', '_', $ip) . '.json';
$now = time();
$hits = [];
if (is_file($rlFile)) {
    $raw = @file_get_contents($rlFile);
    $decoded = $raw ? json_decode($raw, true) : [];
    if (is_array($decoded)) {
        $hits = array_values(array_filter($decoded, static fn($t) => is_int($t) && ($now - $t) < 3600));
    }
}
if (count($hits) >= 120) {
    reg_out(['ok' => false, 'error' => 'Demasiados registros desde esta conexion. Espera unos minutos.'], 429);
}
$hits[] = $now;
@file_put_contents($rlFile, json_encode($hits), LOCK_EX);

$r = portal_api_call('POST', '/portal/charlas/asistencia', ['token' => $token, 'cedula' => $cedula]);

// El puente devuelve el resultado de HGLC PEOPLE dentro de `data`: saludo,
// cuantas actividades lleva y cuantas faltan para la rifa.
if (!empty($r['ok']) && is_array($r['data'] ?? null)) {
    reg_out(['ok' => true] + $r['data']);
}

$msg = '';
if (isset($r['message']) && is_string($r['message']) && $r['message'] !== '') $msg = $r['message'];
elseif (isset($r['error']) && is_string($r['error']) && $r['error'] !== '') $msg = $r['error'];
$code = (int) ($r['status'] ?? 0);
if ($msg === '' || $code >= 500 || $code === 0) {
    $msg = 'No pudimos registrar tu asistencia en este momento. Acercate a Recursos Humanos.';
}
reg_out(['ok' => false, 'error' => $msg], 422);
