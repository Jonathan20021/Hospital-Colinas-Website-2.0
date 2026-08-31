<?php
/**
 * /empleos/baja?token=… — cancela la suscripción al newsletter de empleos.
 * Enlace incluido en cada correo. Relaya el token por el puente de JENOFONTE.
 */
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/public-layout.php';
require __DIR__ . '/includes/portal_client.php';

$active = 'empleos';
$year = date('Y');
$assetVersion = (string) max(
    @filemtime(__DIR__ . '/assets/css/empleos.css') ?: 0,
    @filemtime(__DIR__ . '/assets/css/app-core.css') ?: 0
);

$token = preg_replace('/[^a-f0-9]/i', '', (string) ($_GET['token'] ?? ''));
$r = $token !== '' ? portal_api_call('GET', '/portal/empleos/baja?token=' . rawurlencode($token)) : ['ok' => false];
$ok = !empty($r['ok']);
$title = ($ok ? 'Suscripción cancelada' : 'Enlace no válido') . ' | Empleos — Hospital General Las Colinas';
?>
<!DOCTYPE html>
<html lang="es-DO">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <meta name="robots" content="noindex, follow">
    <meta name="theme-color" content="#262161">
    <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">
    <?php /* Fuentes auto-hospedadas (Inter + Outfit + Plus Jakarta Sans, VARIABLES):
             mismo origen, sin DNS/TLS a Google ni CSS render-blocking. */ ?>
    <link rel="preload" as="font" type="font/woff2" href="<?= e(base_url('assets/fonts/inter-latin.woff2')) ?>" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="<?= e(base_url('assets/fonts/outfit-latin.woff2')) ?>" crossorigin>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/fonts-public.css')) ?>?v=<?= e((string) (@filemtime(__DIR__ . '/assets/css/fonts-public.css') ?: 1)) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/tailwind.generated.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app-core.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/empleos.css')) ?>?v=<?= e($assetVersion) ?>">
</head>

<body class="bg-white font-sans text-slate-950 antialiased empleos-page">
<?php render_public_header($assets, $contact, $active); ?>
<main class="emp-v2">
    <div class="emp-wrap">
        <div class="emp-empty">
            <?php if ($ok): ?>
                <span class="emp-empty-icon"><i data-lucide="bell-off"></i></span>
                <h2>Suscripción cancelada</h2>
                <p>Ya no recibirás correos de alertas de empleo. Lamentamos verte partir — puedes volver a suscribirte cuando quieras.</p>
                <a class="btn btn-outline" href="<?= e(base_url('empleos')) ?>"><i data-lucide="briefcase"></i> Ver vacantes</a>
            <?php else: ?>
                <span class="emp-empty-icon"><i data-lucide="alert-circle"></i></span>
                <h2>Enlace no válido</h2>
                <p>Este enlace de baja no es válido. Si sigues recibiendo correos, usa el enlace "Cancelar mi suscripción" del correo más reciente.</p>
                <a class="btn btn-outline" href="<?= e(base_url('empleos')) ?>"><i data-lucide="briefcase"></i> Ver vacantes</a>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php render_public_footer($assets, $contact, $year); ?>
<script src="<?= e(base_url('assets/js/lucide-subset.js')) ?>?v=<?= e((string) (@filemtime(__DIR__ . '/assets/js/lucide-subset.js') ?: 1)) ?>"></script>
<script>if (window.lucide) lucide.createIcons();</script>
</body>
</html>
