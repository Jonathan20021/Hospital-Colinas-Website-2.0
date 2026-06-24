<?php
/**
 * Proxy server-side: recibe el POST de "Solicitar estudios" como INVITADO y lo
 * reenvía a la API interna. Si el hospital crea una cuenta ligera y devuelve un
 * token (cédula nueva), establecemos aquí la sesión del portal para que el mismo
 * paciente pueda, acto seguido, subir sus documentos por el proxy autenticado.
 *
 * El navegador NUNCA ve el token ni la URL interna de la VIP.
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/portal_client.php';
require_once __DIR__ . '/../includes/portal_session.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payload inválido.']);
    exit;
}

// El endpoint del hospital valida todo (campos, captcha, rate-limit, duplicados).
$res = portal_api_call('POST', '/portal/guest/study-requests', $payload);
$j   = json_decode($res['raw'] ?: 'null', true);

// Si vino token (cuenta recién creada / autologin), iniciar sesión del portal y
// devolver un CSRF para que el navegador suba los documentos autenticado.
if (($res['status'] ?? 0) >= 200 && ($res['status'] ?? 0) < 300
    && is_array($j) && !empty($j['success']) && !empty($j['data']['token'])) {
    portal_login_session([
        'token'          => $j['data']['token'],
        'expires_in'     => $j['data']['expires_in'] ?? 3600,
        'patient'        => $j['data']['patient'] ?? null,
        'email_verified' => $j['data']['email_verified'] ?? false,
    ]);
    // No exponemos el JWT al navegador; sí el CSRF de la nueva sesión.
    unset($j['data']['token']);
    $j['data']['csrf_token'] = portal_csrf_token();
    http_response_code($res['status']);
    echo json_encode($j);
    exit;
}

http_response_code($res['status'] ?: 502);
echo $res['raw'] !== '' ? $res['raw'] : json_encode([
    'success' => false,
    'message' => $res['message'] ?? 'Error al contactar con la API.',
]);
