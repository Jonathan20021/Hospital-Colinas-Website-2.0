<?php
/**
 * Proxy DICOMweb del sitio público (mismo origen) para el visor de imágenes
 * del PORTAL DEL PACIENTE. El visor llama  /api/portal-imaging-dwr.php/{scope}/{dwpath...}
 * y este reenvía —en streaming— al proxy interno de JENOFONTE con el JWT del
 * paciente (que vive en la sesión server-side). El navegador nunca ve el token
 * ni la IP interna del PACS. Solo GET; el acceso ya está acotado por el scope-token
 * firmado (ligado al paciente) y por la sesión del paciente.
 *
 * Aislado del proxy del médico (api/imaging-dwr.php): usa la sesión HGLC_PORTAL.
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/portal_client.php';
require_once __DIR__ . '/../includes/portal_session.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { http_response_code(405); exit; }
if (!portal_is_logged_in()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo '{"success":false,"message":"Sesión expirada."}';
    exit;
}

// PATH_INFO = /{scope}/{dwpath}; fallback: parsear REQUEST_URI tras el nombre del script
$pi = $_SERVER['PATH_INFO'] ?? '';
if ($pi === '') {
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
    $pos = stripos($uriPath, 'portal-imaging-dwr.php');
    if ($pos !== false) $pi = substr($uriPath, $pos + strlen('portal-imaging-dwr.php'));
}
$pi = ltrim($pi, '/');
if ($pi === '' || strpos($pi, '..') !== false) { http_response_code(400); exit; }

$qs     = $_SERVER['QUERY_STRING'] ?? '';
$target = rtrim(portal_api_base(), '/') . '/portal/imaging/dwr/' . $pi;
if ($qs !== '') $target .= '?' . $qs;

$accept = $_SERVER['HTTP_ACCEPT'] ?? '*/*';
$token  = portal_token();

while (ob_get_level() > 0) ob_end_clean();

$ch = curl_init($target);
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER     => ['Accept: ' . $accept, 'Authorization: Bearer ' . $token],
    CURLOPT_TIMEOUT        => 180,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_SSL_VERIFYPEER => portal_api_verify_tls(),
    CURLOPT_SSL_VERIFYHOST => portal_api_verify_tls() ? 2 : 0,
    CURLOPT_HEADERFUNCTION => function ($ch, $h) {
        $t = trim($h);
        if (stripos($t, 'HTTP/') === 0) {
            if (preg_match('#\s(\d{3})\b#', $t, $m)) http_response_code((int)$m[1]);
        } elseif (stripos($t, 'content-type:') === 0 || stripos($t, 'content-length:') === 0) {
            header($t, true);
        }
        return strlen($h);
    },
    CURLOPT_WRITEFUNCTION  => function ($ch, $data) { echo $data; flush(); return strlen($data); },
]);
curl_exec($ch);
curl_close($ch);
exit;
