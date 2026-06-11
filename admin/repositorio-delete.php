<?php
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/repository.php';

if (!db_ready()) {
    header('Location: install.php');
    exit;
}

require_admin_permission('repository');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: repositorio.php');
    exit;
}

verify_csrf();
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id > 0) {
    repo_delete($id);
}

header('Location: repositorio.php?deleted=1');
exit;
