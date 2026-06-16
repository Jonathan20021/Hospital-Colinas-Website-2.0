<?php
/**
 * Proxy de informes radiológicos (PDF) del sitio público (mismo origen) para el visor.
 * El navegador llama  /api/imaging-report.php/{scope}/{studyUID}?u=<unq>  y este reenvía
 * —en streaming— al endpoint interno de JENOFONTE con el JWT del médico (que vive en la
 * sesión server-side). El navegador nunca ve el token, el unq ni la IP interna del RIS.
 * Solo GET; el acceso está acotado por el scope-token firmado y por la sesión del médico,
 * y el unq se valida en JENOFONTE contra los informes reales del estudio autorizado.
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/portal_client.php';
require_once __DIR__ . '/../includes/doctor_portal_session.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { http_response_code(405); exit; }
if (!doctor_is_logged_in()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo '{"success":false,"message":"Sesión del médico expirada."}';
    exit;
}

// PATH_INFO = /{scope}/{studyUID}; fallback: parsear REQUEST_URI tras el nombre del script
$pi = $_SERVER['PATH_INFO'] ?? '';
if ($pi === '') {
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
    $pos = stripos($uriPath, 'imaging-report.php');
    if ($pos !== false) $pi = substr($uriPath, $pos + strlen('imaging-report.php'));
}
$pi = ltrim($pi, '/');
if ($pi === '' || strpos($pi, '..') !== false) { http_response_code(400); exit; }

$qs     = $_SERVER['QUERY_STRING'] ?? '';
$target = rtrim(portal_api_base(), '/') . '/portal-doctor/imaging/report/' . $pi;
if ($qs !== '') $target .= '?' . $qs;

$token = doctor_token();

while (ob_get_level() > 0) ob_end_clean();

$ch = curl_init($target);
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER     => ['Accept: application/pdf', 'Authorization: Bearer ' . $token],
    CURLOPT_TIMEOUT        => 120,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_SSL_VERIFYPEER => portal_api_verify_tls(),
    CURLOPT_SSL_VERIFYHOST => portal_api_verify_tls() ? 2 : 0,
    CURLOPT_HEADERFUNCTION => function ($ch, $h) {
        $t = trim($h);
        if (stripos($t, 'HTTP/') === 0) {
            if (preg_match('#\s(\d{3})\b#', $t, $m)) http_response_code((int)$m[1]);
        } elseif (stripos($t, 'content-type:') === 0 || stripos($t, 'content-length:') === 0 || stripos($t, 'content-disposition:') === 0) {
            header($t, true);
        }
        return strlen($h);
    },
    CURLOPT_WRITEFUNCTION  => function ($ch, $data) { echo $data; flush(); return strlen($data); },
]);
curl_exec($ch);
curl_close($ch);
exit;
