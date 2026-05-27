<?php
/**
 * Cliente HTTP server-side que habla con la API interna del hospital.
 *
 * Se configura en `includes/config.local.php` con:
 *   define('PORTAL_API_BASE',       'https://186.149.243.228:20443/api/v1');
 *   define('PORTAL_API_VERIFY_TLS', false);  // VIP usa cert autofirmado
 *
 * Se invoca server-side desde las páginas y desde el proxy AJAX.
 * El token JWT NUNCA cruza al navegador: vive en $_SESSION (cookie HttpOnly).
 */

// Cargar config.local.php de forma autónoma — las páginas del portal no
// dependen de db.php (que es donde el sitio público lo carga normalmente).
(static function (): void {
    $localConfig = __DIR__ . '/config.local.php';
    if (is_file($localConfig)) {
        require_once $localConfig;
    }
})();

function portal_api_base(): string {
    return defined('PORTAL_API_BASE') ? rtrim(PORTAL_API_BASE, '/') : 'http://localhost/api';
}

function portal_api_verify_tls(): bool {
    return !defined('PORTAL_API_VERIFY_TLS') || (bool)PORTAL_API_VERIFY_TLS;
}

/**
 * Realiza una llamada a la API.
 *
 * @return array { ok:bool, status:int, data:mixed, message:?string, errors:?array, raw:string }
 */
function portal_api_call(string $method, string $path, array $payload = [], ?string $token = null): array {
    $url = portal_api_base() . '/' . ltrim($path, '/');
    $ch  = curl_init($url);

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
    ];
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => portal_api_verify_tls(),
        CURLOPT_SSL_VERIFYHOST => portal_api_verify_tls() ? 2 : 0,
        CURLOPT_FOLLOWLOCATION => false,
    ];

    if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true) && $payload) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
    }
    if (strtoupper($method) === 'GET' && $payload) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($payload);
        curl_setopt($ch, CURLOPT_URL, $url);
    }

    curl_setopt_array($ch, $opts);
    $raw    = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'status' => 0, 'data' => null, 'message' => 'Error de conexión: ' . $err, 'errors' => null, 'raw' => ''];
    }

    $decoded = json_decode($raw, true);
    return [
        'ok'      => $status >= 200 && $status < 300 && !empty($decoded['success']),
        'status'  => $status,
        'data'    => $decoded['data']    ?? null,
        'message' => $decoded['message'] ?? null,
        'errors'  => $decoded['errors']  ?? null,
        'raw'     => $raw,
    ];
}
