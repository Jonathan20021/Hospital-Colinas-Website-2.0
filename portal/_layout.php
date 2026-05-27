<?php
/**
 * Layout compartido para todas las páginas del Portal de Pacientes.
 * Uso:
 *   portal_layout_begin($title, $active);
 *     // contenido HTML
 *   portal_layout_end();
 */

// El portal vive bajo /portal/. base_url() del sitio público usa
// $_SERVER['SCRIPT_NAME'] para calcular su raíz; si lo dejamos como
// /portal/xxx.php, todos los assets y links del header se generan con
// /portal/ prefijado (incorrecto). Pretendemos ser /index.php para que
// la raíz del sitio se calcule como '/' (la raíz real).
$_SERVER['_PORTAL_ORIG_SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '';
$_SERVER['SCRIPT_NAME'] = preg_replace(
    '#/portal/[^/?]*\.php$#',
    '/index.php',
    $_SERVER['SCRIPT_NAME'] ?? '/index.php'
);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/content.php';
require_once __DIR__ . '/../includes/public-layout.php';
require_once __DIR__ . '/../includes/portal_client.php';
require_once __DIR__ . '/../includes/portal_session.php';

function portal_layout_begin(string $title, string $active = ''): void {
    portal_session_start();
    global $assets, $contact;
    $assetVersion = (string) max(
        filemtime(__DIR__ . '/../assets/css/app.css'),
        filemtime(__DIR__ . '/../assets/js/app.js'),
        @filemtime(__DIR__ . '/../assets/css/portal.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/js/portal.js') ?: 0
    );
    ?>
    <!DOCTYPE html>
    <html lang="es-DO">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($title) ?> | Portal de Pacientes - Hospital Las Colinas</title>
        <meta name="robots" content="noindex, nofollow">
        <meta name="theme-color" content="#262161">
        <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($assetVersion) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= e($assetVersion) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/portal.css')) ?>?v=<?= e($assetVersion) ?>">
        <meta name="csrf-token" content="<?= e(portal_csrf_token()) ?>">
    </head>
    <body class="bg-slate-50 font-sans text-slate-950 antialiased portal-page">
        <a class="skip-link" href="#contenido">Saltar al contenido</a>
        <?php render_public_header($assets, $contact, ''); ?>

        <div class="portal-shell <?= portal_is_logged_in() ? 'portal-shell-app' : 'portal-shell-auth' ?>">
            <?php if (portal_is_logged_in()): ?>
                <aside class="portal-sidebar" aria-label="Menú del paciente">
                    <div class="portal-profile">
                        <div class="portal-avatar"><i data-lucide="user-round" class="h-6 w-6"></i></div>
                        <div>
                            <p class="portal-greeting">Hola,</p>
                            <p class="portal-name"><?= e((portal_patient()['name'] ?? '')) ?></p>
                        </div>
                    </div>
                    <nav class="portal-nav" aria-label="Navegación del portal">
                        <a href="<?= e(base_url('portal/dashboard.php')) ?>" class="portal-nav-link <?= $active === 'dashboard' ? 'is-active' : '' ?>"><i data-lucide="layout-dashboard" class="h-4 w-4"></i>Inicio</a>
                        <a href="<?= e(base_url('portal/agendar.php')) ?>" class="portal-nav-link <?= $active === 'agendar' ? 'is-active' : '' ?>"><i data-lucide="calendar-plus" class="h-4 w-4"></i>Agendar cita</a>
                        <a href="<?= e(base_url('portal/mis-citas.php')) ?>" class="portal-nav-link <?= $active === 'mis-citas' ? 'is-active' : '' ?>"><i data-lucide="calendar-check" class="h-4 w-4"></i>Mis citas</a>
                        <a href="<?= e(base_url('portal/perfil.php')) ?>" class="portal-nav-link <?= $active === 'perfil' ? 'is-active' : '' ?>"><i data-lucide="user-cog" class="h-4 w-4"></i>Mi perfil</a>
                        <a href="<?= e(base_url('portal/logout.php')) ?>" class="portal-nav-link portal-nav-logout"><i data-lucide="log-out" class="h-4 w-4"></i>Cerrar sesión</a>
                    </nav>
                </aside>
            <?php endif; ?>

            <main id="contenido" class="portal-main">
                <?php foreach (portal_flash_get() as $flash): ?>
                    <div class="portal-flash portal-flash-<?= e($flash['type']) ?>">
                        <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle-2' : ($flash['type'] === 'error' ? 'alert-circle' : 'info') ?>" class="h-4 w-4"></i>
                        <span><?= e($flash['message']) ?></span>
                    </div>
                <?php endforeach; ?>
    <?php
}

function portal_layout_end(): void {
    global $assets, $contact;
    $assetVersion = (string) max(
        filemtime(__DIR__ . '/../assets/css/app.css'),
        filemtime(__DIR__ . '/../assets/js/app.js'),
        @filemtime(__DIR__ . '/../assets/css/portal.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/js/portal.js') ?: 0
    );
    ?>
            </main>
        </div>

        <?php render_public_footer($assets, $contact, date('Y')); ?>
        <script src="https://unpkg.com/lucide@latest"></script>
        <script>if (window.lucide) lucide.createIcons();</script>
        <script src="<?= e(base_url('assets/js/portal.js')) ?>?v=<?= e($assetVersion) ?>"></script>
    </body>
    </html>
    <?php
}

/**
 * Helper para mostrar errores de validación devueltos por la API
 * en un bloque simple bajo el form.
 */
function portal_render_errors(?array $errors): string {
    if (!$errors) return '';
    $out = '<ul class="portal-errors">';
    foreach ($errors as $field => $msgs) {
        foreach ((array)$msgs as $m) {
            $out .= '<li>' . e($m) . '</li>';
        }
    }
    return $out . '</ul>';
}
