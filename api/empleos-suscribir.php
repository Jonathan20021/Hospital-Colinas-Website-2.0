<?php
/**
 * Alta al newsletter de alertas de empleo. Relaya el correo por el puente de
 * JENOFONTE (`POST /portal/empleos/suscribir`), que llega a HGLC PEOPLE, donde
 * se crea el suscriptor PENDING y se envía el correo de confirmación (doble
 * opt-in). Honeypot + límite por IP antes de cruzar.
 */
declare(strict_types=1);

require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/portal_client.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function subs_out(bool $ok, string $error = '', int $code = 200): void {
    http_response_code($code);
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') subs_out(false, 'Método no permitido.', 405);

// Honeypot: bot → fingir éxito, no crear nada.
if (trim((string) ($_POST['website'] ?? '')) !== '') subs_out(true);

$email = trim((string) ($_POST['email'] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    subs_out(false, 'Escribe un correo válido.', 422);
}

// Límite por IP: máx. 10 altas por hora.
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$rlDir = __DIR__ . '/../storage/cache/empleos';
if (!is_dir($rlDir)) @mkdir($rlDir, 0775, true);
$rlFile = $rlDir . '/sub_' . preg_replace('/[^0-9a-f:.]/i', '_', $ip) . '.json';
$now = time();
$hits = [];
if (is_file($rlFile)) {
    $decoded = json_decode((string) @file_get_contents($rlFile), true);
    if (is_array($decoded)) $hits = array_values(array_filter($decoded, static fn($t) => is_int($t) && ($now - $t) < 3600));
}
if (count($hits) >= 10) subs_out(false, 'Demasiadas solicitudes. Inténtalo más tarde.', 429);
$hits[] = $now;
@file_put_contents($rlFile, json_encode($hits), LOCK_EX);

$r = portal_api_call('POST', '/portal/empleos/suscribir', ['email' => mb_substr($email, 0, 160, 'UTF-8')]);

if (!empty($r['ok'])) subs_out(true);
$msg = '';
if (isset($r['message']) && is_string($r['message']) && $r['message'] !== '') $msg = $r['message'];
elseif (isset($r['error']) && is_string($r['error']) && $r['error'] !== '') $msg = $r['error'];
$code = (int) ($r['status'] ?? 0);
if ($msg === '' || $code >= 500 || $code === 0) $msg = 'No pudimos completar la suscripción en este momento. Inténtalo más tarde.';
subs_out(false, $msg, 422);
