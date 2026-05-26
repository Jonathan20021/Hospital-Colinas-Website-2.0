<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/users-admin.php';

if (!db_ready()) {
    header('Location: install.php');
    exit;
}

$currentUser = require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: usuarios.php');
    exit;
}

verify_csrf();

try {
    admin_delete_user((int) ($_POST['id'] ?? 0), (int) $currentUser['id']);
    header('Location: usuarios.php?deleted=1');
} catch (Throwable $exception) {
    header('Location: usuarios.php?error=' . urlencode($exception->getMessage()));
}
exit;
