<?php
/**
 * Salir del modo soporte: destruye la sesión del portal del médico
 * (HGLC_DOCTOR_PORTAL) y vuelve al panel admin. La sesión del admin (su propia
 * cookie) sigue intacta, así que el admin aterriza logueado en su panel.
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/doctor_support.php';

doctor_support_close();
header('Location: ' . base_url('admin/soporte-medico.php'));
exit;
