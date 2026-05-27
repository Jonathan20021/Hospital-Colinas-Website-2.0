<?php
// Compat: /perfil → /cuenta (la edicion de perfil quedo removida del portal del medico).
// Hacemos el reescrito de SCRIPT_NAME igual que _layout.php para que base_url() resuelva la raiz real.
$_SERVER['SCRIPT_NAME'] = preg_replace(
    '#/portal-medico/[^/?]*\.php$#',
    '/index.php',
    $_SERVER['SCRIPT_NAME'] ?? '/index.php'
);
require_once __DIR__ . '/../includes/helpers.php';
header('Location: ' . base_url('portal-medico/cuenta.php'), true, 301);
exit;
