<?php
/**
 * Sirve el PDF de una receta del paciente, en streaming desde el API interno
 * con el token de sesión del paciente. El endpoint valida que la receta sea
 * suya (anti-IDOR). El navegador nunca ve el token ni la URL interna.
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/portal_client.php';
require_once __DIR__ . '/../includes/portal_session.php';

portal_session_start();
if (!portal_is_logged_in()) {
    header('Location: ' . base_url('portal/login.php'));
    exit;
}

$note = (int)($_GET['note'] ?? 0);
$dl   = !empty($_GET['dl']);
if ($note <= 0) { header('Location: ' . base_url('portal/recetas.php')); exit; }

$bin = portal_api_call_binary('GET', "/portal/me/prescriptions/$note.pdf",
    ['disposition' => $dl ? 'attachment' : 'inline'], portal_token());

if (empty($bin['ok']) || stripos((string)($bin['content_type'] ?? ''), 'pdf') === false) {
    portal_flash_set('error', 'No pudimos abrir esa receta. Intenta de nuevo más tarde.');
    header('Location: ' . base_url('portal/recetas.php'));
    exit;
}

if (!headers_sent()) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($dl ? 'attachment' : 'inline') . '; filename="' . ($bin['filename'] ?: 'receta.pdf') . '"');
    header('Content-Length: ' . strlen($bin['body']));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Robots-Tag: noindex, nofollow');
}
echo $bin['body'];
exit;
