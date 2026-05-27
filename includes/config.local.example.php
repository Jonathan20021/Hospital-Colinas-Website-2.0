<?php

// Copy this file to includes/config.local.php on each environment
// and adjust the credentials. config.local.php is gitignored so it
// will never be pushed to GitHub.

putenv('DB_HOST=localhost');
putenv('DB_PORT=3306');
putenv('DB_NAME=your_database_name');
putenv('DB_USER=your_database_user');
putenv('DB_PASS=your_database_password');

// ── Portal de Pacientes ────────────────────────────────────────────────────
// Apunta a la API interna del hospital (VIP de Fortinet).
// Para desarrollo local con XAMPP: 'http://localhost/api/v1' (sin la VIP).
define('PORTAL_API_BASE', 'https://186.149.243.228:20443/api/v1');

// La VIP usa certificado autofirmado, por eso desactivamos la verificación TLS
// en las llamadas server-to-server. NO afecta a los pacientes: ellos hablan
// solo con tu dominio público (TLS válido). Pon true cuando la VIP tenga
// certificado de CA pública.
define('PORTAL_API_VERIFY_TLS', false);
