<?php
require __DIR__ . '/includes/auth.php';

require_admin_permission('doctors');
verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0 && db_ready()) {
    $stmt = db()->prepare('DELETE FROM doctors WHERE id = ?');
    $stmt->execute([$id]);
}

header('Location: medicos.php');
exit;
