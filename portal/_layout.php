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

function portal_layout_begin(string $title, string $active = ''): void
{
    portal_session_start();
    $GLOBALS['portal_active'] = $active;
    global $assets, $contact;
    $assetVersion = (string) max(
        filemtime(__DIR__ . '/../assets/css/app.css'),
        filemtime(__DIR__ . '/../assets/js/app.js'),
        @filemtime(__DIR__ . '/../assets/css/portal.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/js/portal.js') ?: 0,
        @filemtime(__DIR__ . '/../assets/css/portal-accessible.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/css/portal-v3.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/css/portal-pwa.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/js/portal-pwa.js') ?: 0
    );
    ?>
    <!DOCTYPE html>
    <html lang="es-DO">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <title><?= e($title) ?> | Portal de Pacientes - Hospital Las Colinas</title>
        <meta name="robots" content="noindex, nofollow">
        <meta name="theme-color" content="#262161">
        <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link
            href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800;900&family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap"
            rel="stylesheet">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($assetVersion) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= e($assetVersion) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/portal.css')) ?>?v=<?= e($assetVersion) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/portal-accessible.css')) ?>?v=<?= e($assetVersion) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/portal-v3.css')) ?>?v=<?= e($assetVersion) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/portal-pwa.css')) ?>?v=<?= e($assetVersion) ?>">
        <meta name="csrf-token" content="<?= e(portal_csrf_token()) ?>">
        <meta name="portal-api-url" content="<?= e(base_url('api/portal-proxy.php')) ?>">
        <?php portal_pwa_head(); ?>
        <script>
            try {
                if (localStorage.getItem('hglc-portal-sidebar') === 'collapsed') {
                    document.documentElement.classList.add('portal-sidebar-collapsed');
                }
            } catch (e) {}
        </script>
    </head>

    <body class="bg-slate-50 font-sans text-slate-950 antialiased portal-page<?= $active !== '' ? ' portal-page-' . e(preg_replace('/[^a-z0-9_-]/i', '', $active)) : '' ?>">
        <a class="skip-link" href="#contenido">Saltar al contenido</a>

        <?php
        $isLoggedIn = portal_is_logged_in();
        $pName = $isLoggedIn ? (string) (portal_patient()['name'] ?? '') : '';
        $parts = preg_split('/\s+/', trim($pName)) ?: [];
        $initials = '';
        foreach ($parts as $p) {
            if ($p !== '' && mb_strlen($initials, 'UTF-8') < 2) {
                $initials .= mb_substr($p, 0, 1, 'UTF-8');
            }
        }
        $initials = $initials !== '' ? mb_strtoupper($initials, 'UTF-8') : '?';
        $friendlyName = trim(mb_convert_case(mb_strtolower($pName, 'UTF-8'), MB_CASE_TITLE, 'UTF-8'));
        $friendlyParts = preg_split('/\s+/', $friendlyName) ?: [];
        $compactName = $friendlyParts
            ? trim($friendlyParts[0] . (count($friendlyParts) > 1 ? ' ' . $friendlyParts[count($friendlyParts) - 1] : ''))
            : 'Paciente';
        ?>
        <header class="portal-topbar">
            <div class="portal-topbar-inner">
                <a href="<?= e(base_url($isLoggedIn ? 'portal/dashboard.php' : 'portal/login.php')) ?>"
                    class="portal-topbar-brand" aria-label="Portal de Pacientes - Hospital Las Colinas">
                    <img src="<?= e(base_url($assets['logo'])) ?>" alt="Hospital Las Colinas" class="portal-topbar-logo">
                    <span class="portal-topbar-sep" aria-hidden="true"></span>
                    <span class="portal-topbar-title">Portal de Pacientes</span>
                </a>
                <div class="portal-topbar-actions">
                    <span class="portal-topbar-secure">
                        <i data-lucide="lock-keyhole" class="h-4 w-4"></i>
                        <span>Conexión segura</span>
                    </span>
                    <?php if ($isLoggedIn): ?>
                        <button type="button" class="portal-topbar-account js-open-more" aria-label="Abrir opciones de la cuenta" title="<?= e($friendlyName ?: 'Paciente') ?>">
                            <span class="portal-topbar-mini-avatar"><?= e($initials) ?></span>
                            <span class="portal-topbar-account-name"><?= e($compactName) ?></span>
                            <i data-lucide="chevron-down" class="h-4 w-4"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="portal-shell <?= $isLoggedIn ? 'portal-shell-app' : 'portal-shell-auth' ?>">
            <?php if ($isLoggedIn): ?>
                <aside class="portal-sidebar" aria-label="Menú del paciente">
                    <div class="portal-profile">
                        <div class="portal-avatar portal-avatar-initials"><?= e($initials) ?></div>
                        <div class="portal-profile-copy">
                            <p class="portal-greeting">Hola,</p>
                            <p class="portal-name" title="<?= e($friendlyName) ?>"><?= e($friendlyName) ?></p>
                        </div>
                        <button type="button" class="portal-sidebar-toggle" id="portal-sidebar-toggle"
                            aria-label="Colapsar menú" aria-expanded="true" title="Colapsar menú">
                            <i data-lucide="panel-left-close"></i>
                        </button>
                    </div>
                    <nav class="portal-nav" aria-label="Navegación del portal">
                        <a href="<?= e(base_url('portal/dashboard.php')) ?>" title="Inicio"
                            class="portal-nav-link <?= $active === 'dashboard' ? 'is-active' : '' ?>"><i
                                data-lucide="layout-dashboard" class="h-4 w-4"></i><span class="portal-nav-label">Inicio</span></a>
                        <a href="<?= e(base_url('portal/agendar.php')) ?>" title="Agendar cita"
                            class="portal-nav-link <?= $active === 'agendar' ? 'is-active' : '' ?>"><i
                                data-lucide="calendar-plus" class="h-4 w-4"></i><span class="portal-nav-label">Agendar cita</span></a>
                        <a href="<?= e(base_url('portal/mis-citas.php')) ?>" title="Mis citas"
                            class="portal-nav-link <?= $active === 'mis-citas' ? 'is-active' : '' ?>"><i
                                data-lucide="calendar-check" class="h-4 w-4"></i><span class="portal-nav-label">Mis citas</span></a>
                        <a href="<?= e(base_url('portal/mensajes.php')) ?>" title="Mensajes"
                            class="portal-nav-link <?= $active === 'mensajes' ? 'is-active' : '' ?>"><i
                                data-lucide="messages-square" class="h-4 w-4"></i><span class="portal-nav-label">Mensajes</span></a>
                        <a href="<?= e(base_url('portal/consultas.php')) ?>" title="Mis consultas"
                            class="portal-nav-link <?= $active === 'consultas' ? 'is-active' : '' ?>"><i
                                data-lucide="stethoscope" class="h-4 w-4"></i><span class="portal-nav-label">Mis consultas</span></a>
                        <a href="<?= e(base_url('portal/recetas.php')) ?>" title="Mis recetas"
                            class="portal-nav-link <?= $active === 'recetas' ? 'is-active' : '' ?>"><i
                                data-lucide="file-text" class="h-4 w-4"></i><span class="portal-nav-label">Mis recetas</span></a>
                        <a href="<?= e(base_url('portal/laboratorio.php')) ?>" title="Resultados de laboratorio"
                            class="portal-nav-link <?= $active === 'laboratorio' ? 'is-active' : '' ?>"><i
                                data-lucide="flask-conical" class="h-4 w-4"></i><span class="portal-nav-label">Resultados de laboratorio</span></a>
                        <a href="<?= e(base_url('portal/estudios.php')) ?>" title="Mis imágenes"
                            class="portal-nav-link <?= $active === 'estudios' ? 'is-active' : '' ?>"><i
                                data-lucide="scan" class="h-4 w-4"></i><span class="portal-nav-label">Mis imágenes</span></a>
                        <a href="<?= e(base_url('portal/perfil.php')) ?>" title="Mi perfil"
                            class="portal-nav-link <?= $active === 'perfil' ? 'is-active' : '' ?>"><i data-lucide="user-cog"
                                class="h-4 w-4"></i><span class="portal-nav-label">Mi perfil</span></a>
                        <a href="<?= e(base_url('portal/logout.php')) ?>" class="portal-nav-link portal-nav-logout" title="Cerrar sesión"><i
                                data-lucide="log-out" class="h-4 w-4"></i><span class="portal-nav-label">Cerrar sesión</span></a>
                    </nav>
                    <div class="portal-sidebar-support">
                        <span class="portal-support-label">¿Necesitas ayuda?</span>
                        <a href="tel:<?= e(preg_replace('/[^\d+]/', '', (string)($contact['phone'] ?? '8098060444'))) ?>">
                            <i data-lucide="phone"></i>
                            <span class="portal-support-phone"><?= e($contact['phone'] ?? '(809) 806-0444') ?></span>
                        </a>
                    </div>
                </aside>
            <?php endif; ?>

            <main id="contenido" class="portal-main">
                <?php foreach (portal_flash_get() as $flash): ?>
                    <div class="portal-flash portal-flash-<?= e($flash['type']) ?>">
                        <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle-2' : ($flash['type'] === 'error' ? 'alert-circle' : 'info') ?>"
                            class="h-4 w-4"></i>
                        <span><?= e($flash['message']) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php
}

function portal_layout_end(): void
{
    global $assets, $contact;
    $assetVersion = (string) max(
        filemtime(__DIR__ . '/../assets/css/app.css'),
        filemtime(__DIR__ . '/../assets/js/app.js'),
        @filemtime(__DIR__ . '/../assets/css/portal.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/js/portal.js') ?: 0,
        @filemtime(__DIR__ . '/../assets/css/portal-accessible.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/css/portal-v3.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/css/portal-pwa.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/js/portal-pwa.js') ?: 0
    );
    ?>
                <?php if (portal_is_logged_in()): ?>
                    <footer class="portal-app-footer">
                        <span>Hospital General Las Colinas · Tu información se mantiene protegida.</span>
                        <span><a href="tel:<?= e(preg_replace('/[^\d+]/', '', (string)($contact['phone'] ?? '8098060444'))) ?>">Ayuda: <?= e($contact['phone'] ?? '(809) 806-0444') ?></a></span>
                    </footer>
                <?php endif; ?>
            </main>
        </div>

        <?php if (portal_is_logged_in()): ?>
            <?php
            $activeResult = in_array($GLOBALS['portal_active'] ?? '', ['laboratorio', 'estudios'], true);
            ?>
            <nav class="portal-mobile-nav" aria-label="Navegación móvil">
                <a href="<?= e(base_url('portal/dashboard.php')) ?>" class="<?= ($GLOBALS['portal_active'] ?? '') === 'dashboard' ? 'is-active' : '' ?>">
                    <i data-lucide="house"></i><span>Inicio</span>
                </a>
                <a href="<?= e(base_url('portal/mis-citas.php')) ?>" class="<?= ($GLOBALS['portal_active'] ?? '') === 'mis-citas' ? 'is-active' : '' ?>">
                    <i data-lucide="calendar-days"></i><span>Citas</span>
                </a>
                <a href="<?= e(base_url('portal/agendar.php')) ?>" class="portal-mobile-primary <?= ($GLOBALS['portal_active'] ?? '') === 'agendar' ? 'is-active' : '' ?>">
                    <span class="portal-mobile-primary-icon"><i data-lucide="plus"></i></span><span>Agendar</span>
                </a>
                <a href="<?= e(base_url('portal/laboratorio.php')) ?>" class="<?= $activeResult ? 'is-active' : '' ?>">
                    <i data-lucide="flask-conical"></i><span>Resultados</span>
                </a>
                <button type="button" class="js-open-more" aria-label="Abrir más opciones">
                    <i data-lucide="menu"></i><span>Más</span>
                </button>
            </nav>

            <dialog class="portal-dialog" id="portal-more-dialog" aria-labelledby="portal-more-title">
                <div class="portal-dialog-head">
                    <h2 id="portal-more-title">Más opciones</h2>
                    <button type="button" class="portal-dialog-close js-close-dialog" aria-label="Cerrar">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div class="portal-dialog-body">
                    <nav class="portal-more-nav" aria-label="Opciones adicionales">
                        <a href="<?= e(base_url('portal/mensajes.php')) ?>"><i data-lucide="messages-square"></i> Mensajes</a>
                        <a href="<?= e(base_url('portal/consultas.php')) ?>"><i data-lucide="stethoscope"></i> Mis consultas</a>
                        <a href="<?= e(base_url('portal/recetas.php')) ?>"><i data-lucide="file-text"></i> Mis recetas</a>
                        <a href="<?= e(base_url('portal/estudios.php')) ?>"><i data-lucide="scan-line"></i> Mis imágenes</a>
                        <a href="<?= e(base_url('portal/perfil.php')) ?>"><i data-lucide="user-cog"></i> Mi perfil</a>
                        <a href="#" class="pwa-install-entry" onclick="if(window.HGLCPwa){HGLCPwa.install();}return false;"><i data-lucide="download"></i> Instalar app</a>
                        <a href="<?= e(base_url('portal/logout.php')) ?>"><i data-lucide="log-out"></i> Cerrar sesión</a>
                    </nav>
                </div>
            </dialog>

            <dialog class="portal-dialog" id="portal-cancel-dialog" aria-labelledby="portal-cancel-title">
                <form method="dialog" id="portal-cancel-form">
                    <div class="portal-dialog-head">
                        <h2 id="portal-cancel-title">Cancelar cita</h2>
                        <button type="button" class="portal-dialog-close js-close-dialog" aria-label="Cerrar">
                            <i data-lucide="x"></i>
                        </button>
                    </div>
                    <div class="portal-dialog-body">
                        <p class="portal-subtitle">La cita dejará de estar reservada. Puedes indicar el motivo para que el hospital tenga contexto.</p>
                        <label class="form-label" for="portal-cancel-reason">Motivo (opcional)</label>
                        <textarea class="form-input" id="portal-cancel-reason" rows="3" placeholder="Ej.: No podré asistir en esa fecha"></textarea>
                    </div>
                    <div class="portal-dialog-actions">
                        <button type="button" class="btn btn-outline js-close-dialog">Conservar cita</button>
                        <button type="submit" class="btn btn-green" id="portal-confirm-cancel">Cancelar cita</button>
                    </div>
                </form>
            </dialog>
        <?php endif; ?>

        <div class="portal-toast-region" id="portal-toast-region" aria-live="polite" aria-atomic="true"></div>
        <script src="https://unpkg.com/lucide@latest"></script>
        <script>if (window.lucide) lucide.createIcons();</script>
        <script src="<?= e(base_url('assets/js/portal.js')) ?>?v=<?= e($assetVersion) ?>"></script>
        <script>
            window.HGLC_PWA = {
                sw: '<?= e(base_url('portal/sw.js')) ?>',
                scope: '<?= e(base_url('portal/')) ?>',
                icon: '<?= e(base_url('portal/icons/icon-192.png')) ?>'
            };
        </script>
        <script src="<?= e(base_url('assets/js/portal-pwa.js')) ?>?v=<?= e($assetVersion) ?>"></script>
    </body>

    </html>
    <?php
}

/**
 * Helper para mostrar errores de validación devueltos por la API
 * en un bloque simple bajo el form.
 */
function portal_render_errors(?array $errors): string
{
    if (!$errors)
        return '';
    $out = '<ul class="portal-errors">';
    foreach ($errors as $field => $msgs) {
        foreach ((array) $msgs as $m) {
            $out .= '<li>' . e($m) . '</li>';
        }
    }
    return $out . '</ul>';
}

function portal_auth_intro(): void
{
    global $assets;
    ?>
    <aside class="portal-auth-intro" aria-label="Información del portal">
        <img src="<?= e(base_url($assets['logo'])) ?>" alt="Hospital General Las Colinas">
        <div>
            <h2>Tu salud, más cerca de ti.</h2>
            <p>Consulta tus citas, resultados, recetas e imágenes médicas desde un espacio privado diseñado para ayudarte con claridad.</p>
            <div class="portal-auth-trust">
                <span><i data-lucide="lock-keyhole"></i> Acceso protegido</span>
                <span><i data-lucide="heart-pulse"></i> Información clínica centralizada</span>
            </div>
        </div>
    </aside>
    <?php
}

/**
 * Metadatos PWA del Portal del Paciente (manifest, íconos Apple, splash de iOS).
 * Rutas absolutas vía base_url(); el theme-color ya se define en el <head>.
 */
function portal_pwa_head(): void
{
    $icons = base_url('portal/icons/');
    ?>
        <link rel="manifest" href="<?= e(base_url('portal/manifest.webmanifest')) ?>">
        <meta name="application-name" content="Mi Hospital">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="Mi Hospital">
        <link rel="apple-touch-icon" href="<?= e($icons . 'apple-touch-icon.png') ?>">
        <link rel="icon" type="image/png" sizes="32x32" href="<?= e($icons . 'favicon-32.png') ?>">
        <link rel="icon" type="image/png" sizes="16x16" href="<?= e($icons . 'favicon-16.png') ?>">
    <?php
    // Splash de iOS: [archivo, ancho CSS, alto CSS, DPR] (portrait).
    $splashes = [
        ['1290x2796', 430, 932, 3], ['1179x2556', 393, 852, 3], ['1170x2532', 390, 844, 3],
        ['1125x2436', 375, 812, 3], ['1242x2688', 414, 896, 3], ['828x1792', 414, 896, 2],
        ['750x1334', 375, 667, 2], ['1536x2048', 768, 1024, 2], ['1668x2388', 834, 1194, 2],
        ['2048x2732', 1024, 1366, 2],
    ];
    foreach ($splashes as [$file, $w, $h, $dpr]) {
        $media = "(device-width: {$w}px) and (device-height: {$h}px) and (-webkit-device-pixel-ratio: {$dpr}) and (orientation: portrait)";
        echo '        <link rel="apple-touch-startup-image" media="' . e($media) . '" href="' . e($icons . 'splash-' . $file . '.png') . "\">\n";
    }
}
