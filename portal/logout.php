<?php
require_once __DIR__ . '/_layout.php';

if (portal_is_logged_in()) {
    portal_api_call('POST', '/portal/auth/logout', [], portal_token());
    portal_logout();
}

header('Location: ' . base_url('portal/login.php'));
exit;
