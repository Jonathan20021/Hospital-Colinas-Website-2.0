<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/portal_client.php';
require_once __DIR__ . '/../includes/doctor_portal_session.php';

if (doctor_is_logged_in()) {
    // Invalidar (auditar) en el server interno
    portal_api_call('POST', '/portal-doctor/auth/logout', [], doctor_token());
}
doctor_portal_logout();

header('Location: ' . base_url('portal-medico/login.php'));
exit;
