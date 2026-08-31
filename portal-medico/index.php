<?php
require_once __DIR__ . '/_layout.php';

if (doctor_is_logged_in()) {
    header('Location: ' . base_url('portal-medico/dashboard.php'));
} else {
    // URL limpia: base_url('portal-medico/login.php') provoca un 301 extra, y el start_url del
    // PWA pasa por aqui en cada arranque en frio.
    header('Location: ' . base_url('portal-medico/login'));
}
exit;
