<?php
/**
 * Sesion del Portal del Doctor. JWT del API vive solo en el server publico,
 * el navegador solo ve la cookie de sesion PHP.
 *
 * Aislada de la sesion del Portal de Pacientes (cookie con otro nombre,
 * HGLC_DOCTOR_PORTAL) para que un paciente que abra una pestana del
 * portal de doctor (o viceversa) no pueda colisionar.
 */

function doctor_portal_session_start(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('HGLC_DOCTOR_PORTAL');
        session_start();
    }
}

function doctor_portal_login_session(array $loginData): void {
    doctor_portal_session_start();
    $_SESSION['doctor_token']     = $loginData['token']  ?? null;
    $_SESSION['doctor_token_exp'] = time() + (int)($loginData['expires_in'] ?? 3600);
    $_SESSION['doctor']           = $loginData['doctor'] ?? null;
}

function doctor_token(): ?string {
    doctor_portal_session_start();
    $t = $_SESSION['doctor_token']     ?? null;
    $e = $_SESSION['doctor_token_exp'] ?? 0;
    if (!$t || $e < time() - 30) return null;
    return $t;
}

function doctor_current(): ?array {
    doctor_portal_session_start();
    return $_SESSION['doctor'] ?? null;
}

function doctor_is_logged_in(): bool {
    return doctor_token() !== null;
}

function doctor_portal_logout(): void {
    doctor_portal_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function doctor_require_login(): void {
    if (!doctor_is_logged_in()) {
        $here = $_SERVER['REQUEST_URI'] ?? '';
        $sep  = str_contains(base_url('portal-medico/login.php'), '?') ? '&' : '?';
        header('Location: ' . base_url('portal-medico/login.php') . $sep . 'next=' . urlencode($here));
        exit;
    }
}

function doctor_flash_set(string $type, string $message): void {
    doctor_portal_session_start();
    $_SESSION['doctor_flash'][] = ['type' => $type, 'message' => $message];
}

function doctor_flash_get(): array {
    doctor_portal_session_start();
    $out = $_SESSION['doctor_flash'] ?? [];
    unset($_SESSION['doctor_flash']);
    return $out;
}

function doctor_csrf_token(): string {
    doctor_portal_session_start();
    if (empty($_SESSION['doctor_csrf'])) {
        $_SESSION['doctor_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['doctor_csrf'];
}

function doctor_csrf_check(): void {
    doctor_portal_session_start();
    $sent  = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $known = $_SESSION['doctor_csrf'] ?? '';
    if (!$known || !hash_equals($known, (string)$sent)) {
        http_response_code(419);
        exit('CSRF token invalido. Recarga la pagina.');
    }
}

/** Cookie del "dispositivo confiable" (omite 2FA durante N dias). */
function doctor_trusted_device_token(): ?string {
    return $_COOKIE['HGLC_DOC_TRUST'] ?? null;
}

function doctor_set_trusted_device(string $token, ?string $expiresAt): void {
    if (!$token) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $exp = $expiresAt ? strtotime($expiresAt) : (time() + 30 * 86400);
    setcookie('HGLC_DOC_TRUST', $token, [
        'expires'  => $exp,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function doctor_clear_trusted_device(): void {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('HGLC_DOC_TRUST', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE['HGLC_DOC_TRUST']);
}
