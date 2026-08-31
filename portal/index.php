<?php
require_once __DIR__ . '/_layout.php';

if (portal_is_logged_in()) {
    header('Location: ' . base_url('portal/dashboard.php'));
} else {
    // URL limpia: base_url('portal/login.php') provoca un 301 extra, y el start_url del
    // PWA pasa por aqui en cada arranque en frio.
    header('Location: ' . base_url('portal/login'));
}
exit;
