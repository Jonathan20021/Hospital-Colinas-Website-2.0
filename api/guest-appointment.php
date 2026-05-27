<?php
/**
 * Proxy server-side: recibe POST del wizard de agendar como invitado y
 * lo reenvía a la API interna del hospital. El navegador NUNCA habla
 * directo con la VIP del hospital.
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/portal_client.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payload inválido.']);
    exit;
}

// El endpoint del hospital ya valida todo (campos, captcha, conflictos, rate limit).
$res = portal_api_call('POST', '/portal/guest/appointments', $payload);

http_response_code($res['status'] ?: 502);
echo $res['raw'] !== '' ? $res['raw'] : json_encode([
    'success' => false,
    'message' => $res['message'] ?? 'Error al contactar con la API.',
]);
