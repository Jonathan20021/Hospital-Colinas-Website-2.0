<?php
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/news.php';

if (!db_ready()) {
    header('Location: install.php');
    exit;
}

require_admin_permission('news');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: noticias.php');
    exit;
}

verify_csrf();
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id > 0) {
    news_delete($id);
}

header('Location: noticias.php?deleted=1');
exit;
