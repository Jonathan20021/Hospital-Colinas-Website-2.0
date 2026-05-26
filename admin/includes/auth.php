<?php

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function admin_current_user(): ?array
{
    if (empty($_SESSION['admin_user_id']) || !db_ready()) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, name, email, role FROM admin_users WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([(int) $_SESSION['admin_user_id']]);
    $user = $stmt->fetch();

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

function admin_login(string $email, string $password): bool
{
    if (!db_ready()) {
        return false;
    }

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
