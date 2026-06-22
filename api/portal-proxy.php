<?php
/**
 * Proxy server-side para acciones AJAX del portal.
 * El navegador llama a este archivo (mismo origen) y este reenvía
 * a la API interna usando el JWT del paciente guardado en sesión.
 *
 * El navegador NUNCA ve el token ni la URL interna de la VIP.
 *
 * Formato de request:
 *   POST /api/portal-proxy.php
 *   {
 *     "method": "GET" | "POST" | "PUT" | "DELETE",
 *     "path":   "/portal/doctors/123/slots",
 *     "query":  { ... }   // opcional (para GET)
 *     "body":   { ... }   // opcional
 *   }
 *
 * Validación de CSRF: header X-CSRF-Token.
 */

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/portal_client.php';
require_once __DIR__ . '/../includes/portal_session.php';
require_once __DIR__ . '/../includes/phi_audit.php';

header('Content-Type: application/json; charset=utf-8');

// Limita rutas permitidas para evitar acceso a endpoints internos
$allowedPrefixes = [
    '/portal/auth/logout',
    '/portal/auth/resend-verification',
    '/portal/auth/verify-email',
    '/portal/me',
    '/portal/doctors',
    '/portal/specialties',
];

$payload = json_decode(file_get_contents('php://input'), true) ?? [];
$method  = strtoupper((string)($payload['method'] ?? ''));
$path    = '/' . ltrim((string)($payload['path'] ?? ''), '/');
$query   = $payload['query'] ?? [];
$body    = $payload['body']  ?? [];

if (!in_array($method, ['GET','POST','PUT','DELETE'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
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

// CSRF + sesión para todas las llamadas autenticadas
$needsAuth = !in_array($path, ['/portal/auth/verify-email', '/portal/auth/resend-verification'], true);

if ($needsAuth) {
    portal_csrf_check();
    if (!portal_is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sesión expirada.']);
        exit;
    }
}

// Los datos personales del expediente son de solo lectura para el paciente.
// El cambio de contraseña usa /portal/me/password y continúa permitido.
if ($path === '/portal/me' && $method !== 'GET') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'La información del expediente solo puede modificarse directamente con el hospital.',
    ]);
    exit;
}

$token = portal_token();
$res = portal_api_call($method, $path, $method === 'GET' ? $query : $body, $token);

// Al confirmar un correo nuevo, refrescar la sesión (el correo y el estado de
// verificación) para que el portal deje de pedirlo y el perfil lo muestre al instante.
if ($path === '/portal/me/email-confirm' && ($res['status'] ?? 0) === 200) {
    $j = json_decode($res['raw'] ?? '', true);
    $newEmail = $j['data']['email'] ?? null;
    if ($newEmail) {
        $_SESSION['portal_patient']['email'] = $newEmail;
        portal_set_verified(true);
    }
}

// Bitácora de auditoría de PHI: el paciente accede a sus propios datos.
if ($needsAuth && str_starts_with($path, '/portal/me')) {
    $pat = portal_patient();
    $pid = isset($pat['id']) ? (int) $pat['id'] : null;
    phi_audit_record('patient', $pid, phi_audit_actor_label($pat), $method, $path, (int) ($res['status'] ?? 0), $pid);
}

// Si el upstream devuelve 401, limpiar sesión local
if ($res['status'] === 401 && $needsAuth) {
    portal_logout();
}

http_response_code($res['status'] ?: 502);
echo $res['raw'] !== '' ? $res['raw'] : json_encode([
    'success' => false,
    'message' => $res['message'] ?? 'Error al contactar con la API.',
]);
