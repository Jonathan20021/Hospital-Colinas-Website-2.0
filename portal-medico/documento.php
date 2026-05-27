<?php
/**
 * Stream del PDF clinico del doctor.
 * Recibe ?appt=<id>&type=rx|diagnosis_lab|lab|procedures&theme=bw|color
 *
 * Llama a la API interna con el JWT del doctor (que vive en sesion server-side)
 * y devuelve los bytes del PDF al navegador. El JWT nunca llega al cliente.
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/portal_client.php';
require_once __DIR__ . '/../includes/doctor_portal_session.php';

doctor_require_login();

$apptId = (int)($_GET['appt'] ?? 0);
$type   = $_GET['type']  ?? 'rx';
$theme  = $_GET['theme'] ?? 'bw';
$disp   = $_GET['download'] ?? '' ? 'attachment' : 'inline';

if (!$apptId) {
    http_response_code(400);
    echo 'Falta el ID de la cita.';
    exit;
}

$validTypes = ['rx', 'diagnosis_lab', 'lab', 'procedures'];
if (!in_array($type, $validTypes, true)) {
    http_response_code(400);
    echo 'Tipo de documento invalido.';
    exit;
}

$res = portal_api_call_binary(
    'GET',
    '/portal-doctor/me/appointments/' . $apptId . '/document.pdf',
    ['type' => $type, 'theme' => $theme, 'disposition' => $disp],
    doctor_token()
);

if (!$res['ok']) {
    // Si el upstream devuelve JSON de error, mostrarlo legible
    $msg = 'No se pudo generar el PDF.';
    $decoded = json_decode($res['body'], true);
    if (is_array($decoded) && !empty($decoded['message'])) {
        $msg = $decoded['message'];
    }
    http_response_code($res['status'] ?: 502);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Error</title></head><body style="font-family:sans-serif;padding:2rem;color:#1e293b">';
    echo '<h1>No se pudo generar el documento</h1><p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><a href="javascript:history.back()">Volver atras</a></p></body></html>';
    exit;
}

$filename = $res['filename'] ?: ($type . '_' . $apptId . '.pdf');

while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: application/pdf');
header('Content-Disposition: ' . $disp . '; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Length: ' . strlen($res['body']));
echo $res['body'];
exit;
