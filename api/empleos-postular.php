<?php
/**
 * Recibe la postulación del formulario de /empleos/{id} (mismo origen) y la
 * relaya, por el puente seguro de JENOFONTE, hasta la app de reclutamiento
 * HGLC PEOPLE (`POST /portal/empleos/postular`). El CV viaja en base64.
 *
 * Defensa en profundidad: honeypot + límite por IP + validación (incluido que
 * el CV sea PDF de verdad) ANTES de cruzar el puente; la validación fuerte y el
 * anti-spam por correo viven en HGLC PEOPLE.
 */
declare(strict_types=1);

require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/portal_client.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function emp_out(bool $ok, string $error = '', int $code = 200): void {
    http_response_code($code);
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    emp_out(false, 'Método no permitido.', 405);
}

// 1) Honeypot: un bot llena el campo oculto "website". Fingimos éxito para no
//    darle pistas y no crear nada.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    emp_out(true);
}

// 2) Límite por IP (best-effort): máx. 8 envíos por hora por IP.
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$rlDir = __DIR__ . '/../storage/cache/empleos';
if (!is_dir($rlDir)) @mkdir($rlDir, 0775, true);
$rlFile = $rlDir . '/rl_' . preg_replace('/[^0-9a-f:.]/i', '_', $ip) . '.json';
$now = time();
$hits = [];
if (is_file($rlFile)) {
    $raw = @file_get_contents($rlFile);
    $decoded = $raw ? json_decode($raw, true) : [];
    if (is_array($decoded)) $hits = array_values(array_filter($decoded, static fn($t) => is_int($t) && ($now - $t) < 3600));
}
if (count($hits) >= 8) {
    emp_out(false, 'Has enviado varias postulaciones seguidas. Inténtalo de nuevo en un rato.', 429);
}

// 3) Campos
$vacancyId = trim((string) ($_POST['vacancyId'] ?? ''));
$firstName = trim((string) ($_POST['firstName'] ?? ''));
$lastName  = trim((string) ($_POST['lastName'] ?? ''));
$email     = trim((string) ($_POST['email'] ?? ''));
$phone     = trim((string) ($_POST['phone'] ?? ''));
$headline  = trim((string) ($_POST['headline'] ?? ''));
$location  = trim((string) ($_POST['location'] ?? ''));
$consent   = !empty($_POST['consent']);

if ($vacancyId === '' || $firstName === '' || $lastName === '' || $email === '' || $phone === '') {
    emp_out(false, 'Por favor completa todos los campos obligatorios.', 422);
}
if (!$consent) {
    emp_out(false, 'Debes autorizar el tratamiento de tus datos para postularte.', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    emp_out(false, 'El correo no tiene un formato válido.', 422);
}
if (strlen(preg_replace('/\D/', '', $phone)) < 10) {
    emp_out(false, 'El teléfono debe tener al menos 10 dígitos.', 422);
}

// 4) CV: presente, sin error, PDF real (magic bytes), tope 8 MB.
if (empty($_FILES['cv']) || ($_FILES['cv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    emp_out(false, 'Adjunta tu currículum en PDF.', 422);
}
$cvTmp = (string) $_FILES['cv']['tmp_name'];
$cvSize = (int) ($_FILES['cv']['size'] ?? 0);
if (!is_uploaded_file($cvTmp) || $cvSize <= 0) {
    emp_out(false, 'No pudimos leer el archivo del currículum.', 422);
}
if ($cvSize > 8 * 1024 * 1024) {
    emp_out(false, 'El CV pesa demasiado; el tope son 8 MB.', 422);
}
$head = (string) @file_get_contents($cvTmp, false, null, 0, 5);
if (strncmp($head, '%PDF-', 5) !== 0) {
    emp_out(false, 'El currículum debe ser un PDF válido.', 422);
}
$cvBytes = (string) @file_get_contents($cvTmp);
if ($cvBytes === '') {
    emp_out(false, 'No pudimos leer el archivo del currículum.', 422);
}
$cvName = (string) ($_FILES['cv']['name'] ?? 'cv.pdf');
$cvName = preg_replace('/[^\w.\- ]+/u', '_', $cvName);
$cvName = mb_substr($cvName, 0, 120, 'UTF-8');

// 5) Registrar el intento (rate-limit) y relayar por el puente.
$hits[] = $now;
@file_put_contents($rlFile, json_encode($hits), LOCK_EX);

$payload = [
    'vacancyId' => $vacancyId,
    'firstName' => mb_substr($firstName, 0, 60, 'UTF-8'),
    'lastName'  => mb_substr($lastName, 0, 60, 'UTF-8'),
    'email'     => mb_substr($email, 0, 120, 'UTF-8'),
    'phone'     => mb_substr($phone, 0, 30, 'UTF-8'),
    'headline'  => $headline !== '' ? mb_substr($headline, 0, 160, 'UTF-8') : null,
    'location'  => $location !== '' ? mb_substr($location, 0, 80, 'UTF-8') : null,
    'consent'   => true,
    'cv'        => ['fileName' => $cvName, 'dataBase64' => base64_encode($cvBytes)],
];

$r = portal_api_call('POST', '/portal/empleos/postular', $payload);

if (!empty($r['ok'])) {
    emp_out(true);
}
$msg = '';
if (isset($r['message']) && is_string($r['message']) && $r['message'] !== '') $msg = $r['message'];
elseif (isset($r['error']) && is_string($r['error']) && $r['error'] !== '') $msg = $r['error'];
// Si el puente falló por conectividad/config (no un rechazo de validación), no
// filtrar detalle: mensaje genérico.
$code = (int) ($r['status'] ?? 0);
if ($msg === '' || $code >= 500 || $code === 0) {
    $msg = 'No pudimos enviar tu postulación en este momento. Inténtalo más tarde.';
}
emp_out(false, $msg, 422);
