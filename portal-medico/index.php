<?php
require_once __DIR__ . '/_layout.php';

if (doctor_is_logged_in()) {
    header('Location: ' . base_url('portal-medico/dashboard.php'));
} else {
    header('Location: ' . base_url('portal-medico/login.php'));
}
exit;
