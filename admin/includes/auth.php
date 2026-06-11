<?php

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
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

function admin_login(string $email, string $password): bool
{
    if (!db_ready()) {
        return false;
    }

    admin_ensure_permissions_schema();

    $stmt = db()->prepare('SELECT * FROM admin_users WHERE email = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([trim($email)]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    $_SESSION['admin_user_id'] = (int) $user['id'];
    db()->prepare('UPDATE admin_users SET last_login = NOW() WHERE id = ?')->execute([(int) $user['id']]);

    return true;
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
