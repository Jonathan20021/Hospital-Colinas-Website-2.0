<?php

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/totp.php';

// Política de seguridad del 2FA del admin.
const ADMIN_MAX_FAILED   = 5;     // intentos fallidos antes de bloquear
const ADMIN_LOCK_MINUTES = 15;    // duración del bloqueo
const ADMIN_2FA_TTL      = 300;   // segundos de validez del paso 2FA pendiente

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $secure,
    ]);
    session_start();
}

function admin_permission_definitions(): array
{
    return [
        'dashboard' => [
            'label' => 'Dashboard',
            'description' => 'Ver resumen general del panel.',
            'href' => 'index.php',
            'icon' => 'layout-dashboard',
            'active' => 'dashboard',
        ],
        'doctors' => [
            'label' => 'Médicos',
            'description' => 'Gestionar perfiles médicos, especialidades y publicación.',
            'href' => 'medicos.php',
            'icon' => 'user-round-search',
            'active' => 'medicos',
        ],
        'news' => [
            'label' => 'Noticias',
            'description' => 'Crear, editar y eliminar noticias de la sala de prensa.',
            'href' => 'noticias.php',
            'icon' => 'newspaper',
            'active' => 'noticias',
        ],
        'repository' => [
            'label' => 'Repositorio',
            'description' => 'Gestionar los protocolos y guías del Repositorio Digital.',
            'href' => 'repositorio.php',
            'icon' => 'library',
            'active' => 'repositorio',
        ],
        'users' => [
            'label' => 'Usuarios admin',
            'description' => 'Administrar cuentas, roles y permisos del panel.',
            'href' => 'usuarios.php',
            'icon' => 'shield-user',
            'active' => 'usuarios',
        ],
        'audit' => [
            'label' => 'Auditoría',
            'description' => 'Bitácora de accesos a datos de pacientes (PHI) desde los portales.',
            'href' => 'auditoria.php',
            'icon' => 'history',
            'active' => 'auditoria',
        ],
        'ai' => [
            'label' => 'Colinas IA',
            'description' => 'Configurar el asistente virtual, modelo y credenciales.',
            'href' => 'ai-settings.php',
            'icon' => 'sparkles',
            'active' => 'ai',
        ],
    ];
}

function admin_all_permission_keys(): array
{
    return array_keys(admin_permission_definitions());
}

function admin_default_permissions_for_role(string $role): array
{
    return $role === 'admin'
        ? admin_all_permission_keys()
        : ['dashboard', 'doctors', 'news'];
}

function admin_ensure_permissions_schema(): void
{
    static $ensured = false;
    if ($ensured || !db()) {
        return;
    }

    try {
        $check = db()->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_users' AND COLUMN_NAME = 'permissions'");
        $check->execute();
        if ((int) $check->fetchColumn() === 0) {
            db()->exec('ALTER TABLE admin_users ADD COLUMN permissions TEXT NULL AFTER role');
        }
        $ensured = true;
    } catch (Throwable) {
        $ensured = true;
    }
}

function admin_ensure_security_schema(): void
{
    static $ensured = false;
    if ($ensured || !db()) {
        return;
    }

    $columns = [
        'totp_secret'     => 'VARCHAR(64) NULL',
        'totp_enabled'    => 'TINYINT(1) NOT NULL DEFAULT 0',
        'failed_attempts' => 'INT NOT NULL DEFAULT 0',
        'locked_until'    => 'DATETIME NULL',
        'recovery_codes'  => 'TEXT NULL',
    ];
    try {
        foreach ($columns as $col => $definition) {
            $check = db()->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_users' AND COLUMN_NAME = ?");
            $check->execute([$col]);
            if ((int) $check->fetchColumn() === 0) {
                db()->exec("ALTER TABLE admin_users ADD COLUMN {$col} {$definition}");
            }
        }
        $ensured = true;
    } catch (Throwable) {
        $ensured = true;
    }
}

function admin_normalize_permissions(mixed $permissions, string $role = 'editor'): array
{
    $allowed = admin_all_permission_keys();

    if (is_string($permissions)) {
        $decoded = json_decode($permissions, true);
        $permissions = is_array($decoded) ? $decoded : [];
    }

    if (!is_array($permissions) || $permissions === []) {
        $permissions = admin_default_permissions_for_role($role);
    }

    $permissions = array_values(array_unique(array_filter($permissions, static fn ($permission): bool => in_array($permission, $allowed, true))));

    return $permissions;
}

function admin_user_permissions(array $user): array
{
    if (($user['role'] ?? '') === 'admin') {
        return admin_all_permission_keys();
    }

    return admin_normalize_permissions($user['permissions'] ?? [], (string) ($user['role'] ?? 'editor'));
}

function admin_can(string $permission, ?array $user = null): bool
{
    $user ??= admin_current_user();
    if (!$user) {
        return false;
    }

    return in_array($permission, admin_user_permissions($user), true);
}

function admin_first_allowed_url(?array $user = null): string
{
    $user ??= admin_current_user();
    if (!$user) {
        return 'login.php';
    }

    foreach (admin_permission_definitions() as $key => $definition) {
        if (admin_can($key, $user)) {
            return $definition['href'];
        }
    }

    return 'logout.php';
}

function admin_current_user(): ?array
{
    if (empty($_SESSION['admin_user_id']) || !db_ready()) {
        return null;
    }

    admin_ensure_permissions_schema();

    $stmt = db()->prepare('SELECT id, name, email, role, permissions FROM admin_users WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([(int) $_SESSION['admin_user_id']]);
    $user = $stmt->fetch();

    if ($user) {
        $user['permissions_list'] = admin_user_permissions($user);
    }

    return $user ?: null;
}

function require_admin(): array
{
    $user = admin_current_user();
    if (!$user) {
        header('Location: login.php');
        exit;
    }

    return $user;
}

function require_admin_permission(string $permission): array
{
    $user = require_admin();
    if (!admin_can($permission, $user)) {
        http_response_code(403);
        exit('No tienes permiso para acceder a esta sección del panel.');
    }

    return $user;
}

/**
 * Paso 1: valida correo + contraseña con lockout por fuerza bruta.
 * NO inicia sesión (el acceso real ocurre tras el 2FA).
 * @return array{status:string, user?:array, retry_minutes?:int}  status: 'ok'|'invalid'|'locked'
 */
function admin_check_credentials(string $email, string $password): array
{
    if (!db_ready()) {
        return ['status' => 'invalid'];
    }
    admin_ensure_permissions_schema();
    admin_ensure_security_schema();

    $stmt = db()->prepare('SELECT * FROM admin_users WHERE email = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([trim($email)]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['status' => 'invalid'];
    }

    if (!empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time()) {
        $mins = (int) ceil((strtotime((string) $user['locked_until']) - time()) / 60);
        return ['status' => 'locked', 'retry_minutes' => max(1, $mins)];
    }

    if (!password_verify($password, $user['password_hash'])) {
        $attempts = (int) ($user['failed_attempts'] ?? 0) + 1;
        if ($attempts >= ADMIN_MAX_FAILED) {
            db()->prepare('UPDATE admin_users SET failed_attempts = 0, locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?')
                ->execute([ADMIN_LOCK_MINUTES, (int) $user['id']]);
            return ['status' => 'locked', 'retry_minutes' => ADMIN_LOCK_MINUTES];
        }
        db()->prepare('UPDATE admin_users SET failed_attempts = ? WHERE id = ?')->execute([$attempts, (int) $user['id']]);
        return ['status' => 'invalid'];
    }

    db()->prepare('UPDATE admin_users SET failed_attempts = 0, locked_until = NULL WHERE id = ?')->execute([(int) $user['id']]);
    return ['status' => 'ok', 'user' => $user];
}

/** Inicia la sesión real (tras el 2FA). Regenera el id de sesión (anti-fijación). */
function admin_complete_login(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['admin_user_id'] = (int) $user['id'];
    unset($_SESSION['admin_2fa_user_id'], $_SESSION['admin_2fa_time']);
    db()->prepare('UPDATE admin_users SET last_login = NOW() WHERE id = ?')->execute([(int) $user['id']]);
}

function admin_totp_secret(int $userId): ?string
{
    admin_ensure_security_schema();
    $stmt = db()->prepare('SELECT totp_secret FROM admin_users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $s = $stmt->fetchColumn();
    return $s ? (string) $s : null;
}

function admin_totp_is_enabled(int $userId): bool
{
    admin_ensure_security_schema();
    $stmt = db()->prepare('SELECT totp_enabled FROM admin_users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn() === 1;
}

/** Genera y guarda un secreto nuevo (sin activar). Devuelve el secreto para el QR. */
function admin_begin_enrollment(int $userId): array
{
    admin_ensure_security_schema();
    $secret = totp_generate_secret();
    db()->prepare('UPDATE admin_users SET totp_secret = ?, totp_enabled = 0 WHERE id = ?')->execute([$secret, $userId]);
    return ['secret' => $secret];
}

/** Confirma la inscripción: verifica el código y activa el 2FA. */
function admin_confirm_enrollment(int $userId, string $code): bool
{
    $secret = admin_totp_secret($userId);
    if (!$secret || !totp_verify($secret, $code)) {
        return false;
    }
    db()->prepare('UPDATE admin_users SET totp_enabled = 1 WHERE id = ?')->execute([$userId]);
    return true;
}

/** Verifica el código TOTP en el login (2FA ya activado). */
function admin_verify_login_totp(int $userId, string $code): bool
{
    if (!admin_totp_is_enabled($userId)) {
        return false;
    }
    $secret = admin_totp_secret($userId);
    return $secret && totp_verify($secret, $code);
}

// ── Códigos de recuperación (entrar sin el teléfono) ────────────────────────

function admin_normalize_recovery_code(string $code): string
{
    return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $code));
}

/** Genera N códigos de un solo uso, guarda sus hashes y devuelve los códigos en claro (mostrar una vez). */
function admin_generate_recovery_codes(int $userId, int $count = 8): array
{
    admin_ensure_security_schema();
    $plain = [];
    $hashes = [];
    for ($i = 0; $i < $count; $i++) {
        $raw  = strtoupper(bin2hex(random_bytes(5)));   // 10 hex
        $code = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
        $plain[]  = $code;
        $hashes[] = password_hash(admin_normalize_recovery_code($code), PASSWORD_BCRYPT);
    }
    db()->prepare('UPDATE admin_users SET recovery_codes = ? WHERE id = ?')->execute([json_encode($hashes), $userId]);
    return $plain;
}

/** Consume un código de recuperación válido (lo elimina). Devuelve true si era válido. */
function admin_consume_recovery_code(int $userId, string $code): bool
{
    admin_ensure_security_schema();
    $norm = admin_normalize_recovery_code($code);
    if (strlen($norm) < 8) {
        return false;
    }
    $stmt = db()->prepare('SELECT recovery_codes FROM admin_users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $hashes = json_decode((string) ($stmt->fetchColumn() ?: '[]'), true);
    if (!is_array($hashes)) {
        return false;
    }
    foreach ($hashes as $idx => $h) {
        if (is_string($h) && password_verify($norm, $h)) {
            unset($hashes[$idx]);
            db()->prepare('UPDATE admin_users SET recovery_codes = ? WHERE id = ?')
                ->execute([json_encode(array_values($hashes)), $userId]);
            return true;
        }
    }
    return false;
}

function admin_recovery_codes_remaining(int $userId): int
{
    admin_ensure_security_schema();
    $stmt = db()->prepare('SELECT recovery_codes FROM admin_users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $hashes = json_decode((string) ($stmt->fetchColumn() ?: '[]'), true);
    return is_array($hashes) ? count($hashes) : 0;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Token de seguridad inválido.');
    }
}
