<?php
/**
 * Descarga/visualización de un adjunto del chat del MÉDICO, en streaming desde
 * el API interno con el token de sesión del doctor. El endpoint valida que el
 * adjunto pertenezca a un hilo del médico (anti-IDOR).
 *
 * Uso: <img src="/api/doctor-chat-file.php?id=123">
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/portal_client.php';
require_once __DIR__ . '/../includes/doctor_portal_session.php';

doctor_portal_session_start();
if (!doctor_is_logged_in()) { http_response_code(401); exit; }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); exit; }

$bin = portal_api_call_binary('GET', "/portal-doctor/me/chat/attachments/$id", [], doctor_token());
if (empty($bin['ok']) || empty($bin['body'])) { http_response_code(404); exit; }

if (!headers_sent()) {
    header('Content-Type: ' . ($bin['content_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: inline; filename="' . ($bin['filename'] ?: 'archivo') . '"');
    header('Content-Length: ' . strlen($bin['body']));
    header('Cache-Control: private, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');
}
echo $bin['body'];
exit;
