<?php
/**
 * Recibe la respuesta de /encuesta/{token} (mismo origen) y la relaya, por el
 * puente de JENOFONTE, hasta el modulo de bienestar de HGLC PEOPLE
 * (`POST /portal/charlas/encuesta`).
 *
 * El tope por IP es holgado: tras una charla contesta mucha gente desde la red
 * del hospital, que sale por una sola IP. Corta un script, no una tanda de
 * respuestas.
 */
declare(strict_types=1);

require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/portal_client.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function enc_out(array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    enc_out(['ok' => false, 'error' => 'Metodo no permitido.'], 405);
}

$token = trim((string) ($_POST['token'] ?? ''));
if ($token === '') {
    enc_out(['ok' => false, 'error' => 'Enlace no valido. Abrelo desde el correo.'], 422);
}

/** 1..5, o null si no contestaron esa pregunta. */
function enc_escala(string $campo): ?int {
    $v = trim((string) ($_POST[$campo] ?? ''));
    if ($v === '' || !ctype_digit($v)) return null;
    $n = (int) $v;
    return ($n >= 1 && $n <= 5) ? $n : null;
}

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$rlDir = __DIR__ . '/../storage/cache/encuesta';
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
if (count($hits) >= 150) {
    enc_out(['ok' => false, 'error' => 'Demasiadas respuestas desde esta conexion. Espera unos minutos.'], 429);
}
$hits[] = $now;
@file_put_contents($rlFile, json_encode($hits), LOCK_EX);

$reco = trim((string) ($_POST['recommends'] ?? ''));
$payload = [
    'token'         => $token,
    'overall'       => enc_escala('overall'),
    'content'       => enc_escala('content'),
    'speakers'      => enc_escala('speakers'),
    'logistics'     => enc_escala('logistics'),
    'recommends'    => $reco === '' ? null : ($reco === '1'),
    'comment'       => mb_substr(trim((string) ($_POST['comment'] ?? '')), 0, 2000),
    'absenceReason' => mb_substr(trim((string) ($_POST['absenceReason'] ?? '')), 0, 200),
];

$r = portal_api_call('POST', '/portal/charlas/encuesta', $payload);
if (!empty($r['ok'])) {
    enc_out(['ok' => true]);
}

$msg = '';
if (isset($r['message']) && is_string($r['message']) && $r['message'] !== '') $msg = $r['message'];
elseif (isset($r['error']) && is_string($r['error']) && $r['error'] !== '') $msg = $r['error'];
$code = (int) ($r['status'] ?? 0);
if ($msg === '' || $code >= 500 || $code === 0) {
    $msg = 'No pudimos guardar tu respuesta en este momento. Intentalo de nuevo en un rato.';
}
enc_out(['ok' => false, 'error' => $msg], 422);
