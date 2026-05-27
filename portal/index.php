<?php
require_once __DIR__ . '/_layout.php';

if (portal_is_logged_in()) {
    header('Location: ' . base_url('portal/dashboard.php'));
} else {
    header('Location: ' . base_url('portal/login.php'));
}
exit;
