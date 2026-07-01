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
 * IP real del navegador para reenviar a JENOFONTE (Auditoría Web).
 * Usa CF-Connecting-IP (si el sitio estuviera tras Cloudflare) o REMOTE_ADDR.
 * NO confía en X-Forwarded-For del cliente (spoofeable; el sitio va directo al
 * cPanel, así que REMOTE_ADDR ya es la IP real).
 */
function portal_client_real_ip(): string {
    $ip = (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) !== false ? substr($ip, 0, 45) : '';
}

/** Cabeceras que reenvían identidad de red del navegador al API interno. */
function portal_client_fwd_headers(): array {
    $h = [];
    $ip = portal_client_real_ip();
    if ($ip !== '') $h[] = 'X-Client-IP: ' . $ip;
    if (!empty($_SERVER['HTTP_USER_AGENT'])) {
        $h[] = 'X-Client-UA: ' . substr(preg_replace('/[\r\n]/', '', (string)$_SERVER['HTTP_USER_AGENT']), 0, 255);
    }
    return $h;
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
    $headers = array_merge($headers, portal_client_fwd_headers());

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

/**
 * Variante para endpoints que devuelven contenido binario (PDF, imágenes).
 *
 * @return array { ok:bool, status:int, body:string, content_type:?string, filename:?string }
 */
function portal_api_call_binary(string $method, string $path, array $query = [], ?string $token = null): array {
    $url = portal_api_base() . '/' . ltrim($path, '/');
    if (strtoupper($method) === 'GET' && $query) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    $ch = curl_init($url);
    $headers = ['Accept: application/pdf, application/octet-stream, application/json'];
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;
    $headers = array_merge($headers, portal_client_fwd_headers());

    $respHeaders = [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => portal_api_verify_tls(),
        CURLOPT_SSL_VERIFYHOST => portal_api_verify_tls() ? 2 : 0,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADERFUNCTION => function ($ch, $h) use (&$respHeaders) {
            $len = strlen($h);
            $parts = explode(':', $h, 2);
            if (count($parts) === 2) {
                $respHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return $len;
        },
    ]);
    $raw    = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $filename = null;
    if (!empty($respHeaders['content-disposition']) &&
        preg_match('/filename="?([^";]+)"?/i', $respHeaders['content-disposition'], $m)) {
        $filename = $m[1];
    }

    return [
        'ok'           => $status >= 200 && $status < 300 && $raw !== false,
        'status'       => $status,
        'body'         => $raw !== false ? $raw : '',
        'content_type' => $respHeaders['content-type'] ?? null,
        'filename'     => $filename,
    ];
}
