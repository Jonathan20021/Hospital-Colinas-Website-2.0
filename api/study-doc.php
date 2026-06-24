<?php
/**
 * Descarga/visualización de un documento de una solicitud de estudios del
 * PACIENTE, en streaming desde la API interna con el token de sesión. El
 * endpoint interno valida que el documento pertenezca a una solicitud del
 * propio paciente (anti-IDOR). El navegador nunca ve el token ni la URL interna.
 *
 * Uso: <img src="/api/study-doc.php?req=124&doc=55">
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/portal_client.php';
require_once __DIR__ . '/../includes/portal_session.php';

portal_session_start();
if (!portal_is_logged_in()) { http_response_code(401); exit; }

$req = (int)($_GET['req'] ?? 0);
$doc = (int)($_GET['doc'] ?? 0);
if ($req <= 0 || $doc <= 0) { http_response_code(404); exit; }

$bin = portal_api_call_binary('GET', "/portal/me/study-requests/$req/documents/$doc", [], portal_token());
if (empty($bin['ok']) || empty($bin['body'])) { http_response_code(404); exit; }

if (!headers_sent()) {
    header('Content-Type: ' . ($bin['content_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: inline; filename="' . ($bin['filename'] ?: 'documento') . '"');
    header('Content-Length: ' . strlen($bin['body']));
    header('Cache-Control: private, max-age=600');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');
}
echo $bin['body'];
exit;
