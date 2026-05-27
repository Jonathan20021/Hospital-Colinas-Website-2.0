<?php
/**
 * Layout compartido para todas las paginas del Portal del Doctor.
 * Uso:
 *   doctor_layout_begin($title, $active);
 *     // contenido HTML
 *   doctor_layout_end();
 */

// El portal del doctor vive bajo /portal-medico/. Igual que en /portal/ del paciente,
// reescribimos SCRIPT_NAME para que base_url() del sitio publico calcule '/'
// como la raiz real.
$_SERVER['_DOCTOR_PORTAL_ORIG_SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '';
$_SERVER['SCRIPT_NAME'] = preg_replace(
    '#/portal-medico/[^/?]*\.php$#',
    '/index.php',
    $_SERVER['SCRIPT_NAME'] ?? '/index.php'
);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/content.php';
require_once __DIR__ . '/../includes/public-layout.php';
require_once __DIR__ . '/../includes/portal_client.php';
require_once __DIR__ . '/../includes/doctor_portal_session.php';
require_once __DIR__ . '/../includes/doctor_portal_avatars.php';

function doctor_layout_begin(string $title, string $active = ''): void {
    doctor_portal_session_start();
    global $assets, $contact;
    $assetVersion = (string) max(
        filemtime(__DIR__ . '/../assets/css/app.css'),
        filemtime(__DIR__ . '/../assets/js/app.js'),
        @filemtime(__DIR__ . '/../assets/css/portal.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/css/portal-medico.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/js/portal-medico.js') ?: 0
    );
    ?>
    <!DOCTYPE html>
    <html lang="es-DO">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($title) ?> | Portal Medico - Hospital Las Colinas</title>
        <meta name="robots" content="noindex, nofollow">
        <meta name="theme-color" content="#0f766e">
        <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($assetVersion) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= e($assetVersion) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/portal.css')) ?>?v=<?= e($assetVersion) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/portal-medico.css')) ?>?v=<?= e($assetVersion) ?>">
        <meta name="csrf-token" content="<?= e(doctor_csrf_token()) ?>">
    </head>
    <body class="bg-slate-50 font-sans text-slate-950 antialiased portal-page doctor-portal-page">
        <a class="skip-link" href="#contenido">Saltar al contenido</a>
        <?php render_public_header($assets, $contact, ''); ?>

        <div class="doctor-shell <?= doctor_is_logged_in() ? 'doctor-shell-app' : 'doctor-shell-auth' ?>">
            <?php if (doctor_is_logged_in()):
                $doctor = doctor_current() ?? [];
                $dName  = (string)($doctor['name'] ?? '');
                $friendlyName = trim(mb_convert_case(mb_strtolower($dName, 'UTF-8'), MB_CASE_TITLE, 'UTF-8'));
                $specialty = (string)($doctor['specialty'] ?? '');
                [$avc1, $avc2] = doctor_avatar_palette($dName);
                $avInitials = doctor_initials($dName);
            ?>
                <aside class="doctor-sidebar" aria-label="Menu del medico">
                    <div class="doctor-profile">
                        <div class="doctor-avatar" style="background: linear-gradient(135deg, <?= e($avc1) ?>, <?= e($avc2) ?>);"><?= e($avInitials) ?></div>
                        <div class="doctor-profile-text">
                            <p class="doctor-greeting">Dr/a.</p>
                            <p class="doctor-name" title="<?= e($friendlyName) ?>"><?= e($friendlyName) ?></p>
                            <?php if ($specialty): ?><p class="doctor-specialty"><?= e($specialty) ?></p><?php endif; ?>
                        </div>
                    </div>
                    <nav class="doctor-nav" aria-label="Navegacion del portal del medico">
                        <a href="<?= e(base_url('portal-medico/dashboard.php')) ?>" class="doctor-nav-link <?= $active === 'dashboard' ? 'is-active' : '' ?>"><i data-lucide="layout-dashboard" class="h-4 w-4"></i>Inicio</a>
                        <a href="<?= e(base_url('portal-medico/agenda.php')) ?>" class="doctor-nav-link <?= $active === 'agenda' ? 'is-active' : '' ?>"><i data-lucide="calendar-days" class="h-4 w-4"></i>Mi agenda</a>
                        <a href="<?= e(base_url('portal-medico/pacientes.php')) ?>" class="doctor-nav-link <?= $active === 'pacientes' ? 'is-active' : '' ?>"><i data-lucide="users" class="h-4 w-4"></i>Pacientes</a>
                        <a href="<?= e(base_url('portal-medico/consulta.php')) ?>" class="doctor-nav-link <?= $active === 'consulta' ? 'is-active' : '' ?>"><i data-lucide="stethoscope" class="h-4 w-4"></i>Consulta</a>
                        <a href="<?= e(base_url('portal-medico/disponibilidad.php')) ?>" class="doctor-nav-link <?= $active === 'disponibilidad' ? 'is-active' : '' ?>"><i data-lucide="calendar-clock" class="h-4 w-4"></i>Disponibilidad</a>
                        <a href="<?= e(base_url('portal-medico/analytics.php')) ?>" class="doctor-nav-link <?= $active === 'analytics' ? 'is-active' : '' ?>"><i data-lucide="bar-chart-3" class="h-4 w-4"></i>Analytics</a>
                        <a href="<?= e(base_url('portal-medico/perfil.php')) ?>" class="doctor-nav-link <?= $active === 'perfil' ? 'is-active' : '' ?>"><i data-lucide="user-cog" class="h-4 w-4"></i>Mi perfil</a>
                        <a href="<?= e(base_url('portal-medico/logout.php')) ?>" class="doctor-nav-link doctor-nav-logout"><i data-lucide="log-out" class="h-4 w-4"></i>Cerrar sesion</a>
                    </nav>
                    <div class="doctor-sidebar-foot">
                        <i data-lucide="shield-check" class="h-3 w-3"></i> Conexion segura
                    </div>
                </aside>
            <?php endif; ?>

            <main id="contenido" class="doctor-main">
                <?php foreach (doctor_flash_get() as $flash): ?>
                    <div class="doctor-flash doctor-flash-<?= e($flash['type']) ?>">
                        <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle-2' : ($flash['type'] === 'error' ? 'alert-circle' : 'info') ?>" class="h-4 w-4"></i>
                        <span><?= e($flash['message']) ?></span>
                    </div>
                <?php endforeach; ?>
    <?php
}

function doctor_layout_end(): void {
    global $assets, $contact;
    $assetVersion = (string) max(
        filemtime(__DIR__ . '/../assets/css/app.css'),
        filemtime(__DIR__ . '/../assets/js/app.js'),
        @filemtime(__DIR__ . '/../assets/css/portal.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/css/portal-medico.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/js/portal-medico.js') ?: 0
    );
    ?>
            </main>
        </div>

        <?php render_public_footer($assets, $contact, date('Y')); ?>
        <script src="https://unpkg.com/lucide@latest"></script>
        <script>if (window.lucide) lucide.createIcons();</script>
        <script src="<?= e(base_url('assets/js/portal-medico.js')) ?>?v=<?= e($assetVersion) ?>"></script>
    </body>
    </html>
    <?php
}

function doctor_render_errors(?array $errors): string {
    if (!$errors) return '';
    $out = '<ul class="portal-errors">';
    foreach ($errors as $field => $msgs) {
        foreach ((array)$msgs as $m) {
            $out .= '<li>' . e($m) . '</li>';
        }
    }
    return $out . '</ul>';
}
