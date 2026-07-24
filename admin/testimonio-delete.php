<?php
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/testimonials.php';

if (!db_ready()) {
    header('Location: install.php');
    exit;
}

require_admin_permission('testimonials');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: testimonios.php');
    exit;
}

verify_csrf();
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id > 0) {
    testimonials_delete($id);
}

header('Location: testimonios.php?deleted=1');
exit;
