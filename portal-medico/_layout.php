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

// Teleconsulta: deshabilitada por ahora (el backend de LiveKit ya existe).
// Para reactivarla en el futuro, cambiar este flag a true: vuelve a habilitar
// el botón en consulta.php y el acceso a teleconsulta.php.
if (!defined('DOCTOR_TELECONSULT_ENABLED')) define('DOCTOR_TELECONSULT_ENABLED', false);

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
        @filemtime(__DIR__ . '/../assets/css/portal-medico-pro.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/css/portal-medico-ui.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/js/portal-medico.js') ?: 0,
        @filemtime(__DIR__ . '/../assets/js/portal-medico-pwa.js') ?: 0
    );
    ?>
    <!DOCTYPE html>
    <html lang="es-DO">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <title><?= e($title) ?> | Portal Medico - Hospital Las Colinas</title>
        <meta name="robots" content="noindex, nofollow">
        <meta name="theme-color" content="#2a2566">
        <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">
        <?php doctor_pwa_head(); ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link
            href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800;900&family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
            rel="stylesheet">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($assetVersion) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= e($assetVersion) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/portal.css')) ?>?v=<?= e($assetVersion) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/portal-medico.css')) ?>?v=<?= e($assetVersion) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/portal-medico-shell.css')) ?>?v=<?= e($assetVersion) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/portal-medico-pro.css')) ?>?v=<?= e($assetVersion) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/portal-medico-ui.css')) ?>?v=<?= e($assetVersion) ?>">
        <meta name="csrf-token" content="<?= e(doctor_csrf_token()) ?>">
        <meta name="doctor-api-proxy" content="<?= e(base_url('api/doctor-proxy.php')) ?>">
    </head>

    <?php
    $dmLogged = doctor_is_logged_in();
    $dmTitles = ['dashboard'=>'Inicio','agenda'=>'Mi agenda','pacientes'=>'Pacientes','mensajes'=>'Mensajes','consulta'=>'Consulta','disponibilidad'=>'Disponibilidad','listas'=>'Listas de servicio','analytics'=>'Analytics','herramientas'=>'Herramientas','soporte'=>'Soporte TI','cuenta'=>'Mi cuenta','perfil'=>'Mi perfil'];
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
            ['mensajes','messages-square','Mensajes'],
            ['consulta','stethoscope','Consulta'],
            ['disponibilidad','calendar-clock','Disponibilidad'],
            ['listas','calendar-range','Listas de servicio'],
            ['analytics','bar-chart-3','Analytics'],
            ['herramientas','calculator','Herramientas'],
            ['soporte','life-buoy','Soporte TI'],
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
                    <div class="dm-top-title">
                        <div class="dm-pg"><?= e($dmPg) ?></div>
                        <div class="dm-crumb">Portal Médico · Hospital Las Colinas</div>
                    </div>
                    <form class="dm-search" action="<?= e(base_url('portal-medico/pacientes.php')) ?>" method="GET" role="search">
                        <i data-lucide="search"></i>
                        <input type="search" name="q" placeholder="Buscar paciente por nombre o cédula…" aria-label="Buscar paciente">
                    </form>
                    <div class="dm-topr">
                        <a class="dm-tbtn" href="<?= e(base_url('portal-medico/mensajes.php')) ?>" title="Mensajes" aria-label="Mensajes"><i data-lucide="messages-square"></i></a>
                        <span class="dm-tbtn" title="Conexión segura · verificación en dos pasos"><i data-lucide="shield-check"></i></span>
                        <a class="dm-topav" href="<?= e(base_url('portal-medico/cuenta.php')) ?>" title="Mi cuenta" aria-label="Mi cuenta">
                            <span class="dm-av" style="background:linear-gradient(135deg, <?= e($avc1) ?>, <?= e($avc2) ?>);"><?= e($avInitials) ?></span>
                        </a>
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
        @filemtime(__DIR__ . '/../assets/css/portal-medico-pro.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/css/portal-medico-ui.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/js/portal-medico.js') ?: 0,
        @filemtime(__DIR__ . '/../assets/js/portal-medico-pwa.js') ?: 0
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
        <script src="<?= e(base_url('assets/js/lucide.min.js')) ?>?v=<?= (int)(@filemtime(__DIR__ . '/../assets/js/lucide.min.js') ?: 1) ?>"></script>
        <script>if (window.lucide) lucide.createIcons();</script>
        <script src="<?= e(base_url('assets/js/portal-medico.js')) ?>?v=<?= e($assetVersion) ?>"></script>
        <?php
        // Build del SW: cambia cuando cambia CUALQUIER asset versionado o el
        // propio sw.js → registrar sw.js?v=<build> hace que el navegador instale
        // un worker nuevo en cada deploy y SIEMPRE muestre el aviso "Actualizar".
        $swBuild = max((int) $assetVersion, (int) (@filemtime(__DIR__ . '/sw.js') ?: 0));
        ?>
        <script>
            window.DM_PWA = {
                sw: <?= json_encode(base_url('portal-medico/sw.js') . '?v=' . $swBuild, JSON_UNESCAPED_SLASHES) ?>,
                scope: <?= json_encode(base_url('portal-medico/'), JSON_UNESCAPED_SLASHES) ?>,
                icon: <?= json_encode(base_url('portal-medico/icons/icon-192.png'), JSON_UNESCAPED_SLASHES) ?>
            };
        </script>
        <script src="<?= e(base_url('assets/js/portal-medico-pwa.js')) ?>?v=<?= e($assetVersion) ?>"></script>
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

/** Fecha legible en español a partir de un timestamp. */
function doctor_fecha_es(int $ts, bool $conHora = false): string
{
    $dias  = ['Monday'=>'lunes','Tuesday'=>'martes','Wednesday'=>'miércoles','Thursday'=>'jueves','Friday'=>'viernes','Saturday'=>'sábado','Sunday'=>'domingo'];
    $meses = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
    $s = ucfirst($dias[date('l', $ts)] ?? '') . ' ' . (int)date('j', $ts) . ' de ' . ($meses[(int)date('n', $ts)] ?? '') . ' de ' . date('Y', $ts);
    if ($conHora) $s .= ' · ' . date('H:i', $ts);
    return trim($s);
}

/** Traduce el estado de una cita al español. */
function doctor_estado_es(string $status): string
{
    return ['scheduled'=>'Agendada','completed'=>'Completada','cancelled'=>'Cancelada','pending'=>'Pendiente','no_show'=>'No asistió'][$status] ?? ucfirst($status);
}

/** Fecha corta en español: "15 jun 2026" (o con hora: "15 jun 2026 · 14:30"). */
function doctor_fecha_corta(int $ts, bool $conHora = false): string
{
    $m = [1=>'ene',2=>'feb',3=>'mar',4=>'abr',5=>'may',6=>'jun',7=>'jul',8=>'ago',9=>'sep',10=>'oct',11=>'nov',12=>'dic'];
    $s = (int)date('j', $ts) . ' ' . ($m[(int)date('n', $ts)] ?? '') . ' ' . date('Y', $ts);
    if ($conHora) $s .= ' · ' . date('H:i', $ts);
    return $s;
}

/** Mes abreviado en mayúsculas (para badges de fecha): "JUN". */
function doctor_mes_corto_es(int $ts): string
{
    $m = [1=>'ENE',2=>'FEB',3=>'MAR',4=>'ABR',5=>'MAY',6=>'JUN',7=>'JUL',8=>'AGO',9=>'SEP',10=>'OCT',11=>'NOV',12=>'DIC'];
    return $m[(int)date('n', $ts)] ?? '';
}

/**
 * Etiquetas <head> del PWA: manifest, íconos, metadatos de app instalable y
 * pantallas de carga (splash) de iOS. Las rutas usan base_url() para funcionar
 * igual en localhost (subcarpeta) y en producción (raíz).
 */
function doctor_pwa_head(): void
{
    $icons = base_url('portal-medico/icons/');
    ?>
        <link rel="manifest" href="<?= e(base_url('portal-medico/manifest.webmanifest')) ?>">
        <meta name="application-name" content="Portal Médico">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="Portal Médico">
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

/**
 * Layout MÍNIMO (sin sidebar/topbar) para incrustar contenido del portal en un
 * iframe — p. ej. la nota clínica dentro de la teleconsulta. Mismas hojas de
 * estilo y JS (doctorApi, autocompletado, alertas) bajo el scope .is-app.
 */
function doctor_layout_begin_bare(string $title): void
{
    doctor_portal_session_start();
    // Modo incrustable: permitir SOLO que nuestras propias páginas (mismo origen)
    // lo enmarquen (p. ej. la teleconsulta). Sigue bloqueado para sitios externos.
    if (!headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN');
        header("Content-Security-Policy: " . doctor_portal_csp("'self'"));
    }
    $v = (string) max(
        @filemtime(__DIR__ . '/../assets/css/portal-medico.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/css/portal-medico-shell.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/css/portal-medico-pro.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/css/portal-medico-ui.css') ?: 0,
        @filemtime(__DIR__ . '/../assets/js/portal-medico.js') ?: 0
    );
    ?>
    <!DOCTYPE html>
    <html lang="es-DO">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($title) ?> | HGLC</title>
        <meta name="robots" content="noindex, nofollow">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800;900&family=Outfit:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($v) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= e($v) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/portal.css')) ?>?v=<?= e($v) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/portal-medico.css')) ?>?v=<?= e($v) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/portal-medico-shell.css')) ?>?v=<?= e($v) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/portal-medico-pro.css')) ?>?v=<?= e($v) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/portal-medico-ui.css')) ?>?v=<?= e($v) ?>">
        <meta name="csrf-token" content="<?= e(doctor_csrf_token()) ?>">
        <meta name="doctor-api-proxy" content="<?= e(base_url('api/doctor-proxy.php')) ?>">
        <style>html,body{background:#fff}.doctor-portal-page.is-app .doctor-main{max-width:none;margin:0;padding:14px 16px}</style>
    </head>
    <body class="bg-white font-sans text-slate-950 antialiased portal-page doctor-portal-page is-app">
        <main id="contenido" class="doctor-main">
    <?php
}

function doctor_layout_end_bare(): void
{
    $v = (string) (@filemtime(__DIR__ . '/../assets/js/portal-medico.js') ?: 0);
    ?>
        </main>
        <script src="<?= e(base_url('assets/js/lucide.min.js')) ?>?v=<?= (int)(@filemtime(__DIR__ . '/../assets/js/lucide.min.js') ?: 1) ?>"></script>
        <script>if (window.lucide) lucide.createIcons();</script>
        <script src="<?= e(base_url('assets/js/portal-medico.js')) ?>?v=<?= e($v) ?>"></script>
    </body>
    </html>
    <?php
}
