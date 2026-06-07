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

function doctor_layout_begin(string $title, string $active = ''): void
{
    doctor_portal_session_start();
    global $assets, $contact;
    $assetVersion = (string) max(
        filemtime(__DIR__ . '/../assets/css/app.css'),
        filemtime(__DIR__ . '/../assets/js/app.js'),
        @filemtime(__DIR__ . '/../assets/css/portal.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/css/portal-medico.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/css/portal-medico-shell.css') ?: 0,
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
        <link
            href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800;900&family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap"
            rel="stylesheet">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($assetVersion) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= e($assetVersion) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/portal.css')) ?>?v=<?= e($assetVersion) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/portal-medico.css')) ?>?v=<?= e($assetVersion) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/portal-medico-shell.css')) ?>?v=<?= e($assetVersion) ?>">
        <meta name="csrf-token" content="<?= e(doctor_csrf_token()) ?>">
    </head>

    <?php
    $dmLogged = doctor_is_logged_in();
    $dmTitles = ['dashboard'=>'Inicio','agenda'=>'Mi agenda','pacientes'=>'Pacientes','consulta'=>'Consulta','disponibilidad'=>'Disponibilidad','analytics'=>'Analytics','cuenta'=>'Mi cuenta','perfil'=>'Mi perfil'];
    $dmPg = $dmTitles[$active] ?? $title;
    if ($dmLogged) {
        $doctor = doctor_current() ?? [];
        $dName = (string) ($doctor['name'] ?? '');
        $friendlyName = trim(mb_convert_case(mb_strtolower($dName, 'UTF-8'), MB_CASE_TITLE, 'UTF-8'));
        $specialty = (string) ($doctor['specialty'] ?? '');
        [$avc1, $avc2] = doctor_avatar_palette($dName);
        $avInitials = doctor_initials($dName);
        $dmNav = [
            ['dashboard','layout-dashboard','Inicio'],
            ['agenda','calendar-days','Mi agenda'],
            ['pacientes','users','Pacientes'],
            ['consulta','stethoscope','Consulta'],
            ['disponibilidad','calendar-clock','Disponibilidad'],
            ['analytics','bar-chart-3','Analytics'],
        ];
    }
    ?>
    <body class="bg-slate-50 font-sans text-slate-950 antialiased portal-page doctor-portal-page <?= $dmLogged ? 'is-app' : '' ?>">
        <a class="skip-link" href="#contenido">Saltar al contenido</a>

    <?php if ($dmLogged): ?>
        <div class="dm-app" id="dmApp">
            <aside class="dm-sb" aria-label="Menú del médico">
                <a class="dm-brand" href="<?= e(base_url('portal-medico/dashboard.php')) ?>" aria-label="Portal del Médico — Hospital Las Colinas">
                    <img src="<?= e(base_url($assets['logo'])) ?>" alt="Hospital Las Colinas">
                </a>
                <div class="dm-label">Menú</div>
                <?php foreach ($dmNav as [$slug,$ic,$lbl]): ?>
                    <a href="<?= e(base_url('portal-medico/'.$slug.'.php')) ?>" class="dm-link <?= $active===$slug?'on':'' ?>" title="<?= e($lbl) ?>"><i data-lucide="<?= $ic ?>"></i><span class="t"><?= e($lbl) ?></span></a>
                <?php endforeach; ?>
                <div class="dm-label">Cuenta</div>
                <a href="<?= e(base_url('portal-medico/cuenta.php')) ?>" class="dm-link <?= $active==='cuenta'?'on':'' ?>" title="Mi cuenta"><i data-lucide="shield-check"></i><span class="t">Mi cuenta</span></a>
                <a href="<?= e(base_url('portal-medico/logout.php')) ?>" class="dm-link dm-logout" title="Cerrar sesión"><i data-lucide="log-out"></i><span class="t">Cerrar sesión</span></a>
                <div class="dm-status">
                    <i data-lucide="shield-check"></i>
                    <div><div class="t">Conexión segura</div><div class="s">Sesión cifrada · 2FA</div></div>
                </div>
                <div class="dm-prof">
                    <div class="dm-av" style="background:linear-gradient(135deg, <?= e($avc1) ?>, <?= e($avc2) ?>);"><?= e($avInitials) ?></div>
                    <div>
                        <div class="n" title="<?= e($friendlyName) ?>"><?= e($friendlyName) ?></div>
                        <?php if ($specialty): ?><div class="r"><?= e($specialty) ?></div><?php endif; ?>
                    </div>
                </div>
            </aside>
            <div class="dm-backdrop" data-dm-close></div>
            <div class="dm-maincol">
                <header class="dm-top">
                    <button type="button" class="dm-burger" data-dm-toggle aria-label="Mostrar u ocultar menú"><i data-lucide="menu"></i></button>
                    <div>
                        <div class="dm-pg"><?= e($dmPg) ?></div>
                        <div class="dm-crumb">Portal Médico · Hospital Las Colinas</div>
                    </div>
                    <div class="dm-topr">
                        <span class="dm-tbtn" title="Conexión segura · verificación en dos pasos"><i data-lucide="shield-check"></i></span>
                    </div>
                </header>
                <main id="contenido" class="doctor-main">
    <?php else: ?>
        <header class="portal-topbar">
            <div class="portal-topbar-inner">
                <a href="<?= e(base_url('portal-medico/login.php')) ?>" class="portal-topbar-brand" aria-label="Portal del Médico - Hospital Las Colinas">
                    <img src="<?= e(base_url($assets['logo'])) ?>" alt="Hospital Las Colinas" class="portal-topbar-logo">
                    <span class="portal-topbar-sep" aria-hidden="true"></span>
                    <span class="portal-topbar-title">Portal del Médico</span>
                </a>
                <span class="portal-topbar-secure">
                    <i data-lucide="shield-check" class="h-4 w-4"></i>
                    <span>Conexión segura</span>
                </span>
            </div>
        </header>
        <div class="doctor-shell doctor-shell-auth">
            <main id="contenido" class="doctor-main">
    <?php endif; ?>
                <?php foreach (doctor_flash_get() as $flash): ?>
                    <div class="doctor-flash doctor-flash-<?= e($flash['type']) ?>">
                        <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle-2' : ($flash['type'] === 'error' ? 'alert-circle' : 'info') ?>"
                            class="h-4 w-4"></i>
                        <span><?= e($flash['message']) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php
}

function doctor_layout_end(): void
{
    global $assets, $contact;
    $assetVersion = (string) max(
        filemtime(__DIR__ . '/../assets/css/app.css'),
        filemtime(__DIR__ . '/../assets/js/app.js'),
        @filemtime(__DIR__ . '/../assets/css/portal.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/css/portal-medico.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/css/portal-medico-shell.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/js/portal-medico.js') ?: 0
    );
    ?>
                </main>
        <?php if (doctor_is_logged_in()): ?>
            </div><!-- .dm-maincol -->
        </div><!-- .dm-app -->
        <?php else: ?>
        </div><!-- .doctor-shell -->
        <?php endif; ?>

        <?php // Portal aislado: sin footer del sitio publico. ?>
        <script src="https://unpkg.com/lucide@latest"></script>
        <script>if (window.lucide) lucide.createIcons();</script>
        <script src="<?= e(base_url('assets/js/portal-medico.js')) ?>?v=<?= e($assetVersion) ?>"></script>
    </body>

    </html>
    <?php
}

function doctor_render_errors(?array $errors): string
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
