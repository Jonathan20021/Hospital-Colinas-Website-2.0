<?php
/**
 * Descarga/visualización de un adjunto del chat del PACIENTE, en streaming
 * desde el API interno con el token de sesión. El endpoint valida que el
 * adjunto pertenezca a un hilo del paciente (anti-IDOR). El navegador nunca
 * ve el token ni la URL interna.
 *
 * Uso: <img src="/api/portal-chat-file.php?id=123">
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/portal_client.php';
require_once __DIR__ . '/../includes/portal_session.php';

portal_session_start();
if (!portal_is_logged_in()) { http_response_code(401); exit; }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); exit; }

$bin = portal_api_call_binary('GET', "/portal/me/chat/attachments/$id", [], portal_token());
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
