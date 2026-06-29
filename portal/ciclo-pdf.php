<?php
/**
 * Sirve el PDF "Mi Ciclo — Resumen para la consulta" en streaming desde el API
 * interno con el token de sesión de la paciente. El endpoint genera el PDF
 * server-side (Dompdf, marca HGLC) y valida que sea de la propia paciente.
 * El navegador nunca ve el token ni la URL interna.
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/portal_client.php';
require_once __DIR__ . '/../includes/portal_session.php';

portal_session_start();
if (!portal_is_logged_in()) {
    header('Location: ' . base_url('portal/login.php'));
    exit;
}

$dl = !empty($_GET['dl']);

$bin = portal_api_call_binary('GET', '/portal/me/cycle/summary.pdf',
    ['disposition' => $dl ? 'attachment' : 'inline'], portal_token());

if (empty($bin['ok']) || stripos((string) ($bin['content_type'] ?? ''), 'pdf') === false) {
    portal_flash_set('error', 'No pudimos generar tu resumen. Intenta de nuevo más tarde.');
    header('Location: ' . base_url('portal/ciclo.php'));
    exit;
}

if (!headers_sent()) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($dl ? 'attachment' : 'inline') . '; filename="' . ($bin['filename'] ?: 'mi_ciclo_resumen.pdf') . '"');
    header('Content-Length: ' . strlen($bin['body']));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Robots-Tag: noindex, nofollow');
}
echo $bin['body'];
exit;
