<?php
/**
 * Manejo de sesión del paciente. Guarda el JWT del API solo en el server,
 * el navegador solo ve la cookie de sesión PHP.
 */

function portal_session_start(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('HGLC_PORTAL');
        session_start();

        // Aislamiento/seguridad del portal de pacientes: ninguna pagina debe
        // poder embeberse en un iframe (anti-clickjacking) ni ser indexada.
        // (Referrer-Policy y X-Content-Type-Options ya los fija el .htaccess
        // global del sitio.) Se emiten una sola vez por request, antes de la
        // salida.
        if (!headers_sent()) {
            header('X-Frame-Options: DENY');
            header("Content-Security-Policy: frame-ancestors 'none'");
            header('X-Robots-Tag: noindex, nofollow');
        }
    }
}

function portal_login_session(array $loginData): void {
    portal_session_start();
    $_SESSION['portal_token']      = $loginData['token']      ?? null;
    $_SESSION['portal_token_exp']  = time() + (int)($loginData['expires_in'] ?? 3600);
    $_SESSION['portal_patient']    = $loginData['patient']    ?? null;
    $_SESSION['portal_verified']   = !empty($loginData['email_verified']);
}

function portal_token(): ?string {
    portal_session_start();
    $t = $_SESSION['portal_token']     ?? null;
    $e = $_SESSION['portal_token_exp'] ?? 0;
    if (!$t || $e < time() - 30) return null;
    return $t;
}

function portal_patient(): ?array {
    portal_session_start();
    return $_SESSION['portal_patient'] ?? null;
}

function portal_is_logged_in(): bool {
    return portal_token() !== null;
}

function portal_email_verified(): bool {
    portal_session_start();
    return !empty($_SESSION['portal_verified']);
}

function portal_set_verified(bool $v): void {
    portal_session_start();
    $_SESSION['portal_verified'] = $v;
}

function portal_logout(): void {
    portal_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function portal_require_login(): void {
    if (!portal_is_logged_in()) {
        $here = $_SERVER['REQUEST_URI'] ?? '';
        $sep  = str_contains(base_url('portal/login.php'), '?') ? '&' : '?';
        header('Location: ' . base_url('portal/login.php') . $sep . 'next=' . urlencode($here));
        exit;
    }
}

function portal_flash_set(string $type, string $message): void {
    portal_session_start();
    $_SESSION['portal_flash'][] = ['type' => $type, 'message' => $message];
}

function portal_flash_get(): array {
    portal_session_start();
    $out = $_SESSION['portal_flash'] ?? [];
    unset($_SESSION['portal_flash']);
    return $out;
}

function portal_csrf_token(): string {
    portal_session_start();
    if (empty($_SESSION['portal_csrf'])) {
        $_SESSION['portal_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['portal_csrf'];
}

function portal_csrf_check(): void {
    portal_session_start();
    $sent  = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $known = $_SESSION['portal_csrf'] ?? '';
    if (!$known || !hash_equals($known, (string)$sent)) {
        http_response_code(419);
        exit('CSRF token inválido. Recarga la página.');
    }
}
