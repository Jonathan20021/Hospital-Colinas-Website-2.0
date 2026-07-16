<?php
/**
 * Modo Soporte del Portal del Médico (impersonación asistida).
 *
 * Un admin autorizado del sitio público abre una sesión del portal de un médico
 * SIN OTP para darle soporte. El JWT lo acuña el API interno (endpoint
 * /portal-doctor/support/impersonate, gateado por IP de confianza + el secreto
 * DOCTOR_SUPPORT_SECRET). Aquí solo se llama a ese endpoint y se establece la
 * sesión del portal del médico + un marcador de soporte con límite de 30 min.
 *
 * Seguridad: el secreto vive en config.local.php (fuera del repo); nunca llega
 * al navegador. La autorización real (permiso + 2FA + re-auth) la hace la página
 * admin. Cada apertura queda auditada en el API interno (audit_logs).
 *
 * Ver includes/doctor_portal_session.php y el backend en JENOFONTE.
 */

require_once __DIR__ . '/portal_client.php';        // carga config.local.php (PORTAL_API_BASE + DOCTOR_SUPPORT_SECRET) de forma autónoma
require_once __DIR__ . '/doctor_portal_session.php';

const DOCTOR_SUPPORT_MAX_SECONDS = 1800; // 30 minutos — límite duro de la sesión de soporte

/** Llama a un endpoint de soporte del API interno con el secreto compartido. */
function doctor_support_api(string $method, string $path, array $payload = []): array {
    if (!defined('DOCTOR_SUPPORT_SECRET') || DOCTOR_SUPPORT_SECRET === '') {
        return ['ok' => false, 'status' => 0, 'message' => 'Modo soporte no configurado (falta DOCTOR_SUPPORT_SECRET en config.local.php).', 'data' => null];
    }
    $url = portal_api_base() . '/' . ltrim($path, '/');
    $headers = ['Accept: application/json', 'Content-Type: application/json', 'X-Support-Secret: ' . DOCTOR_SUPPORT_SECRET];
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
    if (strtoupper($method) === 'GET' && $payload) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($payload);
    } elseif ($payload) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, $opts);
    $raw    = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);
    if ($raw === false) return ['ok' => false, 'status' => 0, 'message' => 'Error de conexión con el API interno: ' . $err, 'data' => null];
    $j = json_decode($raw, true);
    return [
        'ok'      => $status >= 200 && $status < 300 && !empty($j['success']),
        'status'  => $status,
        'message' => $j['message'] ?? null,
        'data'    => $j['data'] ?? null,
    ];
}

/**
 * Abre una sesión de SOPORTE del médico: pide el token al API interno y
 * establece la sesión del portal del médico (cookie HGLC_DOCTOR_PORTAL),
 * cerrando primero la sesión actual (la del admin) en el mismo request.
 *
 * @param int   $doctorId  id del médico en JENOFONTE (doctors.id)
 * @param array $admin     ['id'=>int, 'name'=>string] admin que abre el soporte
 * @return array ['ok'=>bool, 'message'=>?string]
 */
function doctor_support_open(int $doctorId, array $admin): array {
    $res = doctor_support_api('POST', 'portal-doctor/support/impersonate', [
        'doctor_id'  => $doctorId,
        'admin_id'   => (int)($admin['id'] ?? 0),
        'admin_name' => (string)($admin['name'] ?? ''),
    ]);
    if (!$res['ok'] || empty($res['data']['token'])) {
        return ['ok' => false, 'message' => $res['message'] ?? 'No se pudo iniciar la sesión de soporte.'];
    }
    $d = $res['data'];

    // Cambiar de la sesión del admin a la del médico dentro del mismo request:
    // cerramos la del admin (PHPSESSID) y abrimos/creamos la del médico (HGLC_DOCTOR_PORTAL).
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    doctor_portal_session_start();
    $_SESSION = [];  // sesión de médico limpia (aislada de cualquier otra)
    $_SESSION['doctor_token']     = $d['token'];
    $_SESSION['doctor_token_exp'] = time() + DOCTOR_SUPPORT_MAX_SECONDS;   // límite duro 30 min (reusa doctor_token())
    $_SESSION['doctor']           = $d['doctor'] ?? null;
    $_SESSION['doctor_support']   = [
        'admin_id'    => (int)($admin['id'] ?? 0),
        'admin_name'  => (string)($admin['name'] ?? ''),
        'doctor_id'   => $doctorId,
        'doctor_name' => trim((string)($d['doctor']['name'] ?? '')),
        'started'     => time(),
    ];
    session_write_close();
    return ['ok' => true];
}

/** Marcador de soporte activo (llamar dentro de la sesión del médico). null si no hay. */
function doctor_support_active(): ?array {
    doctor_portal_session_start();
    $s = $_SESSION['doctor_support'] ?? null;
    return is_array($s) ? $s : null;
}

/** Cierra la sesión de soporte del médico (destruye la sesión HGLC_DOCTOR_PORTAL). */
function doctor_support_close(): void {
    doctor_portal_logout();
}
