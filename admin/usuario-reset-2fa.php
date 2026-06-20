<?php
require __DIR__ . '/includes/auth.php';

if (!db_ready()) {
    header('Location: install.php');
    exit;
}

require_admin_permission('users');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: usuarios.php');
    exit;
}

verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    admin_ensure_security_schema();
    // Restablece el 2FA (forzará nueva inscripción), borra códigos de recuperación
    // viejos y de paso desbloquea la cuenta.
    db()->prepare('UPDATE admin_users SET totp_secret = NULL, totp_enabled = 0, recovery_codes = NULL, failed_attempts = 0, locked_until = NULL WHERE id = ?')
        ->execute([$id]);
    header('Location: usuarios.php?reset2fa=1');
    exit;
}

header('Location: usuarios.php');
exit;
