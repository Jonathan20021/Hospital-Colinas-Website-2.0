<?php
/**
 * Proxy server-side para acciones AJAX del Portal del Doctor.
 * El navegador llama a este archivo (mismo origen) y este reenvia
 * a la API interna usando el JWT del doctor guardado en sesion.
 *
 * El navegador NUNCA ve el token ni la URL interna de la VIP.
 *
 * Formato de request:
 *   POST /api/doctor-proxy.php
 *   {
 *     "method": "GET" | "POST" | "PUT" | "DELETE",
 *     "path":   "/portal-doctor/me/appointments/123",
 *     "query":  { ... }   // opcional (para GET)
 *     "body":   { ... }   // opcional
 *   }
 *
 * Validacion de CSRF: header X-CSRF-Token.
 */

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/portal_client.php';
require_once __DIR__ . '/../includes/doctor_portal_session.php';

header('Content-Type: application/json; charset=utf-8');

// Solo rutas /portal-doctor/* permitidas
$allowedPrefixes = [
    '/portal-doctor/me',
    '/portal-doctor/auth/logout',
];

$payload = json_decode(file_get_contents('php://input'), true) ?? [];
$method  = strtoupper((string)($payload['method'] ?? ''));
$path    = '/' . ltrim((string)($payload['path'] ?? ''), '/');
$query   = $payload['query'] ?? [];
$body    = $payload['body']  ?? [];

if (!in_array($method, ['GET','POST','PUT','DELETE'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Metodo invalido.']);
    exit;
}

$allowed = false;
foreach ($allowedPrefixes as $p) {
    if (str_starts_with($path, $p)) { $allowed = true; break; }
}
if (!$allowed) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Ruta no permitida desde el proxy.']);
    exit;
}

// Toda llamada al portal del doctor requiere sesion + CSRF
doctor_csrf_check();
if (!doctor_is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesion expirada.']);
    exit;
}

$token = doctor_token();
$res = portal_api_call($method, $path, $method === 'GET' ? $query : $body, $token);

// Si el upstream devuelve 401, limpiar sesion local
if ($res['status'] === 401) {
    doctor_portal_logout();
}

http_response_code($res['status'] ?: 502);
echo $res['raw'] !== '' ? $res['raw'] : json_encode([
    'success' => false,
    'message' => $res['message'] ?? 'Error al contactar con la API.',
]);
