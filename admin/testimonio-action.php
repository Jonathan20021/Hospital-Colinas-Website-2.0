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
$do = (string) ($_POST['do'] ?? '');

$back = 'testimonios.php';
if ($id > 0) {
    switch ($do) {
        case 'publish':
            testimonials_set_status($id, 'published');
            $back .= '?approved=1';
            break;
        case 'reject':
            testimonials_set_status($id, 'rejected');
            $back .= '?status=pending';
            break;
        case 'pending':
            testimonials_set_status($id, 'pending');
            $back .= '?status=pending';
            break;
        case 'feature':
            testimonials_toggle_featured($id);
            break;
    }
} elseif ($do === 'load_demo') {
    testimonials_load_demo();
    $back .= '?saved=demo';
}

header('Location: ' . $back);
exit;
