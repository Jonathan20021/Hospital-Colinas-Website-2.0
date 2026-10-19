<?php
/**
 * Recibe la inscripción del formulario de /bienestar (mismo origen) y la relaya,
 * por el puente seguro de JENOFONTE, hasta el módulo de bienestar de HGLC PEOPLE
 * (`POST /portal/charlas/inscribir`).
 *
 * Defensa en profundidad: honeypot + límite por IP + validación ANTES de cruzar
 * el puente; la validación fuerte (actividad vigente, cupo, duplicados) vive en
 * HGLC PEOPLE, que es la dueña de los datos.
 *
 * Solo público general. El personal del hospital se inscribe desde la intranet:
 * su cédula y su fecha de nacimiento no deben pasar por un sitio público.
 */
declare(strict_types=1);

require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/portal_client.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function bien_out(bool $ok, string $error = '', int $code = 200): void {
    http_response_code($code);
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    bien_out(false, 'Método no permitido.', 405);
}

// 1) Honeypot: un bot llena el campo oculto "website". Fingimos éxito para no
//    darle pistas y no crear nada.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    bien_out(true);
}

// 2) Límite por IP (best-effort): máx. 10 inscripciones por hora por IP. Una
//    familia inscribiéndose desde el mismo teléfono cabe de sobra; un script, no.
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$rlDir = __DIR__ . '/../storage/cache/bienestar';
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
if (count($hits) >= 10) {
    bien_out(false, 'Has enviado varias inscripciones seguidas. Inténtalo de nuevo en un rato.', 429);
}

// 3) Campos
$activityId = trim((string) ($_POST['activityId'] ?? ''));
$fullName   = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['fullName'] ?? '')));
$email      = trim((string) ($_POST['email'] ?? ''));
$phone      = trim((string) ($_POST['phone'] ?? ''));

if ($activityId === '' || $fullName === '' || $email === '' || $phone === '') {
    bien_out(false, 'Por favor completa todos los campos.', 422);
}
if (mb_strlen($fullName, 'UTF-8') < 5 || mb_strpos($fullName, ' ', 0, 'UTF-8') === false) {
    bien_out(false, 'Escribe tu nombre y tu apellido.', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    bien_out(false, 'El correo no tiene un formato válido.', 422);
}
if (strlen(preg_replace('/\D/', '', $phone)) < 10) {
    bien_out(false, 'El teléfono debe tener al menos 10 dígitos.', 422);
}

// 4) Registrar el intento (rate-limit) y relayar por el puente.
$hits[] = $now;
@file_put_contents($rlFile, json_encode($hits), LOCK_EX);

$r = portal_api_call('POST', '/portal/charlas/inscribir', [
    'activityId' => mb_substr($activityId, 0, 64, 'UTF-8'),
    'fullName'   => mb_substr($fullName, 0, 120, 'UTF-8'),
    'email'      => mb_substr($email, 0, 180, 'UTF-8'),
    'phone'      => mb_substr($phone, 0, 30, 'UTF-8'),
]);

if (!empty($r['ok'])) {
    bien_out(true);
}

$msg = '';
if (isset($r['message']) && is_string($r['message']) && $r['message'] !== '') $msg = $r['message'];
elseif (isset($r['error']) && is_string($r['error']) && $r['error'] !== '') $msg = $r['error'];
// Si el puente falló por conectividad o configuración (no por un rechazo de
// validación), no se filtra el detalle: mensaje genérico.
$code = (int) ($r['status'] ?? 0);
if ($msg === '' || $code >= 500 || $code === 0) {
    $msg = 'No pudimos registrar tu inscripción en este momento. Inténtalo más tarde.';
}
bien_out(false, $msg, 422);
